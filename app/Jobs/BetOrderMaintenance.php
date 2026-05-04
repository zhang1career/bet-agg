<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\mall\BetOverdueOrderSweepService;
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
     * @return array{0: bool, 1: array<string, int>|null, 2: string|null}
     */
    #[XxlJob('closeExpiredOrders')]
    public static function closeExpiredOrders(mixed $_executorParams): array
    {
        try {
            $stats = app(BetOverdueOrderSweepService::class)->sweepExpired();
            Log::debug('[xxljob] closeExpiredOrders', $stats);

            return [true, $stats, null];
        } catch (Throwable $e) {
            Log::error('[xxljob] closeExpiredOrders failed: '.$e->getMessage());

            return [false, null, $e->getMessage()];
        }
    }

    /**
     * Apply result + settle stakes for {@code biz_game}. {@code executorParams} JSON example:
     * {@code {"game_id":12,"winning_selection_ids":[101,102]}}.
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
            /** @var mixed $winnerRaw */
            $winnerRaw = $decoded['winning_selection_ids'] ?? [];
            if (! is_array($winnerRaw)) {
                return [false, null, 'winning_selection_ids must be an array.'];
            }

            $winners = [];
            foreach ($winnerRaw as $v) {
                $winners[] = is_int($v) ? $v : (int) $v;
            }

            $game = app(BetSettlementService::class)->applyGameResult($gameId, $winners);
            Log::debug('[xxljob] applyGameSettlement', ['game_id' => $game->id, 'winner_count' => count($winners)]);

            return [true, ['game_id' => $game->id, 'status' => $game->status], null];
        } catch (RuntimeException $e) {
            Log::warning('[xxljob] applyGameSettlement failed: '.$e->getMessage());

            return [false, null, $e->getMessage()];
        } catch (Throwable $e) {
            Log::error('[xxljob] applyGameSettlement error: '.$e->getMessage());

            return [false, null, $e->getMessage()];
        }
    }
}
