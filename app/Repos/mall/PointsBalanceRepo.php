<?php

declare(strict_types=1);

namespace App\Repos\mall;

use App\Models\PointsBalance;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;

class PointsBalanceRepo
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
            throw new RuntimeException('Points balance row missing after create.');
        }

        return $locked;
    }

    /**
     * @return LengthAwarePaginator<int, PointsBalance>
     */
    public function paginateForAdmin(int $perPage): LengthAwarePaginator
    {
        return PointsBalance::query()
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return LengthAwarePaginator<int, PointsBalance>
     */
    public function paginateLeaderboard(int $page, int $perPage): LengthAwarePaginator
    {
        return PointsBalance::query()
            ->orderByDesc('balance')
            ->orderBy('uid')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function findOrFail(int $id): PointsBalance
    {
        $row = PointsBalance::query()->whereKey($id)->first();
        if ($row === null) {
            throw (new ModelNotFoundException)->setModel(PointsBalance::class, [$id]);
        }

        return $row;
    }

    public function createAccount(int $uid, int $initialBalance): PointsBalance
    {
        $existing = $this->findLockedByUid($uid);
        if ($existing !== null) {
            throw new RuntimeException('Points account already exists for this user.');
        }

        $row = new PointsBalance(['uid' => $uid, 'balance' => $initialBalance]);
        $row->save();

        return $row;
    }

    public function adjustBalanceByUid(int $uid, int $delta): PointsBalance
    {
        $row = $this->findLockedByUid($uid);
        if ($row === null) {
            throw new RuntimeException('Points account not found for this user.');
        }

        $row->balance = (int) $row->balance + $delta;
        $row->save();

        return $row;
    }

    public function addToBalance(PointsBalance $profile, int $delta): void
    {
        $profile->balance = (int) $profile->balance + $delta;
        $profile->save();
    }

    public function deleteBalance(PointsBalance $row): void
    {
        $row->delete();
    }
}
