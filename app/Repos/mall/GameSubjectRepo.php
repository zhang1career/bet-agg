<?php

declare(strict_types=1);

namespace App\Repos\mall;

use App\Models\GameSubject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class GameSubjectRepo
{
    /**
     * @return LengthAwarePaginator<int, GameSubject>
     */
    public function paginateForAdmin(?string $groupCode, int $perPage): LengthAwarePaginator
    {
        $query = GameSubject::query()
            ->with(['groups' => static fn ($q) => $q->orderBy('code')])
            ->withCount('groups')
            ->orderBy('name');
        if ($groupCode !== null) {
            $query->whereHas('groups', static fn (Builder $q) => $q->where('code', $groupCode));
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @return Collection<int, GameSubject>
     */
    public function listWithGroupsOrderedByName(): Collection
    {
        return GameSubject::query()->with('groups')->orderBy('name')->get();
    }

    public function findForAdmin(int $id): ?GameSubject
    {
        return GameSubject::query()
            ->whereKey($id)
            ->with('groups')
            ->first();
    }

    public function findForAdminShow(int $id): ?GameSubject
    {
        return GameSubject::query()
            ->whereKey($id)
            ->with(['groups' => static fn ($q) => $q->orderBy('code')])
            ->first();
    }

    /**
     * @param  list<int>  $groupIds
     */
    public function existsInAnyOfGroups(int $subjectId, array $groupIds): bool
    {
        return GameSubject::query()
            ->whereKey($subjectId)
            ->whereHas('groups', static fn (Builder $q) => $q->whereIn('biz_game_group.id', $groupIds))
            ->exists();
    }

    /**
     * @param  list<int>  $groupIds
     */
    public function createForAdmin(string $name, string $icon, string $info, array $groupIds): GameSubject
    {
        $subject = new GameSubject([
            'name' => $name,
            'icon' => $icon,
            'info' => $info,
        ]);
        $subject->save();
        $subject->groups()->sync($groupIds);

        return $subject;
    }

    /**
     * @param  list<int>  $groupIds
     */
    public function updateForAdmin(GameSubject $subject, string $name, string $icon, string $info, array $groupIds): void
    {
        $subject->name = $name;
        $subject->icon = $icon;
        $subject->info = $info;
        $subject->save();
        $subject->groups()->sync($groupIds);
    }

    public function detachGroupsAndDelete(GameSubject $subject): void
    {
        $subject->groups()->detach();
        $subject->delete();
    }
}
