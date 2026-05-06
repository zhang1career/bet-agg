<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Game;
use App\Models\Market;
use App\Models\Selection;

final class CatalogSeeder
{
    public static function openSelection(int $oddsMillis = 2000): int
    {
        $game = self::seedGame();

        $market = new Market([
            'game_id' => $game->id,
            'name' => 'Full-time 1X2',
            'status' => Market::STATUS_OPEN,
        ]);
        $market->save();

        $selection = new Selection([
            'market_id' => $market->id,
            'label' => 'Home',
            'current_odds_millis' => $oddsMillis,
            'status' => Selection::STATUS_OPEN,
        ]);
        $selection->save();

        return (int) $selection->id;
    }

    /**
     * @return array{game_local_id: int, selection_a_id: int, selection_b_id: int}
     */
    public static function duelSelections(int $aOddsMillis = 2000, int $bOddsMillis = 2000): array
    {
        $game = self::seedGame();

        $market = new Market([
            'game_id' => $game->id,
            'name' => '1X2',
            'status' => Market::STATUS_OPEN,
        ]);
        $market->save();

        $a = new Selection([
            'market_id' => $market->id,
            'label' => 'A',
            'current_odds_millis' => $aOddsMillis,
            'status' => Selection::STATUS_OPEN,
        ]);
        $a->save();

        $b = new Selection([
            'market_id' => $market->id,
            'label' => 'B',
            'current_odds_millis' => $bOddsMillis,
            'status' => Selection::STATUS_OPEN,
        ]);
        $b->save();

        return [
            'game_local_id' => (int) $game->id,
            'selection_a_id' => (int) $a->id,
            'selection_b_id' => (int) $b->id,
        ];
    }

    public static function seedGame(): Game
    {
        $rawId = (int) (Game::query()->max('raw_id') ?? 0) + 1;

        $game = new Game([
            'raw_id' => $rawId,
            'status' => Game::STATUS_OPEN,
        ]);
        $game->save();

        return $game;
    }
}
