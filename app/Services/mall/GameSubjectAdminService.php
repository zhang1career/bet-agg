<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Models\GameSubject;
use App\Repos\mall\GameGroupRepo;
use App\Repos\mall\GameRepo;
use App\Repos\mall\GameSubjectRepo;
use App\Support\AdminGroupIds;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class GameSubjectAdminService
{
    public function __construct(
        private GameSubjectRepo $subjects,
        private GameGroupRepo $groups,
        private GameRepo $games,
    ) {}

    /**
     * @return LengthAwarePaginator<int, GameSubject>
     */
    public function paginateIndex(?string $groupCode, int $perPage): LengthAwarePaginator
    {
        return $this->subjects->paginateForAdmin($groupCode, $perPage);
    }

    /**
     * @return Collection<int, \App\Models\GameGroup>
     */
    public function listGroups(): Collection
    {
        return $this->groups->listOrderedByCode();
    }

    public function findForModal(int $id): ?GameSubject
    {
        return $this->subjects->findForAdmin($id);
    }

    public function findForShow(int $id): GameSubject
    {
        $subject = $this->subjects->findForAdminShow($id);
        if ($subject === null) {
            throw new NotFoundHttpException();
        }

        return $subject;
    }

    /**
     * @param  list<int>  $groupIds
     */
    public function create(string $name, string $icon, array $groupIds): void
    {
        $this->subjects->createForAdmin($name, $icon, $groupIds);
    }

    /**
     * @param  list<int>  $groupIds
     */
    public function update(int $id, string $name, string $icon, array $groupIds): void
    {
        $subject = $this->subjects->findForAdmin($id);
        if ($subject === null) {
            throw new NotFoundHttpException();
        }

        $this->subjects->updateForAdmin($subject, $name, $icon, $groupIds);
    }

    /**
     * @return array<string, list<string>>|null
     */
    public function delete(int $id): ?array
    {
        $subject = $this->subjects->findForAdmin($id);
        if ($subject === null) {
            throw new NotFoundHttpException();
        }

        if ($this->games->existsReferencingSubject($subject->id)) {
            return ['delete' => ['有赛事仍引用该主体为 A/B 方，无法删除。']];
        }

        $this->subjects->detachGroupsAndDelete($subject);

        return null;
    }

    public static function parseGroupFilter(mixed $raw): ?string
    {
        if (! is_string($raw)) {
            return null;
        }
        $code = trim($raw);

        return $code === '' ? null : $code;
    }
}
