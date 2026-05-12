<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PointsBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Paganini\Constants\ResponseConstant;
use Tests\TestCase;

class MallPointsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('api_gw.base_url', 'http://foundation.local');
        config()->set('bet_agg.foundation.base_url', 'http://foundation.local');
        config()->set('bet_agg.foundation.me_endpoint', '/api/user/me');
    }

    public function test_points_requires_auth(): void
    {
        $this->getJson('/api/bet/points')->assertStatus(401)->assertJsonPath('errorCode', ResponseConstant::RET_UNAUTHORIZED);
    }

    public function test_points_returns_zero_when_no_profile_row(): void
    {
        Http::fake(array_merge([
            'http://foundation.local/api/user/me' => Http::response([
                'errorCode' => ResponseConstant::RET_OK,
                'data' => ['id' => 7, 'username' => 'u'],
                'message' => '',
            ], 200),
        ], self::cmsGatewayGameFakes()));

        $this->withHeader('X-User-Access-Token', 'tok')->getJson('/api/bet/points')
            ->assertOk()
            ->assertJsonPath('errorCode', ResponseConstant::RET_OK)
            ->assertJsonPath('data.balance', 0);
    }

    public function test_points_returns_balance(): void
    {
        Http::fake(array_merge([
            'http://foundation.local/api/user/me' => Http::response([
                'errorCode' => ResponseConstant::RET_OK,
                'data' => ['id' => 88, 'username' => 'u'],
                'message' => '',
            ], 200),
        ], self::cmsGatewayGameFakes()));

        PointsBalance::query()->create([
            'uid' => 88,
            'balance' => 12_345,
            'ct' => 1,
            'ut' => 1,
        ]);

        $this->withHeader('X-User-Access-Token', 'tok')->getJson('/api/bet/points')
            ->assertOk()
            ->assertJsonPath('errorCode', ResponseConstant::RET_OK)
            ->assertJsonPath('data.balance', 12_345);
    }
}
