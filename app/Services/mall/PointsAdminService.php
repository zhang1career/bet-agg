<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Enums\PointsHoldState;
use App\Models\PointsBalance;
use App\Models\PointsFlow;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PointsAdminService
{
    public function openAccount(int $uid, int $initialBalance = 0): PointsBalance
    {
        if ($uid < 1) {
            throw new RuntimeException('Invalid user id.');
        }
        if ($initialBalance < 0) {
            throw new RuntimeException('Initial balance cannot be negative.');
        }

        return DB::transaction(function () use ($uid, $initialBalance): PointsBalance {
            $exists = PointsBalance::query()->where('uid', $uid)->lockForUpdate()->exists();
            if ($exists) {
                throw new RuntimeException('Points account already exists for this user.');
            }

            $balance = new PointsBalance([
                'uid' => $uid,
                'balance' => $initialBalance,
            ]);
            $balance->save();

            if ($initialBalance > 0) {
                $this->insertLedgerRow($uid, 0, $initialBalance);
            }

            return $balance;
        });
    }

    /** @return array{balance: PointsBalance, flow: PointsFlow} */
    public function adjustBalance(int $uid, int $deltaPoints, int $oid = 0): array
    {
        if ($uid < 1) {
            throw new RuntimeException('Invalid user id.');
        }
        if ($deltaPoints === 0) {
            throw new RuntimeException('Adjustment amount must be non-zero.');
        }
        if ($oid < 0) {
            throw new RuntimeException('Invalid order id.');
        }

        return DB::transaction(function () use ($uid, $deltaPoints, $oid): array {
            $balance = PointsBalance::query()->where('uid', $uid)->lockForUpdate()->first();

            return $this->applyAdjustmentToBalance($balance, $uid, $deltaPoints, $oid);
        });
    }

    /** @return array{balance: PointsBalance, flow: PointsFlow} */
    public function adjustBalanceByBalanceId(int $balanceId, int $deltaPoints, int $oid = 0): array
    {
        if ($balanceId < 1) {
            throw new RuntimeException('Invalid balance id.');
        }
        if ($deltaPoints === 0) {
            throw new RuntimeException('Adjustment amount must be non-zero.');
        }
        if ($oid < 0) {
            throw new RuntimeException('Invalid order id.');
        }

        return DB::transaction(function () use ($balanceId, $deltaPoints, $oid): array {
            $balance = PointsBalance::query()->where('id', $balanceId)->lockForUpdate()->first();
            $uid = $balance === null ? 0 : (int) $balance->uid;

            return $this->applyAdjustmentToBalance($balance, $uid, $deltaPoints, $oid);
        });
    }

    /**
     * @return array{balance: PointsBalance, flow: PointsFlow}
     */
    private function applyAdjustmentToBalance(?PointsBalance $balance, int $uid, int $deltaPoints, int $oid): array
    {
        if ($balance === null) {
            throw new RuntimeException('Points account does not exist. Open an account first.');
        }

        $next = (int) $balance->balance + $deltaPoints;
        if ($next < 0) {
            throw new RuntimeException('Insufficient points for this adjustment.');
        }

        $balance->balance = $next;
        $balance->save();

        $flow = $this->insertLedgerRow($uid, $oid, $deltaPoints);

        return ['balance' => $balance, 'flow' => $flow];
    }

    public function deleteBalanceById(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $row = PointsBalance::query()->where('id', $id)->lockForUpdate()->first();
            if ($row === null) {
                throw new RuntimeException('Account not found.');
            }
            if ((int) $row->balance !== 0) {
                throw new RuntimeException('Balance must be zero before delete.');
            }
            $row->delete();
        });
    }

    public function deleteFlowById(int $id): void
    {
        throw new RuntimeException('Points flow is append-only for audit; deletion is disabled.');
    }

    /**
     * Immutable ledger append (settlement payouts/refunds). Application-layer audit guarantee.
     */
    public function appendImmutableLedger(int $uid, int $deltaPoints, int $oid, PointsHoldState $state): PointsFlow
    {
        if ($uid < 1) {
            throw new RuntimeException('Invalid user id.');
        }
        if ($deltaPoints < 1) {
            throw new RuntimeException('Ledger amount must be positive.');
        }
        if ($oid < 1) {
            throw new RuntimeException('Invalid bet order id.');
        }
        if ($state !== PointsHoldState::SettlementPayout && $state !== PointsHoldState::SettlementRefund) {
            throw new RuntimeException('Invalid ledger state for settlement posting.');
        }

        return DB::transaction(function () use ($uid, $deltaPoints, $oid, $state): PointsFlow {
            $balance = PointsBalance::query()->where('uid', $uid)->lockForUpdate()->first();
            if ($balance === null) {
                throw new RuntimeException('Points account does not exist.');
            }

            $balance->balance = (int) $balance->balance + $deltaPoints;
            $balance->save();

            $flow = new PointsFlow([
                'uid' => $uid,
                'oid' => $oid,
                'amount' => $deltaPoints,
                'state' => $state,
            ]);
            $flow->save();

            return $flow;
        });
    }

    private function insertLedgerRow(int $uid, int $oid, int $amountPoints): PointsFlow
    {
        $flow = new PointsFlow([
            'uid' => $uid,
            'oid' => $oid,
            'amount' => $amountPoints,
            'state' => PointsHoldState::AdminLedger,
        ]);
        $flow->save();

        return $flow;
    }
}
