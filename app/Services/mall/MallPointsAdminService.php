<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Enums\PointsHoldState;
use App\Models\MallPointsBalance;
use App\Models\PointsFlow;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class MallPointsAdminService
{
    public function openAccount(int $uid, int $initialBalanceMinor = 0): MallPointsBalance
    {
        if ($uid < 1) {
            throw new RuntimeException('Invalid user id.');
        }
        if ($initialBalanceMinor < 0) {
            throw new RuntimeException('Initial balance cannot be negative.');
        }

        return DB::transaction(function () use ($uid, $initialBalanceMinor): MallPointsBalance {
            $exists = MallPointsBalance::query()->where('uid', $uid)->lockForUpdate()->exists();
            if ($exists) {
                throw new RuntimeException('Points account already exists for this user.');
            }

            $balance = new MallPointsBalance([
                'uid' => $uid,
                'balance_minor' => $initialBalanceMinor,
            ]);
            $balance->save();

            if ($initialBalanceMinor > 0) {
                $this->insertLedgerRow($uid, 0, $initialBalanceMinor);
            }

            return $balance;
        });
    }

    /** @return array{balance: MallPointsBalance, flow: PointsFlow} */
    public function adjustBalance(int $uid, int $deltaMinor, int $oid = 0): array
    {
        if ($uid < 1) {
            throw new RuntimeException('Invalid user id.');
        }
        if ($deltaMinor === 0) {
            throw new RuntimeException('Adjustment amount must be non-zero.');
        }
        if ($oid < 0) {
            throw new RuntimeException('Invalid order id.');
        }

        return DB::transaction(function () use ($uid, $deltaMinor, $oid): array {
            $balance = MallPointsBalance::query()->where('uid', $uid)->lockForUpdate()->first();

            return $this->applyAdjustmentToBalance($balance, $uid, $deltaMinor, $oid);
        });
    }

    /** @return array{balance: MallPointsBalance, flow: PointsFlow} */
    public function adjustBalanceByBalanceId(int $balanceId, int $deltaMinor, int $oid = 0): array
    {
        if ($balanceId < 1) {
            throw new RuntimeException('Invalid balance id.');
        }
        if ($deltaMinor === 0) {
            throw new RuntimeException('Adjustment amount must be non-zero.');
        }
        if ($oid < 0) {
            throw new RuntimeException('Invalid order id.');
        }

        return DB::transaction(function () use ($balanceId, $deltaMinor, $oid): array {
            $balance = MallPointsBalance::query()->where('id', $balanceId)->lockForUpdate()->first();
            $uid = $balance === null ? 0 : (int) $balance->uid;

            return $this->applyAdjustmentToBalance($balance, $uid, $deltaMinor, $oid);
        });
    }

    /**
     * @return array{balance: MallPointsBalance, flow: PointsFlow}
     */
    private function applyAdjustmentToBalance(?MallPointsBalance $balance, int $uid, int $deltaMinor, int $oid): array
    {
        if ($balance === null) {
            throw new RuntimeException('Points account does not exist. Open an account first.');
        }

        $next = (int) $balance->balance_minor + $deltaMinor;
        if ($next < 0) {
            throw new RuntimeException('Insufficient points for this adjustment.');
        }

        $balance->balance_minor = $next;
        $balance->save();

        $flow = $this->insertLedgerRow($uid, $oid, $deltaMinor);

        return ['balance' => $balance, 'flow' => $flow];
    }

    public function deleteBalanceById(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $row = MallPointsBalance::query()->where('id', $id)->lockForUpdate()->first();
            if ($row === null) {
                throw new RuntimeException('Account not found.');
            }
            if ((int) $row->balance_minor !== 0) {
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
    public function appendImmutableLedger(int $uid, int $deltaMinor, int $oid, PointsHoldState $state): PointsFlow
    {
        if ($uid < 1) {
            throw new RuntimeException('Invalid user id.');
        }
        if ($deltaMinor < 1) {
            throw new RuntimeException('Ledger amount must be positive.');
        }
        if ($oid < 1) {
            throw new RuntimeException('Invalid bet order id.');
        }
        if ($state !== PointsHoldState::SettlementPayout && $state !== PointsHoldState::SettlementRefund) {
            throw new RuntimeException('Invalid ledger state for settlement posting.');
        }

        return DB::transaction(function () use ($uid, $deltaMinor, $oid, $state): PointsFlow {
            $balance = MallPointsBalance::query()->where('uid', $uid)->lockForUpdate()->first();
            if ($balance === null) {
                throw new RuntimeException('Points account does not exist.');
            }

            $balance->balance_minor = (int) $balance->balance_minor + $deltaMinor;
            $balance->save();

            $flow = new PointsFlow([
                'uid' => $uid,
                'oid' => $oid,
                'amount_minor' => $deltaMinor,
                'state' => $state,
            ]);
            $flow->save();

            return $flow;
        });
    }

    private function insertLedgerRow(int $uid, int $oid, int $amountMinor): PointsFlow
    {
        $flow = new PointsFlow([
            'uid' => $uid,
            'oid' => $oid,
            'amount_minor' => $amountMinor,
            'state' => PointsHoldState::AdminLedger,
        ]);
        $flow->save();

        return $flow;
    }
}
