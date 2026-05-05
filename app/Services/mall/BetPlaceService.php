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
use App\Models\PointsBalance;
use App\Models\PointsFlow;
use App\Models\SportGame;
use App\Models\SportMarket;
use App\Models\SportSelection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Atomic single-step bet placement.
 *
 * One database transaction per request:
 *   1. validate selections / odds (re-read with {@code lockForUpdate} so prices
 *      cannot drift mid-flight),
 *   2. lock and debit user points balance, throwing
 *      {@see InsufficientPointsException} on shortfall,
 *   3. insert {@code bet_order} (status = Accepted, {@code idem_key} set) and
 *      {@code order_item} lines — {@code UNIQUE(uid, idem_key)} prevents double-spend on
 *      concurrent retries with the same snowflake key,
 *   4. append stake {@code points_flow} and credit bookmaker liquidity.
 *
 * Idempotency contract: if {@code (uid, idemKey)} already exists, the prior
 * order is returned and {@code isReplay = true}. Two different keys for the
 * same selection are treated as two separate bets (intentional).
 */
final readonly class BetPlaceService
{
    public function __construct(
        private PointsAdminService $pointsAdmin,
    ) {}

    /**
     * @param  list<array{kid: int, stake_points: int, expected_odds_millis: int}>  $lines
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
            // Single-line bets only (current parity with prior implementation; multi-line parlay is a
            // separate roadmap item — additive, doesn't affect this contract).
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
                $kid = (int) ($line['kid'] ?? 0);
                $stake = (int) ($line['stake_points'] ?? 0);
                $expectedOdds = (int) ($line['expected_odds_millis'] ?? 0);
                if ($kid < 1 || $stake < 1 || $expectedOdds < 1) {
                    throw new RuntimeException('Invalid bet line.');
                }

                $selection = $this->loadAndValidateSelection($kid, $expectedOdds);
                $oddsMillis = (int) $selection->current_odds_millis;
                $potentialReturn = intdiv($stake * $oddsMillis, 1000);
                if ($potentialReturn < 1) {
                    throw new RuntimeException('Potential return rounds down to zero; stake or odds too small.');
                }

                $this->debitUserBalance($uid, $stake);

                $order = $this->insertOrder($uid, $stake, $idemKey);
                $this->insertOrderLine($order, $selection, $stake, $oddsMillis, $potentialReturn);
                $this->insertStakeFlow($uid, (int) $order->id, $stake);
                $this->pointsAdmin->creditBookmakerAcceptedStake($bookmakerUid, $stake, (int) $order->id);

                return ['order' => $order->load('lines'), 'is_replay' => false];
            });
        } catch (QueryException $e) {
            // Concurrent retry with the same snowflake key won the race: the second {@code bet_order}
            // insert violates UNIQUE(uid, idem_key) and rolls back the whole transaction.
            // Re-fetch and return the winning order so the agent observes a single result.
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
        return BetOrder::query()
            ->with('lines')
            ->where('uid', $uid)
            ->where('idem_key', $idemKey)
            ->first();
    }

    private function loadAndValidateSelection(int $kid, int $expectedOddsMillis): SportSelection
    {
        $selection = SportSelection::query()
            ->with(['market.game'])
            ->whereKey($kid)
            ->lockForUpdate()
            ->first();
        if ($selection === null) {
            throw new SelectionNotAcceptingException($kid, 'not found');
        }

        $market = $selection->market;
        if ($market === null) {
            throw new SelectionNotAcceptingException($kid, 'market missing');
        }
        $game = $market->game;
        if ($game === null) {
            throw new SelectionNotAcceptingException($kid, 'game missing');
        }

        if ($game->status !== SportGame::STATUS_OPEN) {
            throw new SelectionNotAcceptingException($kid, 'parent game is not open');
        }
        if ($market->status !== SportMarket::STATUS_OPEN) {
            throw new SelectionNotAcceptingException($kid, 'market is not open');
        }
        if ($selection->status !== SportSelection::STATUS_OPEN) {
            throw new SelectionNotAcceptingException($kid, 'selection is not open');
        }

        $odds = (int) $selection->current_odds_millis;
        if ($odds < 1000) {
            throw new SelectionNotAcceptingException($kid, 'odds invalid');
        }
        if ($odds !== $expectedOddsMillis) {
            throw new OddsMovedException($kid, $expectedOddsMillis, $odds);
        }

        return $selection;
    }

    private function debitUserBalance(int $uid, int $stake): void
    {
        $balance = PointsBalance::query()->where('uid', $uid)->lockForUpdate()->first();
        if ($balance === null) {
            $created = new PointsBalance(['uid' => $uid, 'balance' => 0]);
            $created->save();
            $balance = PointsBalance::query()->where('uid', $uid)->lockForUpdate()->first();
        }
        if ($balance === null) {
            throw new RuntimeException('Points balance row missing.');
        }

        $available = (int) $balance->balance;
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
        SportSelection $selection,
        int $stake,
        int $oddsMillis,
        int $potentialReturn,
    ): BetOrderLine {
        $market = $selection->market;
        $game = $market?->game;

        $snapshot = [
            'kid' => (int) $selection->id,
            'market_id' => $market !== null ? (int) $market->id : 0,
            'game_id' => $game !== null ? (int) $game->id : 0,
            'cms_game_id' => $game !== null ? (int) $game->raw_id : 0,
            'game_name' => '',
            'label' => (string) $selection->label,
            'decimal_odds_millis' => $oddsMillis,
        ];

        $line = new BetOrderLine([
            'oid' => (int) $order->id,
            'kid' => (int) $selection->id,
            'stake_points' => $stake,
            'odds_snapshot' => $snapshot,
            'decimal_odds_millis' => $oddsMillis,
            'potential_return_points' => $potentialReturn,
            'result' => BetLineResult::Pending,
        ]);
        $line->save();

        return $line;
    }

    private function insertStakeFlow(int $uid, int $orderId, int $stake): void
    {
        $flow = new PointsFlow([
            'uid' => $uid,
            'oid' => $orderId,
            'amount' => -$stake,
            // {@code Confirmed} replaces the legacy TCC TrySucceeded → Confirmed pair; the single-step
            // place flow no longer has a separate confirmation phase.
            'state' => PointsHoldState::Confirmed,
        ]);
        $flow->save();
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        // MySQL: 23000 / errno 1062. SQLite (tests): 23000 / errno 19.
        return $e->getCode() === '23000';
    }
}
