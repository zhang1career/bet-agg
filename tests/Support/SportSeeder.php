<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\SportMarketType;
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
            'market_type' => SportMarketType::MatchResult1x2,
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
}
