<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Repos\mall\PointsBalanceRepo;

final readonly class LeaderboardService
{
    public function __construct(
        private PointsBalanceRepo $balances,
    ) {}

    /**
     * @return array{
     *     items: list<array{rank: int, uid: int, score: int}>,
     *     pagination: array<string, int>
     * }
     */
    public function list(int $page, int $perPage): array
    {
        $paginator = $this->balances->paginateLeaderboard($page, $perPage);

        $base = ($paginator->currentPage() - 1) * $paginator->perPage();
        $items = [];
        $i = 0;
        foreach ($paginator->items() as $row) {
            $i++;
            $items[] = [
                'rank' => $base + $i,
                'uid' => (int) $row->uid,
                'score' => (int) $row->balance,
            ];
        }

        return [
            'items' => $items,
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }
}
