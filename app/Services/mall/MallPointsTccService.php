<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Enums\PointsHoldState;
use App\Models\MallPointsBalance;
use App\Models\PointsFlow;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class MallPointsTccService
{
    /**
     * Available points for the user (minor units); no account row yields 0.
     */
    public function availableBalanceMinor(int $uid): int
    {
        $row = MallPointsBalance::query()->where('uid', $uid)->first();
        if ($row === null) {
            return 0;
        }

        return (int) $row->balance_minor;
    }

    public function ensureAccount(int $uid): MallPointsBalance
    {
        $row = MallPointsBalance::query()->where('uid', $uid)->first();
        if ($row !== null) {
            return $row;
        }

        $row = new MallPointsBalance(['uid' => $uid, 'balance_minor' => 0]);
        $row->save();

        return $row;
    }

    /**
     * Move amount from available balance into hold, keyed by bet order id (points_flow.oid).
     */
    public function tryFreeze(int $uid, int $amountMinor, int $betOrderId): void
    {
        if ($betOrderId < 1) {
            throw new RuntimeException('Bet order id is required for points hold.');
        }
        if ($amountMinor < 1) {
            throw new RuntimeException('Points amount must be positive.');
        }

        DB::transaction(function () use ($uid, $amountMinor, $betOrderId): void {
            $existing = PointsFlow::query()
                ->where('uid', $uid)
                ->where('oid', $betOrderId)
                ->where('state', PointsHoldState::TrySucceeded)
                ->first();
            if ($existing !== null) {
                if ((int) $existing->amount_minor === $amountMinor) {
                    return;
                }
                throw new RuntimeException('Existing points hold for this order with a different amount.');
            }

            $balance = MallPointsBalance::query()->where('uid', $uid)->lockForUpdate()->first();
            if ($balance === null) {
                MallPointsBalance::query()->create([
                    'uid' => $uid,
                    'balance_minor' => 0,
                ]);
                $balance = MallPointsBalance::query()->where('uid', $uid)->lockForUpdate()->first();
            }
            if ($balance === null) {
                throw new RuntimeException('Points balance missing.');
            }
            if ($balance->balance_minor < $amountMinor) {
                throw new RuntimeException('Insufficient points.');
            }

            $balance->balance_minor -= $amountMinor;
            $balance->save();

            $hold = new PointsFlow([
                'uid' => $uid,
                'oid' => $betOrderId,
                'amount_minor' => $amountMinor,
                'state' => PointsHoldState::TrySucceeded,
            ]);
            $hold->save();
        });
    }

    public function confirmHoldForBetOrder(int $betOrderId): void
    {
        if ($betOrderId < 1) {
            return;
        }

        DB::transaction(function () use ($betOrderId): void {
            $holds = PointsFlow::query()
                ->where('oid', $betOrderId)
                ->where('state', PointsHoldState::TrySucceeded)
                ->lockForUpdate()
                ->orderBy('id')
                ->get();
            foreach ($holds as $hold) {
                if ($hold->state === PointsHoldState::Confirmed) {
                    continue;
                }
                if ($hold->state !== PointsHoldState::TrySucceeded) {
                    throw new RuntimeException('Points hold not in try-succeeded state.');
                }
                $hold->state = PointsHoldState::Confirmed;
                $hold->save();
            }
        });
    }

    public function cancelHoldForBetOrder(int $betOrderId): void
    {
        if ($betOrderId < 1) {
            return;
        }

        DB::transaction(function () use ($betOrderId): void {
            $holds = PointsFlow::query()
                ->where('oid', $betOrderId)
                ->where('state', PointsHoldState::TrySucceeded)
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            foreach ($holds as $hold) {
                if ($hold->state === PointsHoldState::RolledBack) {
                    continue;
                }
                if ($hold->state === PointsHoldState::Confirmed) {
                    throw new RuntimeException('Cannot cancel confirmed hold.');
                }
                if ($hold->state !== PointsHoldState::TrySucceeded) {
                    throw new RuntimeException('Points hold not in try-succeeded state.');
                }

                $balance = MallPointsBalance::query()->where('uid', $hold->uid)->lockForUpdate()->first();
                if ($balance === null) {
                    throw new RuntimeException('Points balance missing.');
                }
                $balance->balance_minor += $hold->amount_minor;
                $balance->save();

                $hold->state = PointsHoldState::RolledBack;
                $hold->save();
            }
        });
    }
}
