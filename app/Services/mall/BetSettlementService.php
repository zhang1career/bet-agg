<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Enums\BetOrderStatus;
use App\Models\BetOrder;
use App\Models\Game;
use App\Repos\mall\BetOrderRepo;
use App\Repos\mall\GameRepo;
use App\Repos\mall\MarketRepo;
use App\Services\mall\settlement\SettlementBatchItemHandler;
use App\Services\mall\settlement\SettlementBatchPlanProvider;
use App\Support\SettlementBizKey;
use App\Support\SettleOutcomes;
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
 *   - persist {@code settle_outcomes} ({@code winners} / {@code voids}),
 *   - emit accepted-state predictions that need scoring.
 *
 * @see SettlementBatchPlanProvider
 * @see SettlementBatchItemHandler
 */
final readonly class BetSettlementService
{
    public function __construct(
        private BatchExecutor $batchExecutor,
        private SettlementBatchItemHandler $itemHandler,
        private GameRepo $games,
        private MarketRepo $markets,
        private BetOrderRepo $orders,
    ) {}

    /**
     * Operator submits the sports result: persist {@see SettleOutcomes} on the game only,
     * set game status to pending settlement. The scheduler calls {@see applyGameResult}.
     *
     * @param  list<string>  $winningOutcomeCodes  e.g. {@code home_win}, {@code draw}
     * @param  list<string>  $voidOutcomeCodes  legs that refund (e.g. all three for void_all)
     */
    public function recordPendingSettlement(
        int $gameId,
        array $winningOutcomeCodes,
        array $voidOutcomeCodes = [],
    ): void {
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

        DB::transaction(function () use ($gameId, $winners, $voids): void {
            /** @var Game|null $game */
            $game = $this->games->lockForUpdate($gameId);
            if ($game === null) {
                throw new RuntimeException('Game not found.');
            }
            if ((int) $game->status !== Game::STATUS_OPEN) {
                throw new RuntimeException('Only open games can receive a settlement result.');
            }

            $now = Game::nowMillis();
            $this->games->markPendingSettlement($game, SettleOutcomes::pack($winners, $voids), $now);
        });
    }

    /**
     * Scheduler entry: run payout batch for every game pending settlement.
     *
     * @return array{
     *     games: list<array<string, mixed>>,
     *     any_failure: bool
     * }
     */
    public function applyPendingSettlements(): array
    {
        $games = $this->games->listPendingSettlement();

        if ($games->isEmpty()) {
            return ['games' => [], 'any_failure' => false];
        }

        $payload = ['games' => []];
        $anyFailure = false;

        foreach ($games as $game) {
            $gid = (int) $game->id;
            try {
                $result = $this->applyGameResult($gid);
                Log::debug('[bet-settle] applyPendingSettlements', [
                    'game_id' => $gid,
                    'job_id' => $result->jobId,
                    'total' => $result->total,
                    'success' => $result->successCount,
                    'failure' => $result->failureCount,
                ]);
                $payload['games'][] = [
                    'game_id' => $gid,
                    'job_id' => $result->jobId,
                    'total' => $result->total,
                    'success_count' => $result->successCount,
                    'failure_count' => $result->failureCount,
                    'status' => $result->status->value,
                ];
                if ($result->failureCount > 0) {
                    $anyFailure = true;
                }
            } catch (RuntimeException $e) {
                Log::warning('[bet-settle] applyPendingSettlements failed for game '.$gid.': '.$e->getMessage());
                $anyFailure = true;
                $payload['games'][] = [
                    'game_id' => $gid,
                    'error' => $e->getMessage(),
                ];
            } catch (Throwable $e) {
                Log::error('[bet-settle] applyPendingSettlements error for game '.$gid.': '.$e->getMessage());
                $anyFailure = true;
                $payload['games'][] = [
                    'game_id' => $gid,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return ['games' => $payload['games'], 'any_failure' => $anyFailure];
    }

    /**
     * Run payout batch for one game. Reads winners/voids from {@see Game::$settle_outcomes} when
     * pending or settled (retry).
     *
     * @throws Throwable
     */
    public function applyGameResult(int $gameId): BatchRunResult
    {
        if ($gameId < 1) {
            throw new RuntimeException('Invalid game_id.');
        }

        $bizKey = self::bizKeyForGame($gameId).':'.Game::nowMillis();
        $plan = new SettlementBatchPlanProvider(
            $gameId,
            $this->games,
            $this->markets,
            $this->orders,
        );

        $result = $this->batchExecutor->execute($bizKey, $plan, $this->itemHandler);

        if ($result->failureCount > 0) {
            $this->parkFailedOrdersAsSettlementFailed($result, $gameId);
        }

        return $result;
    }

    public static function bizKeyForGame(int $gameId): string
    {
        return SettlementBizKey::prefixForGame($gameId);
    }

    /**
     * Inverse of {@see bizKeyForGame} for persisted batch rows ({@code biz_key} may include a millis suffix).
     */
    public static function gameIdFromSettleBizKey(string $bizKey): ?int
    {
        return SettlementBizKey::gameIdFromBizKey($bizKey);
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
                    $order = $this->orders->findLocked($orderId);
                    if ($order === null) {
                        return;
                    }
                    if ($order->status !== BetOrderStatus::Accepted) {
                        return;
                    }
                    $this->orders->saveStatus($order, BetOrderStatus::SettlementFailed);
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
