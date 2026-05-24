<?php

declare(strict_types=1);

namespace App\Repos\mall;

use App\Enums\PointsFlowKind;
use App\Models\PointsFlow;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PointsFlowRepo
{
    public function existsForOrderAndState(int $orderId, PointsFlowKind $kind): bool
    {
        return PointsFlow::query()
            ->where('oid', $orderId)
            ->where('state', $kind->value)
            ->exists();
    }

    public function create(int $uid, int $orderId, int $amount, PointsFlowKind $kind): PointsFlow
    {
        $flow = new PointsFlow([
            'uid' => $uid,
            'oid' => $orderId,
            'amount' => $amount,
            'state' => $kind,
        ]);
        $flow->save();

        return $flow;
    }

    /**
     * @return LengthAwarePaginator<int, PointsFlow>
     */
    public function paginateForAdmin(int $perPage): LengthAwarePaginator
    {
        return PointsFlow::query()
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOrFail(int $id): PointsFlow
    {
        $row = PointsFlow::query()->whereKey($id)->first();
        if ($row === null) {
            throw (new ModelNotFoundException)->setModel(PointsFlow::class, [$id]);
        }

        return $row;
    }
}
