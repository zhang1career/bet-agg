<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\mall\BetSettlementService;
use Illuminate\Support\Facades\Log;
use Paganini\XxlJobExecutor\Attributes\XxlJob;
use RuntimeException;
use Throwable;

/**
 * XXL-Job handlers for bet order lifecycle maintenance.
 *
 * The legacy {@code closeExpiredOrders} handler is gone with the two-step
 * checkout flow: {@code POST /api/bet/place} is single-step atomic so there
 * are no draft orders to time out.
 */
final class BetOrderMaintenance
{
    /**
     * Apply result + settle stakes for {@code biz_game}. {@code executorParams} JSON example:
     * {@code {"game_id":12,"winning_selection_ids":[101,102],"voided_selection_ids":[103]}}.
     *
     * @return array{0: bool, 1: array<string, int|string>|null, 2: string|null}
     */
    #[XxlJob('applyGameSettlement')]
    public static function applyGameSettlement(mixed $executorParams): array
    {
        try {
            $raw = is_string($executorParams) ? $executorParams : (is_scalar($executorParams) ? (string) $executorParams : '');
            $decoded = [];
            $trimmed = trim($raw);
            if ($trimmed !== '') {
                $tmp = json_decode($trimmed, true);
                if (! is_array($tmp)) {
                    return [false, null, 'executorParams must decode to a JSON object.'];
                }
                /** @var array<string, mixed> $decoded */
                $decoded = $tmp;
            }

            $gameId = isset($decoded['game_id']) ? (int) $decoded['game_id'] : 0;
            $winners = self::intList($decoded['winning_selection_ids'] ?? []);
            $voids = self::intList($decoded['voided_selection_ids'] ?? []);

            $result = app(BetSettlementService::class)->applyGameResult($gameId, $winners, $voids);
            Log::debug('[xxljob] applyGameSettlement', [
                'game_id' => $gameId,
                'job_id' => $result->jobId,
                'total' => $result->total,
                'success' => $result->successCount,
                'failure' => $result->failureCount,
            ]);

            $payload = [
                'game_id' => $gameId,
                'job_id' => $result->jobId,
                'total' => $result->total,
                'success_count' => $result->successCount,
                'failure_count' => $result->failureCount,
                'status' => $result->status->value,
            ];

            if ($result->failureCount > 0) {
                return [false, $payload, 'Settlement completed with failures: '.$result->failureCount];
            }

            return [true, $payload, null];
        } catch (RuntimeException $e) {
            Log::warning('[xxljob] applyGameSettlement failed: '.$e->getMessage());

            return [false, null, $e->getMessage()];
        } catch (Throwable $e) {
            Log::error('[xxljob] applyGameSettlement error: '.$e->getMessage());

            return [false, null, $e->getMessage()];
        }
    }

    /**
     * @return list<int>
     */
    private static function intList(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $v) {
            $out[] = (int) $v;
        }

        return $out;
    }
}
