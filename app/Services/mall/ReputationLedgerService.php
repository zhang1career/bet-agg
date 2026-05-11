<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Enums\ReputationFlowKind;
use App\Models\PointsFlow;
use App\Repos\mall\PointsBalanceRepo;
use Illuminate\Support\Facades\DB;

/**
 * Applies settlement reputation deltas; idempotent per (oid, state) on {@code points_flow}.
 */
final readonly class ReputationLedgerService
{
    public function __construct(private PointsBalanceRepo $profiles) {}

    public function creditWin(int $uid, int $betOrderId): void
    {
        $delta = max(1, (int) config('bet_agg.reputation.delta_win'));
        $this->applyOnce($uid, $betOrderId, $delta, ReputationFlowKind::WinCredit);
    }

    public function debitLoss(int $uid, int $betOrderId): void
    {
        $loss = max(1, (int) config('bet_agg.reputation.delta_lose'));

        $this->applyOnce($uid, $betOrderId, -$loss, ReputationFlowKind::LossDebit);
    }

    private function applyOnce(int $uid, int $betOrderId, int $delta, ReputationFlowKind $kind): void
    {
        DB::transaction(function () use ($uid, $betOrderId, $delta, $kind): void {
            $exists = PointsFlow::query()
                ->where('oid', $betOrderId)
                ->where('state', $kind->value)
                ->exists();
            if ($exists) {
                return;
            }

            $profile = $this->profiles->ensureLockedProfile($uid);
            $profile->balance = (int) $profile->balance + $delta;
            $profile->save();

            $flow = new PointsFlow([
                'uid' => $uid,
                'oid' => $betOrderId,
                'amount' => $delta,
                'state' => $kind,
            ]);
            $flow->save();
        });
    }
}
