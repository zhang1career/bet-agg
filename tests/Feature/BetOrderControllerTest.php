<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BetOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\SportSeeder;
use Tests\TestCase;

class BetOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('api_gw.base_url', 'http://foundation.local');
        config()->set('bet_agg.foundation.base_url', 'http://foundation.local');
        config()->set('bet_agg.foundation.me_endpoint', '/api/user/me');
    }

    public function test_create_order_requires_auth(): void
    {
        $response = $this->postJson('/api/bet/orders', [
            'lines' => [['kid' => 1, 'stake_points' => 1]],
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('errorCode', 40101);
    }

    public function test_create_order_persists_draft_bet_line(): void
    {
        Http::fake([
            'http://foundation.local/api/user/me' => Http::response([
                'errorCode' => 0,
                'data' => ['id' => 42, 'username' => 'buyer'],
                'message' => '',
            ], 200),
        ]);

        $sid = SportSeeder::openSelection(2000);

        $response = $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/bet/orders', [
            'lines' => [['kid' => $sid, 'stake_points' => 100]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('errorCode', 0)
            ->assertJsonPath('data.status', 0)
            ->assertJsonPath('data.total_price', 100)
            ->assertJsonPath('data.ext_inventory', false);

        $order = BetOrder::query()->where('uid', 42)->first();
        $this->assertNotNull($order);
        $this->assertSame($sid, (int) $order->lines->first()->kid);
    }
}
