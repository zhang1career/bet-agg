<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Models\SettleJob;
use App\Repos\mall\SettlementConsoleRepo;
use Illuminate\Support\Collection;

/**
 * Read models for admin console: links {@code settle_job} to {@code biz_game} via {@code biz_key},
 * and aggregates order / line settlement state through {@code order_item} → {@code biz_market}.
 *
 * Batch methods ({@see latestSettleJobByGameIds}, {@see pendingSettlementOrderCountByGameIds}, …)
 * are intended for list pages: one round-trip per aggregate type instead of N+1 per row.
 */
final readonly class SettlementConsoleOverviewService
{
    public function __construct(
        private SettlementConsoleRepo $overview,
    ) {}

    /**
     * @return Collection<int, SettleJob>
     */
    public function recentJobsForGame(int $gameId, int $limit = 15): Collection
    {
        return $this->overview->recentJobsForGame($gameId, $limit);
    }

    /**
     * Distinct bet orders that touch this game's markets, grouped by {@see BetOrderStatus} value.
     *
     * @return array<int, int> status enum value => count
     */
    public function distinctOrderCountsByStatusForGame(int $gameId): array
    {
        return $this->overview->distinctOrderCountsByStatusForGame($gameId);
    }

    /**
     * Distinct orders that include a line on this market.
     *
     * @return array<int, int>
     */
    public function distinctOrderCountsByStatusForMarket(int $marketId): array
    {
        return $this->overview->distinctOrderCountsByStatusForMarket($marketId);
    }

    /**
     * Order line rows for this game's markets, grouped by {@see BetLineResult} value.
     *
     * @return array<int, int> line result enum value => count
     */
    public function lineResultCountsForGame(int $gameId): array
    {
        return $this->overview->lineResultCountsForGame($gameId);
    }

    /**
     * @return array<int, int>
     */
    public function lineResultCountsForMarket(int $marketId): array
    {
        return $this->overview->lineResultCountsForMarket($marketId);
    }

    /**
     * Latest {@code settle_job} row per game (largest {@code id}) for the given game ids.
     * Missing keys mean no batch row exists for that game.
     *
     * Uses one grouped SQL query (MySQL).
     *
     * @param  list<int|string>  $gameIds
     * @return array<int, SettleJob>
     */
    public function latestSettleJobByGameIds(array $gameIds): array
    {
        return $this->overview->latestSettleJobByGameIds($gameIds);
    }

    /**
     * Distinct orders in Accepted / SettlementFailed touching any market of each game (pending payout / retry).
     *
     * @param  list<int|string>  $gameIds
     * @return array<int, int> game id => count
     */
    public function pendingSettlementOrderCountByGameIds(array $gameIds): array
    {
        return $this->overview->pendingSettlementOrderCountByGameIds($gameIds);
    }

    /**
     * Same as {@see pendingSettlementOrderCountByGameIds} but grouped by {@code order_item.market_id}.
     *
     * @param  list<int|string>  $marketIds
     * @return array<int, int> market id => count
     */
    public function pendingSettlementOrderCountByMarketIds(array $marketIds): array
    {
        return $this->overview->pendingSettlementOrderCountByMarketIds($marketIds);
    }
}
