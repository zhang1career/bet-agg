<?php

declare(strict_types=1);

namespace App\Repos\mall;

use App\Enums\BetLineResult;
use App\Enums\BetOrderStatus;
use App\Models\BetOrder;
use App\Models\OrderItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class BetOrderRepo
{
    public function createAccepted(int $uid, int $idemKey): BetOrder
    {
        $order = new BetOrder([
            'uid' => $uid,
            'idem_key' => $idemKey,
            'status' => BetOrderStatus::Accepted,
        ]);
        $order->save();

        return $order;
    }

    public function findWithLinesByUserIdem(int $uid, int $idemKey): ?BetOrder
    {
        return BetOrder::query()
            ->with('lines')
            ->where('uid', $uid)
            ->where('idem_key', $idemKey)
            ->first();
    }

    /**
     * @return LengthAwarePaginator<int, BetOrder>
     */
    public function paginateForUser(int $uid, int $perPage): LengthAwarePaginator
    {
        return BetOrder::query()
            ->where('uid', $uid)
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findForUserWithLines(int $uid, int $id): ?BetOrder
    {
        return BetOrder::query()
            ->whereKey($id)
            ->where('uid', $uid)
            ->with('lines')
            ->first();
    }

    public function findLocked(int $orderId): ?BetOrder
    {
        return BetOrder::query()->whereKey($orderId)->lockForUpdate()->first();
    }

    /**
     * @param  list<int>  $marketIds  {@code order_item.mid} values
     * @return list<int> {@code bet_order.id}
     */
    public function idsPendingSettlementTouchingMarkets(array $marketIds): array
    {
        return BetOrder::query()
            ->whereIn('status', [
                BetOrderStatus::Accepted->value,
                BetOrderStatus::SettlementFailed->value,
            ])
            ->whereHas('lines', static function ($q) use ($marketIds): void {
                $q->whereIn('mid', $marketIds);
            })
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    /**
     * @return LengthAwarePaginator<int, BetOrder>
     */
    public function paginateForAdmin(int $perPage): LengthAwarePaginator
    {
        return BetOrder::query()
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findForAdminShow(int $id): ?BetOrder
    {
        return BetOrder::query()
            ->with(['lines.market.game'])
            ->whereKey($id)
            ->first();
    }

    public function findLockedWithLines(int $orderId): ?BetOrder
    {
        return BetOrder::query()
            ->whereKey($orderId)
            ->with('lines')
            ->lockForUpdate()
            ->first();
    }

    public function saveStatus(BetOrder $order, BetOrderStatus $status): void
    {
        $order->status = $status;
        $order->save();
    }

    public function applySettlementOutcome(
        BetOrder $order,
        OrderItem $line,
        BetLineResult $lineResult,
        BetOrderStatus $orderStatus,
    ): void {
        $line->result = $lineResult;
        $line->save();
        $order->status = $orderStatus;
        $order->save();
    }

    public function findOrFail(int $id): BetOrder
    {
        $order = BetOrder::query()->whereKey($id)->first();
        if ($order === null) {
            throw (new ModelNotFoundException)->setModel(BetOrder::class, [$id]);
        }

        return $order;
    }
}
