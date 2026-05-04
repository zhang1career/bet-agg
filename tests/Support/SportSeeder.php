<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\SportGame;
use App\Models\SportMarket;
use App\Models\SportSelection;

final class SportSeeder
{
    public static function openSelection(int $oddsMillis = 2000): int
    {
        $rawId = (int) (SportGame::query()->max('raw_id') ?? 0) + 1;

        $game = new SportGame([
            'raw_id' => $rawId,
            'status' => SportGame::STATUS_OPEN,
        ]);
        $game->save();

        $market = new SportMarket([
            'game_id' => $game->id,
            'name' => 'Full-time 1X2',
            'status' => SportMarket::STATUS_OPEN,
        ]);
        $market->save();

        $selection = new SportSelection([
            'market_id' => $market->id,
            'label' => 'Home',
            'current_odds_millis' => $oddsMillis,
            'status' => SportSelection::STATUS_OPEN,
        ]);
        $selection->save();

        return (int) $selection->id;
    }

    /**
     * @return array{game_local_id: int, selection_a_id: int, selection_b_id: int}
     */
    public static function duelSelections(int $aOddsMillis = 2000, int $bOddsMillis = 2000): array
    {
        $rawId = (int) (SportGame::query()->max('raw_id') ?? 0) + 1;

        $game = new SportGame([
            'raw_id' => $rawId,
            'status' => SportGame::STATUS_OPEN,
        ]);
        $game->save();

        $market = new SportMarket([
            'game_id' => $game->id,
            'name' => '1X2',
            'status' => SportMarket::STATUS_OPEN,
        ]);
        $market->save();

        $a = new SportSelection([
            'market_id' => $market->id,
            'label' => 'A',
            'current_odds_millis' => $aOddsMillis,
            'status' => SportSelection::STATUS_OPEN,
        ]);
        $a->save();

        $b = new SportSelection([
            'market_id' => $market->id,
            'label' => 'B',
            'current_odds_millis' => $bOddsMillis,
            'status' => SportSelection::STATUS_OPEN,
        ]);
        $b->save();

        return [
            'game_local_id' => (int) $game->id,
            'selection_a_id' => (int) $a->id,
            'selection_b_id' => (int) $b->id,
        ];
    }
}
