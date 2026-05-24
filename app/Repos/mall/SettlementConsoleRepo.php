<?php

declare(strict_types=1);

namespace App\Repos\mall;

use App\Models\SettleJob;
use App\Support\SettlementBizKey;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SettlementConsoleRepo
{
    /**
     * @return Collection<int, SettleJob>
     */
    public function recentJobsForGame(int $gameId, int $limit = 15): Collection
    {
        if ($gameId < 1 || $limit < 1) {
            return collect();
        }

        $prefix = SettlementBizKey::prefixForGame($gameId).':';

        return SettleJob::query()
            ->where('biz_key', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /** @return array<int, int> */
    public function distinctOrderCountsByStatusForGame(int $gameId): array
    {
        return $this->distinctOrderCountsByStatusForScope($gameId, 'game');
    }

    /** @return array<int, int> */
    public function distinctOrderCountsByStatusForMarket(int $marketId): array
    {
        return $this->distinctOrderCountsByStatusForScope($marketId, 'market');
    }

    /**
     * @param  'game'|'market'  $scope
     * @return array<int, int>
     */
    private function distinctOrderCountsByStatusForScope(int $entityId, string $scope): array
    {
        if ($entityId < 1) {
            return [];
        }

        if ($scope !== 'game' && $scope !== 'market') {
            throw new InvalidArgumentException('scope must be "game" or "market".');
        }

        $q = DB::table('bet_order as bo')
            ->join('order_item as oi', 'oi.oid', '=', 'bo.id');

        if ($scope === 'game') {
            $q->join('biz_market as bm', 'bm.id', '=', 'oi.mid')
                ->where('bm.gid', $entityId);
        } else {
            $q->where('oi.mid', $entityId);
        }

        $rows = $q->selectRaw('bo.status AS st, COUNT(DISTINCT bo.id) AS c')
            ->groupBy('bo.status')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->st] = (int) $row->c;
        }

        return $out;
    }

    /** @return array<int, int> */
    public function lineResultCountsForGame(int $gameId): array
    {
        if ($gameId < 1) {
            return [];
        }

        $rows = DB::table('order_item as oi')
            ->join('biz_market as bm', 'bm.id', '=', 'oi.mid')
            ->where('bm.gid', $gameId)
            ->selectRaw('oi.result as r, COUNT(*) as c')
            ->groupBy('oi.result')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->r] = (int) $row->c;
        }

        return $out;
    }

    /** @return array<int, int> */
    public function lineResultCountsForMarket(int $marketId): array
    {
        if ($marketId < 1) {
            return [];
        }

        $rows = DB::table('order_item')
            ->where('mid', $marketId)
            ->selectRaw('result as r, COUNT(*) as c')
            ->groupBy('result')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->r] = (int) $row->c;
        }

        return $out;
    }
}
