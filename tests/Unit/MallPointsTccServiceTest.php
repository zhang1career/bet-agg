<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\BetOrderStatus;
use App\Enums\CheckoutPhase;
use App\Enums\PointsHoldState;
use App\Models\BetOrder;
use App\Models\MallPointsBalance;
use App\Models\PointsFlow;
use App\Services\mall\MallPointsTccService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MallPointsTccServiceTest extends TestCase
{
    use RefreshDatabase;

    private function seedOrderForUser(int $uid): BetOrder
    {
        return BetOrder::query()->create([
            'uid' => $uid,
            'status' => BetOrderStatus::Pending,
            'total_price' => 1000,
            'points_deduct_minor' => 0,
            'cash_payable_minor' => 0,
            'checkout_phase' => CheckoutPhase::None,
            'ext_inventory' => false,
            'ext_id' => '',
        ]);
    }

    public function test_try_confirm_cancel_lifecycle(): void
    {
        MallPointsBalance::query()->create(['uid' => 1, 'balance_minor' => 1000]);
        $orderA = $this->seedOrderForUser(1);

        $svc = app(MallPointsTccService::class);
        $svc->tryFreeze(1, 100, (int) $orderA->id);

        $hold = PointsFlow::query()->where('uid', 1)->where('oid', $orderA->id)->first();
        $this->assertNotNull($hold);
        $this->assertSame(PointsHoldState::TrySucceeded, $hold->state);

        $svc->confirmHoldForBetOrder((int) $orderA->id);
        $hold->refresh();
        $this->assertSame(PointsHoldState::Confirmed, $hold->state);

        MallPointsBalance::query()->create(['uid' => 2, 'balance_minor' => 500]);
        $orderB = $this->seedOrderForUser(2);
        $svc->tryFreeze(2, 50, (int) $orderB->id);
        $svc->cancelHoldForBetOrder((int) $orderB->id);
        $cancelled = PointsFlow::query()->where('uid', 2)->where('oid', $orderB->id)->first();
        $this->assertNotNull($cancelled);
        $this->assertSame(PointsHoldState::RolledBack, $cancelled->state);
    }

    public function test_try_insufficient_balance_does_not_create_hold(): void
    {
        MallPointsBalance::query()->create(['uid' => 3, 'balance_minor' => 5]);
        $order = $this->seedOrderForUser(3);

        try {
            app(MallPointsTccService::class)->tryFreeze(3, 100, (int) $order->id);
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Insufficient points', $e->getMessage());
        }

        $this->assertNull(PointsFlow::query()->where('oid', $order->id)->first());
    }

    public function test_try_same_order_is_idempotent_after_try_succeeded(): void
    {
        MallPointsBalance::query()->create(['uid' => 4, 'balance_minor' => 200]);
        $order = $this->seedOrderForUser(4);

        $svc = app(MallPointsTccService::class);
        $svc->tryFreeze(4, 50, (int) $order->id);
        $svc->tryFreeze(4, 50, (int) $order->id);

        $this->assertSame(1, (int) PointsFlow::query()->where('oid', $order->id)->where('state', PointsHoldState::TrySucceeded)->count());
        $row = MallPointsBalance::query()->where('uid', 4)->first();
        $this->assertSame(150, (int) $row->balance_minor);
    }
}
