<?php

declare(strict_types=1);

namespace App\Repos\mall;

use App\Models\PointsBalance;

final class PointsBalanceRepo
{
    public function findLockedByUid(int $uid): ?PointsBalance
    {
        return PointsBalance::query()->where('uid', $uid)->lockForUpdate()->first();
    }

    public function ensureLockedProfile(int $uid): PointsBalance
    {
        $row = $this->findLockedByUid($uid);
        if ($row !== null) {
            return $row;
        }

        $created = new PointsBalance(['uid' => $uid, 'balance' => 0]);
        $created->save();

        $locked = $this->findLockedByUid($uid);
        if ($locked === null) {
            throw new \RuntimeException('Points balance row missing after create.');
        }

        return $locked;
    }
}
