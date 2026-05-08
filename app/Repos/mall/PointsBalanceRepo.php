<?php

declare(strict_types=1);

namespace App\Repos\mall;

use App\Models\PointsBalance;

/**
 * Points wallet rows: reads and pessimistic locks for ledger flows.
 */
final class PointsBalanceRepo
{
    public function findByUid(int $uid): ?PointsBalance
    {
        return PointsBalance::query()->where('uid', $uid)->first();
    }

    public function existsLockedByUid(int $uid): bool
    {
        return PointsBalance::query()->where('uid', $uid)->lockForUpdate()->exists();
    }

    public function findLockedByUid(int $uid): ?PointsBalance
    {
        return PointsBalance::query()->where('uid', $uid)->lockForUpdate()->first();
    }

    public function findLockedById(int $id): ?PointsBalance
    {
        return PointsBalance::query()->where('id', $id)->lockForUpdate()->first();
    }
}
