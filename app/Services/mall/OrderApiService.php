<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Models\BetOrder;
use App\Repos\mall\BetOrderRepo;
use App\Support\BetOrderApiArray;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final readonly class OrderApiService
{
    public function __construct(
        private BetOrderRepo $orders,
    ) {}

    /**
     * @return array{
     *     items: list<array<string, mixed>>,
     *     pagination: array<string, int>
     * }
     */
    public function listForUser(int $uid, int $perPage): array
    {
        $paginator = $this->orders->paginateForUser($uid, $perPage);

        $items = [];
        foreach ($paginator->items() as $order) {
            $items[] = $this->serializeOrderSummary($order);
        }

        return [
            'items' => $items,
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detailForUser(int $uid, int $id): array
    {
        $order = $this->orders->findForUserWithLines($uid, $id);
        if ($order === null) {
            throw (new ModelNotFoundException)->setModel(BetOrder::class, [$id]);
        }

        return BetOrderApiArray::detail($order);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrderSummary(BetOrder $order): array
    {
        return [
            'id' => $order->id,
            'uid' => $order->uid,
            'status' => $order->status->value,
            'ct' => $order->ct,
            'ut' => $order->ut,
        ];
    }
}
