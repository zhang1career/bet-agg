<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\MarketType;
use App\Enums\MatchOutcomeCode;
use App\Models\Game;
use App\Models\GameGroup;
use App\Models\GameSubject;
use App\Models\Market;

final class CatalogSeeder
{
    /**
     * @return array{
     *     market_id: int,
     *     outcome_code: string,
     *     odds_millis: int,
     *     game_local_id: int,
     * }
     */
    public static function openHomeWinMarket(int $unusedOddsMillis = 2000): array
    {
        $game = self::seedGame();

        $market = new Market([
            'gid' => $game->id,
            'type' => MarketType::OneX2,
            'name' => 'Full-time 1X2',
            'status' => Market::STATUS_OPEN,
        ]);
        $market->save();

        return [
            'market_id' => (int) $market->id,
            'outcome_code' => MatchOutcomeCode::HomeWin->value,
            'game_local_id' => (int) $game->id,
        ];
    }

    /**
     * Open game with two subjects + group + 胜平负 market (no pricing in DB).
     *
     * @return array{
     *     game_local_id: int,
     *     market_id: int,
     * }
     */
    public static function oneXTwoSettlement(): array
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $group = new GameGroup(['code' => 'seed-g-'.$suffix]);
        $group->save();

        $homeSubject = new GameSubject(['name' => 'Seed Home '.$suffix]);
        $homeSubject->save();
        $awaySubject = new GameSubject(['name' => 'Seed Away '.$suffix]);
        $awaySubject->save();

        $group->subjects()->attach([(int) $homeSubject->id, (int) $awaySubject->id]);

        $game = self::seedGame((int) $homeSubject->id, (int) $awaySubject->id);
        $group->games()->attach((int) $game->id);

        $market = new Market([
            'gid' => $game->id,
            'type' => MarketType::OneX2,
            'name' => '胜平负',
            'status' => Market::STATUS_OPEN,
        ]);
        $market->save();

        return [
            'game_local_id' => (int) $game->id,
            'market_id' => (int) $market->id,
        ];
    }

    public static function seedGame(?int $sideA = null, ?int $sideB = null): Game
    {
        $rawId = (int) (Game::query()->max('raw_id') ?? 0) + 1;

        $game = new Game([
            'raw_id' => $rawId,
            'status' => Game::STATUS_OPEN,
            'side_a_subject_id' => $sideA,
            'side_b_subject_id' => $sideB,
        ]);
        $game->save();

        return $game;
    }
}
