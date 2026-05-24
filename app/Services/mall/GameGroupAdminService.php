<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Models\GameGroup;
use App\Repos\mall\GameGroupRepo;
use App\Services\mall\serv_fd\CmsGameClient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final readonly class GameGroupAdminService
{
    public function __construct(
        private GameGroupRepo $groups,
        private CmsGameClient $cmsGames,
    ) {}

    /**
     * @return LengthAwarePaginator<int, GameGroup>
     */
    public function paginateIndex(int $perPage): LengthAwarePaginator
    {
        return $this->groups->paginateForAdmin($perPage);
    }

    public function findForModal(int $id): ?GameGroup
    {
        return $this->groups->find($id);
    }

    public function findForShow(int $id): GameGroup
    {
        $group = $this->groups->findForAdminShow($id);
        if ($group === null) {
            throw new NotFoundHttpException();
        }

        return $group;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function cmsByRawIdsForGroup(GameGroup $group): array
    {
        $rawIds = $group->games
            ->map(static fn ($g): int => (int) $g->raw_id)
            ->unique()
            ->filter(static fn (int $r): bool => $r >= 1)
            ->values()
            ->all();
        if ($rawIds === []) {
            return [];
        }

        try {
            return $this->cmsGames->findManyById($rawIds);
        } catch (Throwable) {
            return [];
        }
    }

    public function create(string $code): void
    {
        $this->groups->create($code);
    }

    public function update(int $id, string $code): void
    {
        $group = $this->groups->find($id);
        if ($group === null) {
            throw new NotFoundHttpException();
        }

        $this->groups->updateCode($group, $code);
    }

    public function delete(int $id): void
    {
        $group = $this->groups->find($id);
        if ($group === null) {
            throw new NotFoundHttpException();
        }

        $this->groups->detachAllAndDelete($group);
    }
}
