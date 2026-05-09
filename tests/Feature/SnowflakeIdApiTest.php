<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Paganini\Constants\ResponseConstant;
use Tests\TestCase;

final class SnowflakeIdApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('api_gw.base_url', 'http://foundation.local');
        config()->set('bet_agg.foundation.base_url', 'http://foundation.local');
        config()->set('bet_agg.foundation.me_endpoint', '/api/user/me');
    }

    public function test_post_mints_id_via_foundation_when_authenticated(): void
    {
        config()->set('bet_agg.snowflake.access_key', 'test-access-key');
        config()->set('bet_agg.snowflake.mint_endpoint', '/api/snowflake/id');

        Http::fake([
            'http://foundation.local/api/user/me' => Http::response([
                'errorCode' => ResponseConstant::RET_OK,
                'data' => ['id' => 42, 'username' => 'u'],
                'message' => '',
            ], 200),
            'http://foundation.local/api/snowflake/id' => Http::response([
                'errorCode' => 0,
                'data' => ['id' => '1709123456789012345'],
                'message' => '',
                '_req_id' => 'f',
            ], 200),
        ]);

        $response = $this->withHeader('X-User-Access-Token', 'tok')
            ->postJson('/api/bet/snowflake');

        $response->assertOk()
            ->assertJsonPath('errorCode', 0)
            ->assertJsonPath('data.id', '1709123456789012345')
            ->assertJsonPath('_req_id', '');

        Http::assertSent(static function (Request $request): bool {
            return $request->url() === 'http://foundation.local/api/snowflake/id'
                && $request->method() === 'POST'
                && $request['access_key'] === 'test-access-key';
        });
    }

    public function test_requires_access_token(): void
    {
        config()->set('bet_agg.snowflake.access_key', 'k');

        $this->postJson('/api/bet/snowflake')
            ->assertStatus(401)
            ->assertJsonPath('errorCode', ResponseConstant::RET_UNAUTHORIZED);
    }

    public function test_503_when_snowflake_access_key_missing_after_auth(): void
    {
        config()->set('bet_agg.snowflake.access_key', '');

        Http::fake([
            'http://foundation.local/api/user/me' => Http::response([
                'errorCode' => ResponseConstant::RET_OK,
                'data' => ['id' => 1, 'username' => 'u'],
                'message' => '',
            ], 200),
        ]);

        $this->withHeader('X-User-Access-Token', 'tok')
            ->postJson('/api/bet/snowflake')
            ->assertStatus(503);
    }
}
