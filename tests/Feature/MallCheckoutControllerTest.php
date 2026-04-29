<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BetOrderStatus;
use App\Enums\CheckoutPhase;
use App\Models\BetOrder;
use App\Models\MallPointsBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\SportSeeder;
use Tests\TestCase;

class MallCheckoutControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('api_gw.base_url', 'http://foundation.local');
        config()->set('bet_agg.foundation.base_url', 'http://foundation.local');
        config()->set('bet_agg.foundation.me_endpoint', '/api/user/me');
    }

    private function fakeUserMe(int $userId): void
    {
        Http::fake([
            'http://foundation.local/api/user/me' => Http::response([
                'errorCode' => 0,
                'data' => ['id' => $userId, 'username' => 'buyer'],
                'message' => '',
            ], 200),
        ]);
    }

    public function test_checkout_requires_auth(): void
    {
        $this->postJson('/api/bet/checkout', ['order_id' => 1])
            ->assertStatus(401)
            ->assertJsonPath('errorCode', 40101);
    }

    public function test_checkout_points_only_accepts_immediately(): void
    {
        $this->fakeUserMe(42);

        $sid = SportSeeder::openSelection(2000);

        $create = $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/bet/orders', [
            'lines' => [['kid' => $sid, 'stake_points' => 100]],
        ]);
        $create->assertCreated();
        $orderId = (int) $create->json('data.id');

        MallPointsBalance::query()->create(['uid' => 42, 'balance_minor' => 500]);

        $response = $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/bet/checkout', [
            'order_id' => $orderId,
            'points_minor' => 100,
        ]);

        $response->assertCreated()
            ->assertJsonPath('errorCode', 0)
            ->assertJsonPath('data.order.status', BetOrderStatus::Accepted->value)
            ->assertJsonPath('data.prepay.invoke_payment', 'none');

        $order = BetOrder::query()->find($orderId);
        $this->assertNotNull($order);
        $this->assertSame(CheckoutPhase::Completed, $order->checkout_phase);
    }

    public function test_checkout_with_cash_portion_sets_await_payment(): void
    {
        $this->fakeUserMe(42);

        $sid = SportSeeder::openSelection(2000);

        $orderId = (int) $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/bet/orders', [
            'lines' => [['kid' => $sid, 'stake_points' => 100]],
        ])->json('data.id');

        MallPointsBalance::query()->create(['uid' => 42, 'balance_minor' => 30]);

        $response = $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/bet/checkout', [
            'order_id' => $orderId,
            'points_minor' => 30,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.order.status', BetOrderStatus::Pending->value)
            ->assertJsonPath('data.prepay.amount_minor', 70)
            ->assertJsonPath('data.prepay.invoke_payment', 'placeholder');

        $order = BetOrder::query()->find($orderId);
        $this->assertNotNull($order);
        $this->assertSame(CheckoutPhase::AwaitPayment, $order->checkout_phase);
    }

    public function test_checkout_rejects_when_order_not_draft(): void
    {
        $this->fakeUserMe(1);

        $sid = SportSeeder::openSelection(2000);

        $orderId = (int) $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/bet/orders', [
            'lines' => [['kid' => $sid, 'stake_points' => 50]],
        ])->json('data.id');

        BetOrder::query()->whereKey($orderId)->update(['checkout_phase' => CheckoutPhase::AwaitPayment->value]);

        $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/bet/checkout', [
            'order_id' => $orderId,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errorCode', 40001);
    }

    public function test_checkout_returns_422_when_order_id_invalid(): void
    {
        $this->fakeUserMe(1);

        $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/bet/checkout', [
            'order_id' => 0,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errorCode', 100);
    }

    public function test_checkout_returns_404_when_order_not_found(): void
    {
        $this->fakeUserMe(1);

        $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/bet/checkout', [
            'order_id' => 99999,
        ])
            ->assertStatus(404)
            ->assertJsonPath('errorCode', 40401);
    }
}
