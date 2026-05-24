<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Models\PointsBalance;
use App\Models\PointsFlow;
use App\Repos\mall\PointsBalanceRepo;
use App\Repos\mall\PointsFlowRepo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use RuntimeException;

final readonly class PointsService
{
    public function __construct(
        private PointsBalanceRepo $balances,
        private PointsFlowRepo $flows,
    ) {}

    public function availableBalance(int $uid): int
    {
        $row = $this->balances->findByUid($uid);

        return $row === null ? 0 : (int) $row->balance;
    }

    /**
     * @return LengthAwarePaginator<int, PointsBalance>
     */
    public function paginateBalances(int $perPage): LengthAwarePaginator
    {
        return $this->balances->paginateForAdmin($perPage);
    }

    /**
     * @return LengthAwarePaginator<int, PointsFlow>
     */
    public function paginateFlows(int $perPage): LengthAwarePaginator
    {
        return $this->flows->paginateForAdmin($perPage);
    }

    public function findBalanceForShow(int $id): PointsBalance
    {
        return $this->balances->findOrFail($id);
    }

    public function findFlowForShow(int $id): PointsFlow
    {
        return $this->flows->findOrFail($id);
    }

    /**
     * @return array<string, list<string>>|null
     */
    public function createAccount(int $uid, int $initialBalance): ?array
    {
        try {
            $this->balances->createAccount($uid, $initialBalance);
        } catch (RuntimeException $e) {
            return ['account' => [$e->getMessage()]];
        }

        return null;
    }

    /**
     * @return array<string, list<string>>|null
     */
    public function adjustBalance(int $uid, int $delta): ?array
    {
        try {
            $this->balances->adjustBalanceByUid($uid, $delta);
        } catch (RuntimeException $e) {
            return ['adjust' => [$e->getMessage()]];
        }

        return null;
    }

    public function deleteBalance(int $id): void
    {
        $this->balances->deleteBalance($this->balances->findOrFail($id));
    }
}
