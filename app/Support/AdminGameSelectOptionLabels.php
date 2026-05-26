<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Game;
use App\Services\mall\serv_fd\CmsGameClient;
use Illuminate\Support\Collection;

final readonly class AdminGameSelectOptionLabels
{
    public function __construct(
        private CmsGameClient $cmsGames,
    ) {}

    /**
     * @param  iterable<Game>  $games
     * @return array<int, string>
     */
    public function mapByLocalId(iterable $games): array
    {
        $list = $games instanceof Collection ? $games : collect($games);
        if ($list->isEmpty()) {
            return [];
        }

        $rawIds = $list
            ->map(static fn (Game $g): int => (int) $g->raw_id)
            ->unique()
            ->values()
            ->all();
        $cmsByRawId = $this->cmsGames->findManyByIdOrEmpty($rawIds);

        $out = [];
        foreach ($list as $game) {
            $rawId = (int) $game->raw_id;
            $row = $cmsByRawId[$rawId] ?? null;
            $title = '';
            if (is_array($row) && isset($row['title']) && is_string($row['title'])) {
                $title = trim($row['title']);
            }
            $out[(int) $game->id] = $title !== '' ? $title : 'Untitled';
        }

        return $out;
    }
}
