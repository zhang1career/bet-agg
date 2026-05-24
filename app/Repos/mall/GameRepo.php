<?php

declare(strict_types=1);

namespace App\Repos\mall;

use App\Models\Game;
use App\Services\mall\GameListFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class GameRepo
{
    public function lockForUpdate(int $gameId): ?Game
    {
        return Game::query()->whereKey($gameId)->lockForUpdate()->first();
    }

    public function findOrFail(int $id): Game
    {
        $game = Game::query()->whereKey($id)->first();
        if ($game === null) {
            throw (new ModelNotFoundException)->setModel(Game::class, [$id]);
        }

        return $game;
    }

    public function findForAdmin(int $id): ?Game
    {
        return Game::query()
            ->whereKey($id)
            ->with('groups')
            ->first();
    }

    public function findForAdminShow(int $id): ?Game
    {
        return Game::query()
            ->whereKey($id)
            ->with([
                'groups',
                'sideASubject',
                'sideBSubject',
                'markets' => static fn ($q) => $q->orderByDesc('id'),
            ])
            ->first();
    }

    /**
     * @return LengthAwarePaginator<int, Game>
     */
    public function paginateForCatalog(GameListFilter $filter, int $page, int $perPage): LengthAwarePaginator
    {
        $query = Game::query()->with(['sideASubject', 'sideBSubject']);
        $this->applyCatalogFilter($query, $filter);
        $this->applyCatalogSort($query, $filter);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function findForCatalogDetail(int $localId): ?Game
    {
        return Game::query()
            ->whereKey($localId)
            ->with(['groups', 'sideASubject', 'sideBSubject'])
            ->first();
    }

    /**
     * @return LengthAwarePaginator<int, Game>
     */
    public function paginateForAdmin(?int $statusFilter, string $dir, int $perPage): LengthAwarePaginator
    {
        $query = $this->adminListQuery($statusFilter);

        return $query
            ->orderBy('id', $dir === 'asc' ? 'asc' : 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function countForAdmin(?int $statusFilter): int
    {
        return $this->adminListQuery($statusFilter)->count();
    }

    /**
     * @return EloquentCollection<int, Game>
     */
    public function listForAdminStartsAtMerge(?int $statusFilter, int $cap): EloquentCollection
    {
        return $this->adminListQuery($statusFilter)
            ->orderByDesc('id')
            ->limit($cap)
            ->get();
    }

    /**
     * @return Collection<int, Game>
     */
    public function listForAdminSelect(int $limit = 500): Collection
    {
        return Game::query()
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Game>
     */
    public function listOpenWithBothSides(int $limit = 500): Collection
    {
        return Game::query()
            ->where('status', Game::STATUS_OPEN)
            ->whereNotNull('side_a_subj_id')
            ->whereNotNull('side_b_subj_id')
            ->with(['sideASubject', 'sideBSubject'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Game>
     */
    public function listPendingSettlement(): Collection
    {
        return Game::query()
            ->where('status', Game::STATUS_PENDING_SETTLEMENT)
            ->orderBy('id')
            ->get();
    }

    public function existsReferencingSubject(int $subjectId): bool
    {
        return Game::query()
            ->where('side_a_subj_id', $subjectId)
            ->orWhere('side_b_subj_id', $subjectId)
            ->exists();
    }

    public function hasMarkets(Game $game): bool
    {
        return $game->markets()->exists();
    }

    public function createForAdmin(int $rawId, ?int $sideA, ?int $sideB, int $status): Game
    {
        $game = new Game([
            'raw_id' => $rawId,
            'side_a_subj_id' => $sideA,
            'side_b_subj_id' => $sideB,
            'status' => $status,
        ]);
        $game->save();

        return $game;
    }

    public function updateForAdmin(Game $game, ?int $sideA, ?int $sideB, int $status): void
    {
        $game->side_a_subj_id = $sideA;
        $game->side_b_subj_id = $sideB;
        $game->status = $status;
        $game->save();
    }

    /**
     * @param  list<int>  $groupIds
     */
    public function syncGroups(Game $game, array $groupIds): void
    {
        $game->groups()->sync($groupIds);
    }

    public function detachGroups(Game $game): void
    {
        $game->groups()->detach();
    }

    public function delete(Game $game): void
    {
        $game->delete();
    }

    /**
     * @param  array{winners?: list<string>, voids?: list<string>}  $settleOutcomes
     */
    public function markPendingSettlement(Game $game, array $settleOutcomes, int $nowMillis): void
    {
        $game->settle_outcomes = $settleOutcomes;
        $game->status = Game::STATUS_PENDING_SETTLEMENT;
        $game->ut = $nowMillis;
        $game->save();
    }

    public function markSettled(Game $game, int $nowMillis): void
    {
        $game->status = Game::STATUS_SETTLED;
        $game->ut = $nowMillis;
        $game->save();
    }

    /**
     * @param  Builder<Game>  $query
     */
    private function applyCatalogFilter(Builder $query, GameListFilter $filter): void
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
    private function applyCatalogSort(Builder $query, GameListFilter $filter): void
    {
        if ($filter->sort === null) {
            $query->orderByDesc('id');

            return;
        }
        [$column, $direction] = $filter->sort;
        $query->orderBy($column, $direction);
    }

    /**
     * @return Builder<Game>
     */
    private function adminListQuery(?int $statusFilter): Builder
    {
        $query = Game::query()->withCount('markets');
        if ($statusFilter !== null) {
            $query->where('status', $statusFilter);
        }

        return $query;
    }
}
