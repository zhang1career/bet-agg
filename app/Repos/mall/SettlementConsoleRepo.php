<?php

declare(strict_types=1);

namespace App\Repos\mall;

use App\Enums\BetOrderStatus;
use App\Models\SettleJob;
use App\Support\SettlementBizKey;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Aggregates and reporting queries for admin settlement overview (joins across order / market / job tables).
 */
final class SettlementConsoleRepo
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

    /**
     * @return array<int, int> status enum value => count
     */
    public function distinctOrderCountsByStatusForGame(int $gameId): array
    {
        return $this->distinctOrderCountsByStatusForScope($gameId, 'game');
    }

    /**
     * @return array<int, int>
     */
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
            $q->join('biz_market as bm', 'bm.id', '=', 'oi.market_id')
                ->where('bm.game_id', $entityId);
        } else {
            $q->where('oi.market_id', $entityId);
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

    /**
     * @return array<int, int>
     */
    public function lineResultCountsForGame(int $gameId): array
    {
        if ($gameId < 1) {
            return [];
        }

        $rows = DB::table('order_item as oi')
            ->join('biz_market as bm', 'bm.id', '=', 'oi.market_id')
            ->where('bm.game_id', $gameId)
            ->selectRaw('oi.result as r, COUNT(*) as c')
            ->groupBy('oi.result')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->r] = (int) $row->c;
        }

        return $out;
    }

    /**
     * @return array<int, int>
     */
    public function lineResultCountsForMarket(int $marketId): array
    {
        if ($marketId < 1) {
            return [];
        }

        $rows = DB::table('order_item')
            ->where('market_id', $marketId)
            ->selectRaw('result as r, COUNT(*) as c')
            ->groupBy('result')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->r] = (int) $row->c;
        }

        return $out;
    }

    /**
     * @param  list<int|string>  $gameIds
     * @return array<int, SettleJob>
     */
    public function latestSettleJobByGameIds(array $gameIds): array
    {
        $ids = self::uniquePositiveInts($gameIds);
        if ($ids === []) {
            return [];
        }

        return $this->fetchLatestSettleJobsPerGameIds($ids);
    }

    /**
     * @param  list<int|string>  $gameIds
     * @return array<int, int>
     */
    public function pendingSettlementOrderCountByGameIds(array $gameIds): array
    {
        return $this->pendingSettlementOrderCounts($gameIds, 'game');
    }

    /**
     * @param  list<int|string>  $marketIds
     * @return array<int, int>
     */
    public function pendingSettlementOrderCountByMarketIds(array $marketIds): array
    {
        return $this->pendingSettlementOrderCounts($marketIds, 'market');
    }

    /**
     * @param  list<int|string>  $ids
     * @param  'game'|'market'  $groupBy
     * @return array<int, int>
     */
    private function pendingSettlementOrderCounts(array $ids, string $groupBy): array
    {
        $normalized = self::uniquePositiveInts($ids);
        if ($normalized === []) {
            return [];
        }

        if ($groupBy !== 'game' && $groupBy !== 'market') {
            throw new InvalidArgumentException('groupBy must be "game" or "market".');
        }

        $accepted = BetOrderStatus::Accepted->value;
        $failed = BetOrderStatus::SettlementFailed->value;

        $base = DB::table('bet_order as bo')
            ->join('order_item as oi', 'oi.oid', '=', 'bo.id')
            ->whereIn('bo.status', [$accepted, $failed]);

        if ($groupBy === 'game') {
            $rows = $base
                ->join('biz_market as bm', 'bm.id', '=', 'oi.market_id')
                ->whereIn('bm.game_id', $normalized)
                ->selectRaw('bm.game_id AS bucket_id, COUNT(DISTINCT bo.id) AS c')
                ->groupBy('bm.game_id')
                ->get();
        } else {
            $rows = $base
                ->whereIn('oi.market_id', $normalized)
                ->selectRaw('oi.market_id AS bucket_id, COUNT(DISTINCT bo.id) AS c')
                ->groupBy('oi.market_id')
                ->get();
        }

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->bucket_id] = (int) $row->c;
        }

        return $out;
    }

    /**
     * One grouped query per batch of games (MySQL {@code SUBSTRING_INDEX} / {@code GROUP BY}).
     *
     * @param  list<int>  $gameIds
     * @return array<int, SettleJob>
     */
    private function fetchLatestSettleJobsPerGameIds(array $gameIds): array
    {
        $likes = [];
        $bindings = [];
        foreach ($gameIds as $id) {
            $likes[] = 'biz_key LIKE ?';
            $bindings[] = SettlementBizKey::prefixForGame($id).':%';
        }

        $innerWhere = implode(' OR ', $likes);
        $gidExpr = "CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(biz_key, ':', 3), ':', -1) AS UNSIGNED)";

        $sql = <<<SQL
SELECT sj.*
FROM settle_job sj
INNER JOIN (
    SELECT MAX(id) AS max_id
    FROM settle_job
    WHERE ({$innerWhere})
    GROUP BY {$gidExpr}
) t ON sj.id = t.max_id
SQL;

        $rows = DB::select($sql, $bindings);

        $out = [];
        foreach ($rows as $row) {
            $job = SettleJob::hydrate([(array) $row])[0];
            $gid = SettlementBizKey::gameIdFromBizKey($job->biz_key);
            if ($gid !== null) {
                $out[$gid] = $job;
            }
        }

        return $out;
    }

    /**
     * @param  list<int|string>  $raw
     * @return list<int>
     */
    private static function uniquePositiveInts(array $raw): array
    {
        $out = [];
        foreach ($raw as $v) {
            $n = (int) $v;
            if ($n >= 1) {
                $out[$n] = true;
            }
        }

        return array_keys($out);
    }
}
