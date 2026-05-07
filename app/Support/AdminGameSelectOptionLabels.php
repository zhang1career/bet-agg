<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Game;
use App\Services\mall\serv_fd\CmsGameClient;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Human-readable labels for admin game selects: CMS {@see CmsGameClient::findManyById} title
 * keyed by {@see Game::raw_id}, mapped to local {@see Game::id}.
 */
final readonly class AdminGameSelectOptionLabels
{
    public function __construct(
        private CmsGameClient $cmsGames,
    ) {}

    /**
     * @param  iterable<Game>  $games
     * @return array<int, string> Local primary key to label for each game
     */
    public function mapByLocalId(iterable $games): array
    {
        $list = $games instanceof Collection ? $games : collect($games);
        if ($list->isEmpty()) {
            return [];
        }

        $cmsByRawId = [];
        try {
            $rawIds = $list
                ->map(static fn (Game $g): int => (int) $g->raw_id)
                ->unique()
                ->values()
                ->all();
            if ($rawIds !== []) {
                $cmsByRawId = $this->cmsGames->findManyById($rawIds);
            }
        } catch (Throwable) {
        }

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
