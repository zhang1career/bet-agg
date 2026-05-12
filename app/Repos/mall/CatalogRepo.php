<?php

declare(strict_types=1);

namespace App\Repos\mall;

use App\Models\Game;
use App\Models\Market;
use App\Services\mall\GameListFilter;
use App\Services\mall\MarketListFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Read-side catalog queries for {@code biz_game} / {@code biz_market}.
 */
final class CatalogRepo
{
    /**
     * @return LengthAwarePaginator<int, Game>
     */
    public function paginateGames(GameListFilter $filter, int $page, int $perPage): LengthAwarePaginator
    {
        $query = Game::query()->with(['sideASubject', 'sideBSubject']);
        $this->applyGameFilter($query, $filter);
        $this->applyGameSort($query, $filter);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function findGameForDetail(int $localId): ?Game
    {
        return Game::query()
            ->whereKey($localId)
            ->with(['groups', 'sideASubject', 'sideBSubject'])
            ->first();
    }

    /**
     * @return LengthAwarePaginator<int, Market>
     */
    public function paginateMarkets(MarketListFilter $filter, int $page, int $perPage): LengthAwarePaginator
    {
        $query = Market::query()->with([
            'game.sideASubject',
            'game.sideBSubject',
        ]);
        $this->applyMarketFilter($query, $filter);
        $query->orderByDesc('id');

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function findMarketForDetail(int $id): ?Market
    {
        return Market::query()
            ->with(['game.sideASubject', 'game.sideBSubject'])
            ->whereKey($id)
            ->first();
    }

    /**
     * @param  list<int>  $marketIds
     * @return EloquentCollection<int, Market>
     */
    public function marketsWithGamesForLegs(array $marketIds): EloquentCollection
    {
        if ($marketIds === []) {
            return new EloquentCollection;
        }

        return Market::query()
            ->whereIn('id', $marketIds)
            ->with(['game.sideASubject', 'game.sideBSubject'])
            ->get()
            ->keyBy(static fn (Market $m): int => $m->id);
    }

    /**
     * @param  Builder<Game>  $query
     */
    private function applyGameFilter(Builder $query, GameListFilter $filter): void
    {
        if ($filter->statuses !== []) {
            $query->whereIn('status', $filter->statuses);
        }
        if ($filter->updatedAfterMillis !== null) {
            $query->where('ut', '>=', $filter->updatedAfterMillis);
        }
        if ($filter->groupCode !== null) {
            $query->whereHas('groups', static function (Builder $q) use ($filter): void {
                $q->where('biz_game_group.code', $filter->groupCode);
            });
        }
    }

    /**
     * @param  Builder<Game>  $query
     */
    private function applyGameSort(Builder $query, GameListFilter $filter): void
    {
        if ($filter->sort === null) {
            $query->orderByDesc('id');

            return;
        }
        [$column, $direction] = $filter->sort;
        $query->orderBy($column, $direction);
    }

    /**
     * @param  Builder<Market>  $query
     */
    private function applyMarketFilter(Builder $query, MarketListFilter $filter): void
    {
        if ($filter->statuses !== []) {
            $query->whereIn('status', $filter->statuses);
        }
        if ($filter->localGameId !== null) {
            $query->where('gid', $filter->localGameId);
        }
        if ($filter->updatedAfterMillis !== null) {
            $query->where('ut', '>=', $filter->updatedAfterMillis);
        }
        if ($filter->onlyMarketsUnderOpenGame) {
            $query->whereHas('game', static function (Builder $q): void {
                $q->where('status', Game::STATUS_OPEN);
            });
        }
    }
}
