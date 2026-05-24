<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Models\BetOrder;
use App\Repos\mall\BetOrderRepo;
use App\Repos\mall\SettlementConsoleRepo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class OrderAdminService
{
    public function __construct(
        private BetOrderRepo $orders,
        private SettlementConsoleRepo $settlementConsole,
    ) {}

    /**
     * @return LengthAwarePaginator<int, BetOrder>
     */
    public function paginateIndex(int $perPage): LengthAwarePaginator
    {
        return $this->orders->paginateForAdmin($perPage);
    }

    /**
     * @return array{
     *     order: BetOrder,
     *     settlementGameIds: list<int>,
     *     settlementJobsByGameId: array<int, Collection<int, \App\Models\SettleJob>>
     * }
     */
    public function showViewData(int $id): array
    {
        $order = $this->findForShow($id);
        $gameIds = $this->settlementGameIdsForOrder($order);

        $jobsByGameId = [];
        foreach ($gameIds as $gid) {
            $jobsByGameId[$gid] = $this->settlementConsole->recentJobsForGame($gid, 5);
        }

        return [
            'order' => $order,
            'settlementGameIds' => $gameIds,
            'settlementJobsByGameId' => $jobsByGameId,
        ];
    }

    public function findForShow(int $id): BetOrder
    {
        $order = $this->orders->findForAdminShow($id);
        if ($order === null) {
            throw new NotFoundHttpException();
        }

        return $order;
    }

    /**
     * @return list<int>
     */
    public function settlementGameIdsForOrder(BetOrder $order): array
    {
        $gameIds = [];
        foreach ($order->lines as $line) {
            $gid = $line->market?->gid;
            if ($gid !== null && (int) $gid >= 1) {
                $gameIds[(int) $gid] = true;
            }
        }

        $ids = array_keys($gameIds);
        sort($ids);

        return $ids;
    }
}
