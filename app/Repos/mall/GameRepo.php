<?php

declare(strict_types=1);

namespace App\Repos\mall;

use App\Models\Game;

class GameRepo
{
    public function lockForUpdate(int $gameId): ?Game
    {
        return Game::query()->whereKey($gameId)->lockForUpdate()->first();
    }
}
