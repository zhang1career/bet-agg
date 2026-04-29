<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PointsHoldState;
use App\Models\MallPointsBalance;
use App\Models\PointsFlow;
use App\Services\mall\MallPointsTccService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MallPointsTccServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_try_confirm_cancel_lifecycle(): void
    {
        MallPointsBalance::query()->create(['uid' => 1, 'balance_minor' => 1000]);

        $svc = app(MallPointsTccService::class);
        $svc->tryFreeze(1, 100, 5, 'idem-1');

        $hold = PointsFlow::query()->where('tcc_idem_key', 'idem-1')->first();
        $this->assertNotNull($hold);
        $this->assertSame(PointsHoldState::TrySucceeded, $hold->state);

        $svc->confirm('idem-1');
        $hold->refresh();
        $this->assertSame(PointsHoldState::Confirmed, $hold->state);

        MallPointsBalance::query()->create(['uid' => 2, 'balance_minor' => 500]);
        $svc->tryFreeze(2, 50, 0, 'idem-cancel-1');
        $svc->cancel('idem-cancel-1');
        $cancelled = PointsFlow::query()->where('tcc_idem_key', 'idem-cancel-1')->first();
        $this->assertNotNull($cancelled);
        $this->assertSame(PointsHoldState::RolledBack, $cancelled->state);
    }

    public function test_try_insufficient_balance_does_not_create_hold(): void
    {
        MallPointsBalance::query()->create(['uid' => 3, 'balance_minor' => 5]);

        try {
            app(MallPointsTccService::class)->tryFreeze(3, 100, 0, 'idem-low');
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Insufficient points', $e->getMessage());
        }

        $this->assertNull(PointsFlow::query()->where('tcc_idem_key', 'idem-low')->first());
    }

    public function test_try_duplicate_key_is_idempotent_after_try_succeeded(): void
    {
        MallPointsBalance::query()->create(['uid' => 4, 'balance_minor' => 200]);

        $svc = app(MallPointsTccService::class);
        $svc->tryFreeze(4, 50, 0, 'idem-dup');
        $svc->tryFreeze(4, 50, 0, 'idem-dup');

        $this->assertSame(1, (int) PointsFlow::query()->where('tcc_idem_key', 'idem-dup')->count());
        $row = MallPointsBalance::query()->where('uid', 4)->first();
        $this->assertSame(150, (int) $row->balance_minor);
    }
}
