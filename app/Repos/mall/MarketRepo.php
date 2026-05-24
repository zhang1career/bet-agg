<?php

declare(strict_types=1);

namespace App\Repos\mall;

use App\Enums\MarketStatus;
use App\Enums\MarketType;
use App\Models\Game;
use App\Models\Market;
use App\Services\mall\MarketListFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class MarketRepo
{
    /**
     * Lock row for prediction submission.
     */
    public function lockWithGameAndSubjectsForPrediction(int $marketId): ?Market
    {
        return Market::query()
            ->with(['game.sideASubject', 'game.sideBSubject'])
            ->whereKey($marketId)
            ->lockForUpdate()
            ->first();
    }

    public function markAllSettledForGame(int $gameId, int $nowMillis): void
    {
        Market::query()
            ->where('gid', $gameId)
            ->update(['status' => Market::STATUS_SETTLED, 'ut' => $nowMillis]);
    }

    /**
     * @return list<int>
     */
    public function idsForGame(int $gameId): array
    {
        return Market::query()
            ->where('gid', $gameId)
            ->pluck('id')
            ->all();
    }

    /**
     * @return LengthAwarePaginator<int, Market>
     */
    public function paginateForCatalog(MarketListFilter $filter, int $page, int $perPage): LengthAwarePaginator
    {
        $query = Market::query()->with([
            'game.sideASubject',
            'game.sideBSubject',
        ]);
        $this->applyCatalogFilter($query, $filter);
        $query->orderByDesc('id');

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function findForCatalogDetail(int $id): ?Market
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
    public function withGamesForLegs(array $marketIds): EloquentCollection
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
     * @return LengthAwarePaginator<int, Market>
     */
    public function paginateForAdmin(int $perPage): LengthAwarePaginator
    {
        return Market::query()
            ->with('game')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findForAdmin(int $id): ?Market
    {
        return Market::query()->whereKey($id)->first();
    }

    public function findForAdminShow(int $id): ?Market
    {
        return Market::query()
            ->whereKey($id)
            ->with(['game.sideASubject', 'game.sideBSubject'])
            ->first();
    }

    public function createForAdmin(int $gameId, MarketType $type, string $name, MarketStatus $status): Market
    {
        $market = new Market([
            'gid' => $gameId,
            'type' => $type,
            'name' => $name,
            'status' => $status->value,
        ]);
        $market->save();

        return $market;
    }

    public function updateForAdmin(
        Market $market,
        int $gameId,
        MarketType $type,
        string $name,
        MarketStatus $status,
    ): void {
        $market->fill([
            'gid' => $gameId,
            'type' => $type,
            'name' => $name,
            'status' => $status->value,
        ]);
        $market->save();
    }

    public function delete(Market $market): void
    {
        $market->delete();
    }

    public function existsById(int $marketId): bool
    {
        return Market::query()->whereKey($marketId)->exists();
    }

    /**
     * @param  Builder<Market>  $query
     */
    private function applyCatalogFilter(Builder $query, MarketListFilter $filter): void
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
