<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BetOrderStatus;
use App\Enums\CheckoutPhase;
use App\Models\BetOrder;
use App\Services\mall\OrderCommandService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\SportSeeder;
use Tests\TestCase;

final class PaymentCallbackControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_callback_marks_order_paid_after_tcc_confirm(): void
    {
        config()->set('api_gw.base_url', 'http://gw.test');

        Http::fake([
            'http://gw.test/api/tcc/tx/12345/confirm' => Http::response([
                'errorCode' => 0,
                'data' => [],
                'message' => '',
            ], 200),
        ]);

        $sid = SportSeeder::openSelection(2000);
        $order = app(OrderCommandService::class)
            ->createDraftPendingOrder(7, [['selection_id' => $sid, 'stake_points' => 1]]);
        $order->checkout_phase = CheckoutPhase::AwaitPayment;
        $order->cash_payable_minor = (int) $order->total_price;
        $order->tcc_idem_key = 12345;
        $order->save();

        $this->postJson('/api/bet/payment/callback', [
            'order_id' => $order->id,
            'status' => 'paid',
        ])->assertOk()->assertJsonPath('errorCode', 0)
            ->assertJsonPath('data.status', BetOrderStatus::Accepted->value);

        $fresh = BetOrder::query()->find($order->id);
        $this->assertNotNull($fresh);
        $this->assertSame(BetOrderStatus::Accepted, $fresh->status);
        $this->assertSame(CheckoutPhase::Completed, $fresh->checkout_phase);
    }

    public function test_callback_is_idempotent_when_already_paid(): void
    {
        config()->set('api_gw.base_url', 'http://gw.test');
        Http::fake([
            'http://gw.test/api/tcc/tx/999/confirm' => Http::response([
                'errorCode' => 0,
                'data' => [],
                'message' => '',
            ], 200),
        ]);

        $sid = SportSeeder::openSelection(2000);
        $order = app(OrderCommandService::class)
            ->createDraftPendingOrder(8, [['selection_id' => $sid, 'stake_points' => 1]]);
        $order->checkout_phase = CheckoutPhase::AwaitPayment;
        $order->cash_payable_minor = (int) $order->total_price;
        $order->tcc_idem_key = 999;
        $order->save();

        $this->postJson('/api/bet/payment/callback', [
            'order_id' => $order->id,
            'status' => 'paid',
        ])->assertOk();

        Http::fake();

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
