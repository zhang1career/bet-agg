<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\mall\BetSettlementService;
use Illuminate\Support\Facades\Log;
use Paganini\XxlJobExecutor\Attributes\XxlJob;
use Throwable;

/**
 * XXL-Job handlers for bet order lifecycle maintenance.
 */
final class BetOrderMaintenance
{
    /**
     * Processes every game in pending settlement via {@see BetSettlementService::applyPendingSettlements}.
     * Executor params are ignored.
     *
     * @return array{0: bool, 1: array<string, int|string|list<mixed>>|null, 2: string|null}
     */
    #[XxlJob('applyGameSettlement')]
    public static function applyGameSettlement(mixed $executorParams): array
    {
        try {
            $result = app(BetSettlementService::class)->applyPendingSettlements();

            if ($result['games'] === []) {
                return [true, ['games' => []], null];
            }

            if ($result['any_failure']) {
                return [false, ['games' => $result['games']], 'One or more games failed settlement.'];
            }

            return [true, ['games' => $result['games']], null];
        } catch (Throwable $e) {
            Log::error('[xxljob] applyGameSettlement error: '.$e->getMessage());

            return [false, null, $e->getMessage()];
        }
    }
}
