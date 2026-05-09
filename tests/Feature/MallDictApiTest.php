<?php

declare(strict_types=1);

namespace Tests\Feature;

use Paganini\Constants\ResponseConstant;
use Tests\TestCase;

class MallDictApiTest extends TestCase
{
    public function test_dict_requires_codes(): void
    {
        $this->getJson('/api/bet/dict')
            ->assertOk()
            ->assertJsonPath('errorCode', ResponseConstant::RET_MISSING_PARAM);

        $this->getJson('/api/bet/dict?codes=')
            ->assertOk()
            ->assertJsonPath('errorCode', ResponseConstant::RET_MISSING_PARAM);
    }

    public function test_dict_returns_points_hold_state(): void
    {
        $this->getJson('/api/bet/dict?codes=points_hold_state')
            ->assertOk()
            ->assertJsonPath('errorCode', ResponseConstant::RET_OK)
            ->assertJsonPath('data.points_hold_state.0.v', '10')
            ->assertJsonPath('data.points_hold_state.0.k', 'try pending');
    }

    public function test_dict_ignores_unknown_codes(): void
    {
        $this->getJson('/api/bet/dict?codes=unknown_code,points_hold_state')
            ->assertOk()
            ->assertJsonPath('errorCode', ResponseConstant::RET_OK)
            ->assertJsonMissingPath('data.unknown_code')
            ->assertJsonPath('data.points_hold_state.0.v', '10');
    }

    public function test_dict_returns_game_status(): void
    {
        $this->getJson('/api/bet/dict?codes=game_status')
            ->assertOk()
            ->assertJsonPath('errorCode', ResponseConstant::RET_OK)
            ->assertJsonPath('data.game_status.0.v', '1')
            ->assertJsonPath('data.game_status.0.k', 'Open');
    }

    public function test_dict_returns_market_status(): void
    {
        $this->getJson('/api/bet/dict?codes=market_status')
            ->assertOk()
            ->assertJsonPath('errorCode', ResponseConstant::RET_OK)
            ->assertJsonPath('data.market_status.0.v', '1')
            ->assertJsonPath('data.market_status.0.k', 'Open');
    }

    public function test_dict_codes_query_max_length(): void
    {
        $tooLong = str_repeat('a', 513);
        $this->getJson('/api/bet/dict?codes='.$tooLong)
            ->assertStatus(422);
    }
}
