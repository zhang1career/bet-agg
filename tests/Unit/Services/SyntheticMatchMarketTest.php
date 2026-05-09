<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\MarketType;
use App\Enums\MatchOutcomeCode;
use App\Models\Game;
use App\Models\GameSubject;
use App\Models\Market;
use App\Services\mall\SyntheticMatchMarket;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SyntheticMatchMarketTest extends TestCase
{
    public function test_legs_for_api_one_x2_uses_default_side_labels_when_game_null(): void
    {
        $market = new Market;
        $market->type = MarketType::OneX2;
        $market->odds_millis = Market::oneX2OddsMillisJson(1800, 3000, 4200);

        $svc = new SyntheticMatchMarket;
        $legs = $svc->legsForApi($market, null);

        $this->assertCount(3, $legs);
        $this->assertSame('主队胜', $legs[0]['label']);
        $this->assertSame('平局', $legs[1]['label']);
        $this->assertSame('客队胜', $legs[2]['label']);
        $this->assertSame(1800, $legs[0]['current_odds_millis']);
        $this->assertSame(3000, $legs[1]['current_odds_millis']);
        $this->assertSame(4200, $legs[2]['current_odds_millis']);
    }

    public function test_legs_for_api_one_x2_embeds_subject_names(): void
    {
        $market = new Market;
        $market->type = MarketType::OneX2;
        $market->odds_millis = Market::oneX2OddsMillisJson(1, 2, 3);

        $game = new Game;
        $game->setRelation('sideASubject', new GameSubject(['name' => 'Alpha']));
        $game->setRelation('sideBSubject', new GameSubject(['name' => 'Beta']));

        $svc = new SyntheticMatchMarket;
        $legs = $svc->legsForApi($market, $game);

        $this->assertSame('Alpha胜', $legs[0]['label']);
        $this->assertSame('Beta胜', $legs[2]['label']);
    }

    public function test_odds_millis_for_outcome_returns_mapped_value(): void
    {
        $market = new Market;
        $market->type = MarketType::OneX2;
        $market->odds_millis = Market::oneX2OddsMillisJson(111, 222, 333);

        $svc = new SyntheticMatchMarket;

        $this->assertSame(
            222,
            $svc->oddsMillisForOutcome($market, MatchOutcomeCode::Draw->value),
        );
    }

    public function test_odds_millis_for_outcome_throws_on_invalid_code(): void
    {
        $market = new Market;
        $market->type = MarketType::OneX2;
        $market->odds_millis = [];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid outcome_code.');

        (new SyntheticMatchMarket)->oddsMillisForOutcome($market, 'not_an_outcome');
    }
}
