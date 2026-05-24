<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Enums\PointsFlowKind;
use App\Repos\mall\PointsBalanceRepo;
use App\Repos\mall\PointsFlowRepo;
use Illuminate\Support\Facades\DB;

/**
 * Applies settlement score deltas on {@code points_balance} / {@code points_flow}; idempotent per (oid, state).
 */
final readonly class PointsLedgerService
{
    public function __construct(
        private PointsBalanceRepo $profiles,
        private PointsFlowRepo $flows,
    ) {}

    public function creditWin(int $uid, int $betOrderId): void
    {
        $delta = max(1, (int) config('bet_agg.points.delta_win'));
        $this->applyOnce($uid, $betOrderId, $delta, PointsFlowKind::WinCredit);
    }

    public function debitLoss(int $uid, int $betOrderId): void
    {
        $loss = max(1, (int) config('bet_agg.points.delta_lose'));

        $this->applyOnce($uid, $betOrderId, -$loss, PointsFlowKind::LossDebit);
    }

    private function applyOnce(int $uid, int $betOrderId, int $delta, PointsFlowKind $kind): void
    {
        DB::transaction(function () use ($uid, $betOrderId, $delta, $kind): void {
            if ($this->flows->existsForOrderAndState($betOrderId, $kind)) {
                return;
            }

            $profile = $this->profiles->ensureLockedProfile($uid);
            $this->profiles->addToBalance($profile, $delta);
            $this->flows->create($uid, $betOrderId, $delta, $kind);
        });
    }
}
