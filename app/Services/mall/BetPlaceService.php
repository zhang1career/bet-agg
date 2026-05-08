<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Enums\BetLineResult;
use App\Enums\BetOrderStatus;
use App\Enums\PointsHoldState;
use App\Exceptions\bet\InsufficientPointsException;
use App\Exceptions\bet\OddsMovedException;
use App\Exceptions\bet\SelectionNotAcceptingException;
use App\Models\BetOrder;
use App\Models\BetOrderLine;
use App\Models\Game;
use App\Models\Market;
use App\Models\PointsBalance;
use App\Models\PointsFlow;
use App\Repos\mall\BetOrderRepo;
use App\Repos\mall\MarketRepo;
use App\Repos\mall\PointsBalanceRepo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Atomic single-step bet placement (request still sends {@code outcome_code}; persisted as {@code order_item.selection} JSON).
 */
final readonly class BetPlaceService
{
    public function __construct(
        private PointsAdminService $pointsAdmin,
        private SyntheticMatchMarket $synthetic,
        private BetOrderRepo $orders,
        private MarketRepo $markets,
        private PointsBalanceRepo $balances,
    ) {}

    /**
     * @param  list<array{market_id: int, outcome_code: string, stake_points: int, expected_odds_millis: int}>  $lines
     * @return array{order: BetOrder, is_replay: bool}
     */
    public function place(int $uid, int $idemKey, array $lines): array
    {
        if ($uid < 1) {
            throw new RuntimeException('Invalid uid.');
        }
        if ($idemKey < 1) {
            throw new RuntimeException('Idempotency key must be a positive integer.');
        }
        if ($lines === []) {
            throw new RuntimeException('Bet must contain at least one line.');
        }
        if (count($lines) !== 1) {
            throw new RuntimeException('Only single-selection bets are supported in this version.');
        }

        $existingOrder = $this->findExistingByIdemKey($uid, $idemKey);
        if ($existingOrder !== null) {
            return ['order' => $existingOrder, 'is_replay' => true];
        }

        $bookmakerUid = (int) config('bet_agg.points.bookmaker_uid');
        if ($bookmakerUid < 1) {
            throw new RuntimeException('Bookmaker account is not configured (bet_agg.points.bookmaker_uid).');
        }
        if ($bookmakerUid === $uid) {
            throw new RuntimeException('Player cannot use the configured bookmaker account.');
        }

        try {
            return DB::transaction(function () use ($uid, $idemKey, $lines, $bookmakerUid): array {
                $line = $lines[0];
                $marketId = (int) ($line['market_id'] ?? 0);
                $outcomeCode = trim((string) ($line['outcome_code'] ?? ''));
                $stake = (int) ($line['stake_points'] ?? 0);
                $expectedOdds = (int) ($line['expected_odds_millis'] ?? 0);
                if ($marketId < 1 || $outcomeCode === '' || $stake < 1 || $expectedOdds < 1000) {
                    throw new RuntimeException('Invalid bet line.');
                }

                $market = $this->loadAndValidateMarketLeg($marketId, $outcomeCode, $expectedOdds);
                $oddsMillis = $this->synthetic->oddsMillisForOutcome($market, $outcomeCode);
                $potentialReturn = intdiv($stake * $oddsMillis, 1000);
                if ($potentialReturn < 1) {
                    throw new RuntimeException('Potential return rounds down to zero; stake or odds too small.');
                }

                $this->debitUserBalance($uid, $stake);

                $order = $this->insertOrder($uid, $stake, $idemKey);
                $this->insertOrderLine($order, $market, $outcomeCode, $stake, $oddsMillis, $potentialReturn);
                $this->insertStakeFlow($uid, $order->id, $stake);
                $this->pointsAdmin->creditBookmakerAcceptedStake($bookmakerUid, $stake, $order->id);

                return ['order' => $order->load('lines'), 'is_replay' => false];
            });
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                $existing = $this->findExistingByIdemKey($uid, $idemKey);
                if ($existing !== null) {
                    return ['order' => $existing, 'is_replay' => true];
                }
            }
            throw $e;
        }
    }

    private function findExistingByIdemKey(int $uid, int $idemKey): ?BetOrder
    {
        return $this->orders->findWithLinesByUserIdem($uid, $idemKey);
    }

    private function loadAndValidateMarketLeg(int $marketId, string $outcomeCode, int $expectedOddsMillis): Market
    {
        /** @var Market|null $market */
        $market = $this->markets->lockWithGameAndSubjectsForBet($marketId);
        if ($market === null) {
            throw new SelectionNotAcceptingException($marketId, $outcomeCode, 'market not found');
        }

        $game = $market->game;
        if ($game === null) {
            throw new SelectionNotAcceptingException($marketId, $outcomeCode, 'game missing');
        }

        if ($game->status !== Game::STATUS_OPEN) {
            throw new SelectionNotAcceptingException($marketId, $outcomeCode, 'parent game is not open');
        }
        if ($market->status !== Market::STATUS_OPEN) {
            throw new SelectionNotAcceptingException($marketId, $outcomeCode, 'market is not open');
        }

        $odds = $this->synthetic->oddsMillisForOutcome($market, $outcomeCode);
        if ($odds < 1000) {
            throw new SelectionNotAcceptingException($marketId, $outcomeCode, 'odds invalid');
        }
        if ($odds !== $expectedOddsMillis) {
            throw new OddsMovedException($marketId, $outcomeCode, $expectedOddsMillis, $odds);
        }

        return $market;
    }

    private function debitUserBalance(int $uid, int $stake): void
    {
        $balance = $this->balances->findLockedByUid($uid);
        if ($balance === null) {
            $created = new PointsBalance(['uid' => $uid, 'balance' => 0]);
            $created->save();
            $balance = $this->balances->findLockedByUid($uid);
        }
        if ($balance === null) {
            throw new RuntimeException('Points balance row missing.');
        }

        $available = $balance->balance;
        if ($available < $stake) {
            throw new InsufficientPointsException($stake, $available);
        }

        $balance->balance = $available - $stake;
        $balance->save();
    }

    private function insertOrder(int $uid, int $stake, int $idemKey): BetOrder
    {
        $order = new BetOrder([
            'uid' => $uid,
            'idem_key' => $idemKey,
            'status' => BetOrderStatus::Accepted,
            'total_price' => $stake,
            'points_held' => $stake,
        ]);
        $order->save();

        return $order;
    }

    private function insertOrderLine(
        BetOrder $order,
        Market $market,
        string $outcomeCode,
        int $stake,
        int $oddsMillis,
        int $potentialReturn,
    ): void {
        $game = $market->game;
        $labels = $this->synthetic->legsForApi($market, $game);
        $label = $outcomeCode;
        foreach ($labels as $leg) {
            if (($leg['outcome_code'] ?? '') === $outcomeCode) {
                $label = (string) $leg['label'];

                break;
            }
        }

        $snapshot = [
            'market_id' => $market->id,
            'selection' => ['code' => $outcomeCode],
            'game_id' => (int) $game?->id,
            'cms_game_id' => (int) $game?->raw_id,
            'label' => $label,
            'decimal_odds_millis' => $oddsMillis,
        ];

        $line = new BetOrderLine([
            'oid' => $order->id,
            'market_id' => $market->id,
            'selection' => ['code' => $outcomeCode],
            'stake_points' => $stake,
            'odds_snapshot' => $snapshot,
            'decimal_odds_millis' => $oddsMillis,
            'potential_return_points' => $potentialReturn,
            'result' => BetLineResult::Pending,
        ]);
        $line->save();

    }

    private function insertStakeFlow(int $uid, int $orderId, int $stake): void
    {
        $flow = new PointsFlow([
            'uid' => $uid,
            'oid' => $orderId,
            'amount' => -$stake,
            'state' => PointsHoldState::Confirmed,
        ]);
        $flow->save();
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return $e->getCode() === '23000';
    }
}
