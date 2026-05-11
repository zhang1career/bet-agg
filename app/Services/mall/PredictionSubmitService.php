<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Enums\BetLineResult;
use App\Enums\BetOrderStatus;
use App\Exceptions\bet\SelectionNotAcceptingException;
use App\Models\BetOrder;
use App\Models\Game;
use App\Models\Market;
use App\Models\OrderItem;
use App\Repos\mall\BetOrderRepo;
use App\Repos\mall\MarketRepo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Records a single-outcome prediction before settlement; no stake or odds.
 */
final readonly class PredictionSubmitService
{
    public function __construct(
        private SyntheticMatchMarket $synthetic,
        private BetOrderRepo $orders,
        private MarketRepo $markets,
    ) {}

    /**
     * @param  list<array{market_id: int, outcome_code: string}>  $lines
     * @return array{order: BetOrder, is_replay: bool}
     */
    public function submit(int $uid, int $idemKey, array $lines): array
    {
        if ($uid < 1) {
            throw new RuntimeException('Invalid uid.');
        }
        if ($idemKey < 1) {
            throw new RuntimeException('Idempotency key must be a positive integer.');
        }
        if (count($lines) !== 1) {
            throw new RuntimeException('Only single-outcome predictions are supported.');
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
                    throw new RuntimeException('Invalid prediction line.');
                }

                $market = $this->loadAndValidateMarketLeg($marketId, $outcomeCode);
                $game = $market->game;
                if ($game === null) {
                    throw new SelectionNotAcceptingException($marketId, $outcomeCode, 'game missing');
                }

                $order = $this->insertOrder($uid, $idemKey);
                $this->insertLine($order, $market, $game, $outcomeCode);

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

    private function insertOrder(int $uid, int $idemKey): BetOrder
    {
        $order = new BetOrder([
            'uid' => $uid,
            'idem_key' => $idemKey,
            'status' => BetOrderStatus::Accepted,
        ]);
        $order->save();

        return $order;
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

        $line = new OrderItem([
            'oid' => $order->id,
            'mid' => $market->id,
            'selection' => ['code' => $outcomeCode],
            'pick_label' => $label,
            'result' => BetLineResult::Pending,
        ]);
        $line->save();
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return $e->getCode() === '23000';
    }
}
