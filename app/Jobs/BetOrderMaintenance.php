<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Game;
use App\Services\mall\BetSettlementService;
use Illuminate\Support\Facades\Log;
use Paganini\XxlJobExecutor\Attributes\XxlJob;
use RuntimeException;
use Throwable;

/**
 * XXL-Job handlers for bet order lifecycle maintenance.
 */
final class BetOrderMaintenance
{
    /**
     * Processes every game in {@see Game::STATUS_PENDING_SETTLEMENT}: runs the same batch payout
     * as {@see BetSettlementService::applyGameResult}. Executor params are ignored.
     *
     * @return array{0: bool, 1: array<string, int|string|list<mixed>>|null, 2: string|null}
     */
    #[XxlJob('applyGameSettlement')]
    public static function applyGameSettlement(mixed $executorParams): array
    {
        try {
            $games = Game::query()
                ->where('status', Game::STATUS_PENDING_SETTLEMENT)
                ->orderBy('id')
                ->get();

            if ($games->isEmpty()) {
                return [true, ['games' => []], null];
            }

            $payload = ['games' => []];
            $anyFailure = false;

            foreach ($games as $game) {
                $gid = (int) $game->id;
                try {
                    $result = app(BetSettlementService::class)->applyGameResult($gid);
                    Log::debug('[xxljob] applyGameSettlement', [
                        'game_id' => $gid,
                        'job_id' => $result->jobId,
                        'total' => $result->total,
                        'success' => $result->successCount,
                        'failure' => $result->failureCount,
                    ]);
                    $row = [
                        'game_id' => $gid,
                        'job_id' => $result->jobId,
                        'total' => $result->total,
                        'success_count' => $result->successCount,
                        'failure_count' => $result->failureCount,
                        'status' => $result->status->value,
                    ];
                    $payload['games'][] = $row;
                    if ($result->failureCount > 0) {
                        $anyFailure = true;
                    }
                } catch (RuntimeException $e) {
                    Log::warning('[xxljob] applyGameSettlement failed for game '.$gid.': '.$e->getMessage());
                    $anyFailure = true;
                    $payload['games'][] = [
                        'game_id' => $gid,
                        'error' => $e->getMessage(),
                    ];
                } catch (Throwable $e) {
                    Log::error('[xxljob] applyGameSettlement error for game '.$gid.': '.$e->getMessage());
                    $anyFailure = true;
                    $payload['games'][] = [
                        'game_id' => $gid,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            if ($anyFailure) {
                return [false, $payload, 'One or more games failed settlement.'];
            }

            return [true, $payload, null];
        } catch (Throwable $e) {
            Log::error('[xxljob] applyGameSettlement error: '.$e->getMessage());

            return [false, null, $e->getMessage()];
        }
    }
}
