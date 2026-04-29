<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\SportMarketType;
use App\Models\SportEvent;
use App\Models\SportMarket;
use App\Models\SportSelection;

final class SportSeeder
{
    public static function openSelection(int $oddsMillis = 2000): int
    {
        $event = new SportEvent([
            'name' => 'Test FC v Test SC',
            'starts_at' => SportEvent::nowMillis(),
            'status' => SportEvent::STATUS_OPEN,
        ]);
        $event->save();

        $market = new SportMarket([
            'event_id' => $event->id,
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
