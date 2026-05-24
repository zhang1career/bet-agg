<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Enums\BetLineResult;
use App\Exceptions\bet\SelectionNotAcceptingException;
use App\Models\BetOrder;
use App\Models\Game;
use App\Models\Market;
use App\Repos\mall\BetOrderRepo;
use App\Repos\mall\MarketRepo;
use App\Repos\mall\OrderItemRepo;
use App\Repos\mall\PointsBalanceRepo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Records a single-outcome selection before settlement (current catalog: no stake or odds on the order line).
 */
final readonly class BetPlaceService
{
    public function __construct(
        private SyntheticMatchMarket $synthetic,
        private BetOrderRepo $orders,
        private OrderItemRepo $orderItems,
        private MarketRepo $markets,
        private PointsBalanceRepo $pointsBalances,
        private MarketQuoteService $marketQuote,
    ) {}

    /**
     * @param  list<array{market_id: int, outcome_code: string}>  $lines
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
        if (count($lines) !== 1) {
            throw new RuntimeException('Only single-outcome selections are supported.');
        }

        $existingOrder = $this->findExistingByIdemKey($uid, $idemKey);
        if ($existingOrder !== null) {
            return ['order' => $existingOrder, 'is_replay' => true];
        }

        try {
            return DB::transaction(function () use ($uid, $idemKey, $lines): array {
                $line = $lines[0];
                $marketId = (int) ($line['market_id'] ?? 0);
                $outcomeCode = trim((string) ($line['outcome_code'] ?? ''));
                if ($marketId < 1 || $outcomeCode === '') {
                    throw new RuntimeException('Invalid bet line.');
                }

                $market = $this->loadAndValidateMarketLeg($marketId, $outcomeCode);
                $game = $market->game;
                if ($game === null) {
                    throw new SelectionNotAcceptingException($marketId, $outcomeCode, 'game missing');
                }

                $this->pointsBalances->ensureLockedProfile($uid);

                $order = $this->orders->createAccepted($uid, $idemKey);
                $this->insertLine($order, $market, $game, $outcomeCode);
                $this->marketQuote->recordPick($marketId, $outcomeCode, (int) $order->ct);

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

    private function loadAndValidateMarketLeg(int $marketId, string $outcomeCode): Market
    {
        /** @var Market|null $market */
        $market = $this->markets->lockWithGameAndSubjectsForPrediction($marketId);
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

        $this->synthetic->assertValidOutcome($market, $outcomeCode);

        return $market;
    }

    private function insertLine(
        BetOrder $order,
        Market $market,
        Game $game,
        string $outcomeCode,
    ): void {
        $labels = $this->synthetic->legsForApi($market, $game);
        $label = $outcomeCode;
        foreach ($labels as $leg) {
            if (($leg['outcome_code'] ?? '') === $outcomeCode) {
                $label = (string) $leg['label'];

                break;
            }
        }

        $this->orderItems->createForOrder(
            (int) $order->id,
            $market->id,
            ['code' => $outcomeCode],
            $label,
            BetLineResult::Pending,
        );
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return $e->getCode() === '23000';
    }
}
