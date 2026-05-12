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

        $svc = new SyntheticMatchMarket;
        $legs = $svc->legsForApi($market, null);

        $this->assertCount(3, $legs);
        $this->assertSame('主队胜', $legs[0]['label']);
        $this->assertSame('平局', $legs[1]['label']);
        $this->assertSame('客队胜', $legs[2]['label']);
        $this->assertSame(MatchOutcomeCode::HomeWin->value, $legs[0]['outcome_code']);
    }

    public function test_legs_for_api_one_x2_embeds_subject_names(): void
    {
        $market = new Market;
        $market->type = MarketType::OneX2;

        $game = new Game;
        $game->setRelation('sideASubject', new GameSubject(['name' => 'Alpha']));
        $game->setRelation('sideBSubject', new GameSubject(['name' => 'Beta']));

        $svc = new SyntheticMatchMarket;
        $legs = $svc->legsForApi($market, $game);

        $this->assertSame('Alpha胜', $legs[0]['label']);
        $this->assertSame('Beta胜', $legs[2]['label']);
    }

    public function test_assert_valid_outcome_accepts_known_codes(): void
    {
        $market = new Market;
        $market->type = MarketType::OneX2;

        (new SyntheticMatchMarket)->assertValidOutcome($market, MatchOutcomeCode::Draw->value);
        $this->addToAssertionCount(1);
    }

    public function test_assert_valid_outcome_throws_on_invalid_code(): void
    {
        $market = new Market;
        $market->type = MarketType::OneX2;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid outcome_code.');

        (new SyntheticMatchMarket)->assertValidOutcome($market, 'not_an_outcome');
    }
}
