<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Enums\PointsHoldState;
use App\Models\PointsBalance;
use App\Models\PointsFlow;
use App\Repos\mall\PointsBalanceRepo;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class PointsAdminService
{
    public function __construct(
        private PointsBalanceRepo $balances,
    ) {}

    /**
     * Available points balance for {@code uid}; missing row yields 0.
     */
    public function availableBalance(int $uid): int
    {
        $row = $this->balances->findByUid($uid);
        if ($row === null) {
            return 0;
        }

        return $row->balance;
    }

    public function openAccount(int $uid, int $initialBalance = 0): PointsBalance
    {
        if ($uid < 1) {
            throw new RuntimeException('Invalid user id.');
        }
        if ($initialBalance < 0) {
            throw new RuntimeException('Initial balance cannot be negative.');
        }

        return DB::transaction(function () use ($uid, $initialBalance): PointsBalance {
            $exists = $this->balances->existsLockedByUid($uid);
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
            $balance = $this->balances->findLockedByUid($uid);

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

        $next = $balance->balance + $deltaPoints;
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
            $row = $this->balances->findLockedById($id);
            if ($row === null) {
                throw new RuntimeException('Account not found.');
            }
            if ($row->balance !== 0) {
                throw new RuntimeException('Balance must be zero before delete.');
            }
            $row->delete();
        });
    }

    /**
     * Locks bookmaker liquidity and pays a winning bet snapshot amount (deposit × decimal odds stored on the line).
     *
     * @return array{user_flow: PointsFlow, book_flow: PointsFlow}
     */
    public function payoutBetWinFromBookmaker(int $bookUid, int $winnerUid, int $payoutPoints, int $betOrderId): array
    {
        if ($bookUid < 1 || $winnerUid < 1) {
            throw new RuntimeException('Invalid user id.');
        }
        if ($bookUid === $winnerUid) {
            throw new RuntimeException('Bookmaker uid cannot match winner uid.');
        }
        if ($payoutPoints < 1) {
            throw new RuntimeException('Payout amount must be positive.');
        }
        if ($betOrderId < 1) {
            throw new RuntimeException('Invalid bet order id.');
        }

        return DB::transaction(function () use ($bookUid, $winnerUid, $payoutPoints, $betOrderId): array {
            return $this->transferFromBookmakerToUser(
                $bookUid,
                $winnerUid,
                $payoutPoints,
                $betOrderId,
                PointsHoldState::BookPayoutDebit,
                PointsHoldState::SettlementPayout,
                'Insufficient bookmaker liquidity for this payout.',
            );
        });
    }

    /**
     * Refunds a void bet's stake from the bookmaker pool back to the user. Mirrors
     * {@see payoutBetWinFromBookmaker} but uses the {@see PointsHoldState::BookStakeRefund} /
     * {@see PointsHoldState::SettlementRefund} pair so audit and settlement filters can
     * distinguish "void refund" from "win payout".
     *
     * @return array{user_flow: PointsFlow, book_flow: PointsFlow}
     */
    public function refundBetStakeFromBookmaker(int $bookUid, int $userUid, int $stakePoints, int $betOrderId): array
    {
        if ($bookUid < 1 || $userUid < 1) {
            throw new RuntimeException('Invalid user id.');
        }
        if ($bookUid === $userUid) {
            throw new RuntimeException('Bookmaker uid cannot match user uid.');
        }
        if ($stakePoints < 1) {
            throw new RuntimeException('Refund amount must be positive.');
        }
        if ($betOrderId < 1) {
            throw new RuntimeException('Invalid bet order id.');
        }

        return DB::transaction(function () use ($bookUid, $userUid, $stakePoints, $betOrderId): array {
            return $this->transferFromBookmakerToUser(
                $bookUid,
                $userUid,
                $stakePoints,
                $betOrderId,
                PointsHoldState::BookStakeRefund,
                PointsHoldState::SettlementRefund,
                'Insufficient bookmaker liquidity for this refund.',
            );
        });
    }

    /** Credits the internal pool when a bet checkout is confirmed (paired with player's stake debit). */
    public function creditBookmakerAcceptedStake(int $bookUid, int $stakePoints, int $betOrderId): void
    {
        if ($bookUid < 1) {
            throw new RuntimeException('Invalid bookmaker user id.');
        }
        if ($stakePoints < 1 || $betOrderId < 1) {
            throw new RuntimeException('Invalid stake posting.');
        }

        DB::transaction(function () use ($bookUid, $stakePoints, $betOrderId): void {
            $balance = $this->balances->findLockedByUid($bookUid);
            if ($balance === null) {
                $balance = new PointsBalance([
                    'uid' => $bookUid,
                    'balance' => 0,
                ]);
                $balance->save();
                $balance = $this->balances->findLockedByUid($bookUid);
            }
            if ($balance === null) {
                throw new RuntimeException('Bookmaker points account missing.');
            }

            $balance->balance = $balance->balance + $stakePoints;
            $balance->save();

            $flow = new PointsFlow([
                'uid' => $bookUid,
                'oid' => $betOrderId,
                'amount' => $stakePoints,
                'state' => PointsHoldState::BookStakeCredit,
            ]);
            $flow->save();
        });
    }

    /**
     * Debits the bookmaker pool and credits the user by {@code $points}, with paired ledger rows.
     * Must be called inside {@see DB::transaction}; {@code $points} must be positive.
     *
     * @return array{user_flow: PointsFlow, book_flow: PointsFlow}
     */
    private function transferFromBookmakerToUser(
        int $bookUid,
        int $userUid,
        int $points,
        int $betOrderId,
        PointsHoldState $bookDebitState,
        PointsHoldState $userCreditState,
        string $insufficientBookLiquidityMessage,
    ): array {
        $bookBal = $this->balances->findLockedByUid($bookUid);
        if ($bookBal === null || $bookBal->balance < $points) {
            throw new RuntimeException($insufficientBookLiquidityMessage);
        }

        $userBal = $this->balances->findLockedByUid($userUid);
        if ($userBal === null) {
            throw new RuntimeException('Points account does not exist.');
        }

        $bookBal->balance = $bookBal->balance - $points;
        $bookBal->save();

        $userBal->balance = $userBal->balance + $points;
        $userBal->save();

        $bookFlow = new PointsFlow([
            'uid' => $bookUid,
            'oid' => $betOrderId,
            'amount' => -$points,
            'state' => $bookDebitState,
        ]);
        $bookFlow->save();

        $userFlow = new PointsFlow([
            'uid' => $userUid,
            'oid' => $betOrderId,
            'amount' => $points,
            'state' => $userCreditState,
        ]);
        $userFlow->save();

        return ['user_flow' => $userFlow, 'book_flow' => $bookFlow];
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
