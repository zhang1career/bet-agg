<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Enums\BetOrderStatus;
use App\Models\BetOrder;
use App\Models\Game;
use App\Services\mall\settlement\SettlementBatchItemHandler;
use App\Services\mall\settlement\SettlementBatchPlanProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Paganini\Batch\DTO\BatchRunResult;
use Paganini\Batch\Execution\BatchExecutor;
use RuntimeException;
use Throwable;

/**
 * Big-task / small-task settlement orchestrator.
 *
 * Outer transaction (one per game):
 *   - lock biz_game and mark it + its markets as SETTLED (胜平负 options are synthetic),
 *   - persist {@code winning_outcomes} (synthetic keys e.g. {@code home_win}),
 *   - emit accepted-state orders that need money movement.
 *
 * @see SettlementBatchPlanProvider
 * @see SettlementBatchItemHandler
 */
final readonly class BetSettlementService
{
    public function __construct(
        private BatchExecutor $batchExecutor,
        private SettlementBatchItemHandler $itemHandler,
    ) {}

    /**
     * @param list<string> $winningOutcomeCodes e.g. {@code home_win}, {@code draw}
     * @param list<string> $voidOutcomeCodes legs that refund (e.g. all three for void_all)
     * @throws Throwable
     */
    public function applyGameResult(
        int $gameId,
        array $winningOutcomeCodes,
        array $voidOutcomeCodes = [],
    ): BatchRunResult {
        if ($gameId < 1) {
            throw new RuntimeException('Invalid game_id.');
        }
        $winners = self::normalizeOutcomeCodes($winningOutcomeCodes);
        $voids = self::normalizeOutcomeCodes($voidOutcomeCodes);

        $overlap = array_intersect($winners, $voids);
        if ($overlap !== []) {
            throw new RuntimeException(sprintf(
                'Outcome codes cannot appear in both winners and voids: %s',
                implode(',', $overlap),
            ));
        }

        $bizKey = self::bizKeyForGame($gameId).':'.Game::nowMillis();
        $plan = new SettlementBatchPlanProvider($gameId, $winners, $voids);

        $result = $this->batchExecutor->execute($bizKey, $plan, $this->itemHandler);

        if ($result->failureCount > 0) {
            $this->parkFailedOrdersAsSettlementFailed($result, $gameId);
        }

        return $result;
    }

    public static function bizKeyForGame(int $gameId): string
    {
        return 'settle:game:'.$gameId;
    }

    /**
     * @param  list<string>  $raw
     * @return list<string>
     */
    private static function normalizeOutcomeCodes(array $raw): array
    {
        $out = [];
        foreach ($raw as $v) {
            if (! is_string($v)) {
                continue;
            }
            $t = trim($v);
            if ($t === '') {
                continue;
            }
            $out[$t] = true;
        }

        return array_keys($out);
    }

    private function parkFailedOrdersAsSettlementFailed(BatchRunResult $result, int $gameId): void
    {
        foreach ($result->failures as $failure) {
            $orderId = (int) $failure->ref;
            if ($orderId < 1) {
                continue;
            }

            try {
                DB::transaction(function () use ($orderId): void {
                    /** @var BetOrder|null $order */
                    $order = BetOrder::query()->whereKey($orderId)->lockForUpdate()->first();
                    if ($order === null) {
                        return;
                    }
                    if ($order->status !== BetOrderStatus::Accepted) {
                        return;
                    }
                    $order->status = BetOrderStatus::SettlementFailed;
                    $order->save();
                });
            } catch (Throwable $e) {
                Log::error('[bet-settle] failed to park order in SettlementFailed', [
                    'game_id' => $gameId,
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
