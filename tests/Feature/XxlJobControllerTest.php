<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BetOrderStatus;
use App\Models\BetOrder;
use App\Services\mall\OrderCommandService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SportSeeder;
use Tests\TestCase;

final class XxlJobControllerTest extends TestCase
{
    use RefreshDatabase;

    private const TRIGGER_LOG_DATE_TIME_MS = 1_700_000_000_000;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('xxl.token', 'xxl-test-token');
        config()->set('xxl.admin_address', 'http://xxl-admin.test');
    }

    public function test_beat_rejects_invalid_token(): void
    {
        $this->getJson('/api/xxl-job/beat')
            ->assertOk()
            ->assertJsonPath('code', 500);
    }

    public function test_beat_accepts_valid_token(): void
    {
        $this->withHeader('XXL-JOB-ACCESS-TOKEN', 'xxl-test-token')
            ->getJson('/api/xxl-job/beat')
            ->assertOk()
            ->assertJsonPath('code', 200);
    }

    public function test_run_dispatches_close_expired_orders_and_cancels_pending(): void
    {
        config()->set('bet_agg.orders.pending_payment_timeout_ms', 60_000);

        $sid = SportSeeder::openSelection(2000);
        $order = app(OrderCommandService::class)
            ->createDraftPendingOrder(7, [['selection_id' => $sid, 'stake_points' => 1]]);

        BetOrder::query()->where('id', $order->id)->update([
            'ct' => BetOrder::nowMillis() - 120_000,
            'ut' => BetOrder::nowMillis() - 120_000,
        ]);

        $this->withHeader('XXL-JOB-ACCESS-TOKEN', 'xxl-test-token')
            ->postJson('/api/xxl-job/run', [
                'jobId' => 9001,
                'executorHandler' => 'closeExpiredOrders',
                'executorParams' => '',
                'logId' => 55_001,
                'logDateTime' => self::TRIGGER_LOG_DATE_TIME_MS,
            ])
            ->assertOk()
            ->assertJsonPath('code', 200);

        $order->refresh();
        $this->assertSame(BetOrderStatus::Cancelled, $order->status);
    }

    public function test_run_returns_500_when_handler_unknown(): void
    {
        $this->withHeader('XXL-JOB-ACCESS-TOKEN', 'xxl-test-token')
            ->postJson('/api/xxl-job/run', [
                'jobId' => 9002,
                'executorHandler' => 'nonexistentHandler',
                'logId' => 55_002,
                'logDateTime' => self::TRIGGER_LOG_DATE_TIME_MS,
            ])
            ->assertOk()
            ->assertJsonPath('code', 500);
    }

    public function test_run_returns_500_when_log_date_time_invalid(): void
    {
        $this->withHeader('XXL-JOB-ACCESS-TOKEN', 'xxl-test-token')
            ->postJson('/api/xxl-job/run', [
                'jobId' => 9003,
                'executorHandler' => 'closeExpiredOrders',
                'logId' => 55_003,
                'logDateTime' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('code', 500);
    }

    public function test_kill_removes_lock_file_when_present(): void
    {
        $storage = Storage::disk('local');
        $storage->put('jobs/777.job', '777');

        $this->withHeader('XXL-JOB-ACCESS-TOKEN', 'xxl-test-token')
            ->postJson('/api/xxl-job/kill', ['jobId' => 777])
            ->assertOk()
            ->assertJsonPath('code', 200);

        $this->assertFalse($storage->exists('jobs/777.job'));
    }

    public function test_kill_idempotent_when_file_missing(): void
    {
        $this->withHeader('XXL-JOB-ACCESS-TOKEN', 'xxl-test-token')
            ->postJson('/api/xxl-job/kill', ['jobId' => 999999])
            ->assertOk()
            ->assertJsonPath('code', 200);
    }
}
