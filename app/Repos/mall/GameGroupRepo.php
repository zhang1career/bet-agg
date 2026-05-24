<?php

declare(strict_types=1);

namespace App\Repos\mall;

use App\Models\GameGroup;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class GameGroupRepo
{
    /**
     * @return LengthAwarePaginator<int, GameGroup>
     */
    public function paginateForAdmin(int $perPage): LengthAwarePaginator
    {
        return GameGroup::query()
            ->withCount('games')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return Collection<int, GameGroup>
     */
    public function listOrderedByCode(): Collection
    {
        return GameGroup::query()->orderBy('code')->get();
    }

    public function find(int $id): ?GameGroup
    {
        return GameGroup::query()->whereKey($id)->first();
    }

    public function findForAdminShow(int $id): ?GameGroup
    {
        return GameGroup::query()
            ->whereKey($id)
            ->with([
                'games' => static fn ($q) => $q->orderBy('biz_game.id'),
            ])
            ->first();
    }

    public function create(string $code): GameGroup
    {
        $group = new GameGroup(['code' => $code]);
        $group->save();

        return $group;
    }

    public function updateCode(GameGroup $group, string $code): void
    {
        $group->code = $code;
        $group->save();
    }

    public function detachAllAndDelete(GameGroup $group): void
    {
        $group->games()->detach();
        $group->subjects()->detach();
        $group->delete();
    }
}
