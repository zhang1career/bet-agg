<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\BetOrderStatus;
use App\Enums\CheckoutPhase;
use App\Enums\PointsHoldState;
use App\Models\BetOrder;
use App\Models\PointsBalance;
use App\Models\PointsFlow;
use App\Services\mall\PointsTccService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PointsTccServiceTest extends TestCase
{
    use RefreshDatabase;

    private function seedOrderForUser(int $uid): BetOrder
    {
        return BetOrder::query()->create([
            'uid' => $uid,
            'status' => BetOrderStatus::Pending,
            'total_price' => 1000,
            'points_held' => 0,
            'checkout_phase' => CheckoutPhase::None,
            'ext_inventory' => false,
            'ext_id' => '',
        ]);
    }

    public function test_try_confirm_cancel_lifecycle(): void
    {
        PointsBalance::query()->create(['uid' => 1, 'balance' => 1000]);
        $orderA = $this->seedOrderForUser(1);

        $svc = app(PointsTccService::class);
        $svc->tryFreeze(1, 100, (int) $orderA->id);

        $hold = PointsFlow::query()->where('uid', 1)->where('oid', $orderA->id)->first();
        $this->assertNotNull($hold);
        $this->assertSame(PointsHoldState::TrySucceeded, $hold->state);

        $svc->confirmHoldForBetOrder((int) $orderA->id);
        $hold->refresh();
        $this->assertSame(PointsHoldState::Confirmed, $hold->state);

        PointsBalance::query()->create(['uid' => 2, 'balance' => 500]);
        $orderB = $this->seedOrderForUser(2);
        $svc->tryFreeze(2, 50, (int) $orderB->id);
        $svc->cancelHoldForBetOrder((int) $orderB->id);
        $cancelled = PointsFlow::query()->where('uid', 2)->where('oid', $orderB->id)->first();
        $this->assertNotNull($cancelled);
        $this->assertSame(PointsHoldState::RolledBack, $cancelled->state);
    }

    public function test_try_insufficient_balance_does_not_create_hold(): void
    {
        PointsBalance::query()->create(['uid' => 3, 'balance' => 5]);
        $order = $this->seedOrderForUser(3);

        try {
            app(PointsTccService::class)->tryFreeze(3, 100, (int) $order->id);
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Insufficient points', $e->getMessage());
        }

        $this->assertNull(PointsFlow::query()->where('oid', $order->id)->first());
    }

    public function test_try_same_order_is_idempotent_after_try_succeeded(): void
    {
        PointsBalance::query()->create(['uid' => 4, 'balance' => 200]);
        $order = $this->seedOrderForUser(4);

        $svc = app(PointsTccService::class);
        $svc->tryFreeze(4, 50, (int) $order->id);
        $svc->tryFreeze(4, 50, (int) $order->id);

        $this->assertSame(1, (int) PointsFlow::query()->where('oid', $order->id)->where('state', PointsHoldState::TrySucceeded)->count());
        $row = PointsBalance::query()->where('uid', 4)->first();
        $this->assertSame(150, (int) $row->balance);
    }
}
