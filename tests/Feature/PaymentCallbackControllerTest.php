<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BetOrderStatus;
use App\Enums\CheckoutPhase;
use App\Models\BetOrder;
use App\Models\MallPointsBalance;
use App\Services\mall\BetCheckoutService;
use App\Services\mall\OrderCommandService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SportSeeder;
use Tests\TestCase;

final class PaymentCallbackControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_callback_marks_order_paid_after_confirming_points_hold(): void
    {
        $sid = SportSeeder::openSelection(2000);
        $order = app(OrderCommandService::class)
            ->createDraftPendingOrder(7, [['selection_id' => $sid, 'stake_points' => 10]]);

        MallPointsBalance::query()->create(['uid' => 7, 'balance_minor' => 100]);

        app(\App\Services\mall\BetCheckoutService::class)->checkoutExistingOrder(7, $order, 3);

        $fresh = BetOrder::query()->find($order->id);
        $this->assertNotNull($fresh);
        $this->assertSame(CheckoutPhase::AwaitPayment, $fresh->checkout_phase);

        $this->postJson('/api/bet/payment/callback', [
            'order_id' => $order->id,
            'status' => 'paid',
        ])->assertOk()->assertJsonPath('errorCode', 0)
            ->assertJsonPath('data.status', BetOrderStatus::Accepted->value);

        $again = BetOrder::query()->find($order->id);
        $this->assertNotNull($again);
        $this->assertSame(BetOrderStatus::Accepted, $again->status);
        $this->assertSame(CheckoutPhase::Completed, $again->checkout_phase);
    }

    public function test_callback_is_idempotent_when_already_paid(): void
    {
        $sid = SportSeeder::openSelection(2000);
        $order = app(OrderCommandService::class)
            ->createDraftPendingOrder(8, [['selection_id' => $sid, 'stake_points' => 5]]);
        MallPointsBalance::query()->create(['uid' => 8, 'balance_minor' => 50]);
        app(BetCheckoutService::class)->checkoutExistingOrder(8, $order, 2);

        $this->postJson('/api/bet/payment/callback', [
            'order_id' => $order->id,
            'status' => 'paid',
        ])->assertOk();

        $this->postJson('/api/bet/payment/callback', [
            'order_id' => $order->id,
            'status' => 'paid',
        ])->assertOk()->assertJsonPath('errorCode', 0);
    }

    public function test_callback_rejects_when_callback_token_mismatch(): void
    {
        config()->set('bet_agg.payment.callback_token', 'secret-cb');

        $this->postJson('/api/bet/payment/callback', [
            'order_id' => 1,
            'status' => 'paid',
        ], ['X-Payment-Callback-Token' => 'wrong'])
            ->assertStatus(403)
            ->assertJsonPath('errorCode', 40301);
    }
}
