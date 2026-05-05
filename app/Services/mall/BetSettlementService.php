<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Enums\BetOrderStatus;
use App\Models\BetOrder;
use App\Models\SportGame;
use App\Services\mall\settlement\SettlementBatchItemHandler;
use App\Services\mall\settlement\SettlementBatchPlanProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Paganini\Batch\DTO\BatchRunResult;
use Paganini\Batch\Execution\BatchExecutor;
use RuntimeException;

/**
 * Big-task / small-task settlement orchestrator.
 *
 * Outer transaction (one per game):
 *   - lock biz_game and mark it + its markets + selections as SETTLED,
 *   - persist {@code winning_selection_ids},
 *   - emit the list of accepted-state orders that need money movement.
 * The outer transaction commits BEFORE any per-order work runs, so a crash
 * mid-settlement leaves the game irreversibly settled and the per-order
 * inner transactions can be retried independently (paganini\batch tracks
 * per-order outcomes on {@code settle_job}).
 *
 * Inner transactions (one per order):
 *   - delegated to {@see SettlementBatchItemHandler}, each is independent.
 *   - on rollback the order stays {@code Accepted} on the first attempt; this
 *     orchestrator then marks it {@code SettlementFailed} in a separate
 *     transaction so it surfaces to operators.
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
     * @param  list<int>  $winningSelectionIds
     * @param  list<int>  $voidedSelectionIds  Selections whose bets refund the stake (e.g. match abandoned, market settled void).
     */
    public function applyGameResult(
        int $gameId,
        array $winningSelectionIds,
        array $voidedSelectionIds = [],
    ): BatchRunResult {
        if ($gameId < 1) {
            throw new RuntimeException('Invalid game_id.');
        }
        $winners = $this->normalizeIds($winningSelectionIds);
        $voids = $this->normalizeIds($voidedSelectionIds);

        $overlap = array_intersect($winners, $voids);
        if ($overlap !== []) {
            throw new RuntimeException(sprintf(
                'Selection ids cannot appear in both winners and voids: %s',
                implode(',', $overlap),
            ));
        }

        // One settle_job row per attempt: we suffix the millis-precision attempt timestamp
        // so retries (e.g. after topping up bookmaker liquidity for SettlementFailed orders) get
        // their own audit row. Once paganini\batch grows native resume support (project todo 9)
        // this can collapse back to a per-game bizKey that the executor resumes by cursor.
        $bizKey = self::bizKeyForGame($gameId).':'.SportGame::nowMillis();
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
     * @param  list<int|string>  $rawIds
     * @return list<int>
     */
    private function normalizeIds(array $rawIds): array
    {
        $ids = array_map(static fn (mixed $v): int => (int) $v, $rawIds);
        $ids = array_filter($ids, static fn (int $v): bool => $v > 0);

        return array_values(array_unique($ids));
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
                        // Already terminal (Won/Lost/Void) or already SettlementFailed — leave as-is.
                        return;
                    }
                    $order->status = BetOrderStatus::SettlementFailed;
                    $order->save();
                });
            } catch (\Throwable $e) {
                Log::error('[bet-settle] failed to park order in SettlementFailed', [
                    'game_id' => $gameId,
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Convenience accessor used by call sites that previously expected a {@see SportGame}
     * back from {@code applyGameResult}. Returns the freshly-settled row (or {@code null}
     * if the game was deleted in the meantime, which should not happen).
     */
    public function loadGame(int $gameId): ?SportGame
    {
        return SportGame::query()->whereKey($gameId)->first();
    }
}
