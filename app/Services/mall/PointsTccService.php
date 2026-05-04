<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Enums\PointsHoldState;
use App\Models\PointsBalance;
use App\Models\PointsFlow;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Points try / confirm / cancel hold (TCC) for bet checkout.
 */
final class PointsTccService
{
    /**
     * Available points balance for {@code uid}; missing row yields 0.
     */
    public function availableBalance(int $uid): int
    {
        $row = PointsBalance::query()->where('uid', $uid)->first();
        if ($row === null) {
            return 0;
        }

        return (int) $row->balance;
    }

    public function ensureAccount(int $uid): PointsBalance
    {
        $row = PointsBalance::query()->where('uid', $uid)->first();
        if ($row !== null) {
            return $row;
        }

        $row = new PointsBalance(['uid' => $uid, 'balance' => 0]);
        $row->save();

        return $row;
    }

    /**
     * Move amount from available balance into hold, keyed by bet order id (points_flow.oid).
     */
    public function tryFreeze(int $uid, int $points, int $betOrderId): void
    {
        if ($betOrderId < 1) {
            throw new RuntimeException('Bet order id is required for points hold.');
        }
        if ($points < 1) {
            throw new RuntimeException('Points amount must be positive.');
        }

        DB::transaction(function () use ($uid, $points, $betOrderId): void {
            $existing = PointsFlow::query()
                ->where('uid', $uid)
                ->where('oid', $betOrderId)
                ->where('state', PointsHoldState::TrySucceeded)
                ->first();
            if ($existing !== null) {
                if ((int) $existing->amount === $points) {
                    return;
                }
                throw new RuntimeException('Existing points hold for this order with a different amount.');
            }

            $balance = PointsBalance::query()->where('uid', $uid)->lockForUpdate()->first();
            if ($balance === null) {
                PointsBalance::query()->create([
                    'uid' => $uid,
                    'balance' => 0,
                ]);
                $balance = PointsBalance::query()->where('uid', $uid)->lockForUpdate()->first();
            }
            if ($balance === null) {
                throw new RuntimeException('Points balance missing.');
            }
            if ($balance->balance < $points) {
                throw new RuntimeException('Insufficient points.');
            }

            $balance->balance -= $points;
            $balance->save();

            $hold = new PointsFlow([
                'uid' => $uid,
                'oid' => $betOrderId,
                'amount' => $points,
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

                $balance = PointsBalance::query()->where('uid', $hold->uid)->lockForUpdate()->first();
                if ($balance === null) {
                    throw new RuntimeException('Points balance missing.');
                }
                $balance->balance += $hold->amount;
                $balance->save();

                $hold->state = PointsHoldState::RolledBack;
                $hold->save();
            }
        });
    }
}
