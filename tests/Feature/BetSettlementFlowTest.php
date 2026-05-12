<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BetOrderStatus;
use App\Enums\MatchOutcomeCode;
use App\Enums\PointsFlowKind;
use App\Models\BetOrder;
use App\Models\PointsBalance;
use App\Models\PointsFlow;
use App\Services\mall\BetPlaceService;
use App\Services\mall\BetSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Paganini\Batch\DTO\BatchRunResult;
use Paganini\Batch\Enums\JobStatus;
use Paganini\Constants\ResponseConstant;
use Tests\Support\CatalogSeeder;
use Tests\TestCase;

final class BetSettlementFlowTest extends TestCase
{
    use RefreshDatabase;

    private const SNOWFLAKE_BASE = 7_300_000_000_000_000;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(array_merge([
            'http://foundation.local/api/user/me' => Http::response([
                'errorCode' => ResponseConstant::RET_OK,
                'data' => ['id' => 42, 'username' => 'buyer'],
                'message' => '',
            ], 200),
        ], self::cmsGatewayGameFakes()));

        config()->set('api_gw.base_url', 'http://foundation.local');
        config()->set('bet_agg.foundation.base_url', 'http://foundation.local');
        config()->set('bet_agg.foundation.me_endpoint', '/api/user/me');
        config()->set('bet_agg.points.delta_win', 10);
        config()->set('bet_agg.points.delta_lose', 5);
    }

    /**
     * @return array{game_local_id: int, market_id: int}
     */
    private function seedFixture(): array
    {
        return CatalogSeeder::oneXTwoSettlement();
    }

    /**
     * @param  array{game_local_id: int, market_id: int}  $ids
     */
    private function submitWithIds(array $ids, int $idemSeed, string $outcomeCode = 'home_win'): int
    {
        $result = app(BetPlaceService::class)->place(42, self::SNOWFLAKE_BASE + $idemSeed, [
            [
                'market_id' => $ids['market_id'],
                'outcome_code' => $outcomeCode,
            ],
        ]);

        return (int) $result['order']->id;
    }

    /**
     * @param  list<string>  $winners
     * @param  list<string>  $voids
     */
    private function recordAndSettle(int $gameId, array $winners, array $voids = []): BatchRunResult
    {
        $svc = app(BetSettlementService::class);
        $svc->recordPendingSettlement($gameId, $winners, $voids);

        return $svc->applyGameResult($gameId);
    }

    public function test_winner_increments_reputation(): void
    {
        $ids = $this->seedFixture();
        $orderId = $this->submitWithIds($ids, 1, MatchOutcomeCode::HomeWin->value);

        $result = $this->recordAndSettle($ids['game_local_id'], [MatchOutcomeCode::HomeWin->value]);
        $this->assertSame(JobStatus::Completed, $result->status);

        $order = BetOrder::query()->whereKey($orderId)->firstOrFail();
        $this->assertSame(BetOrderStatus::Won, $order->status);

        $this->assertSame(10, (int) PointsBalance::query()->where('uid', 42)->value('balance'));
        $this->assertSame(1, (int) PointsFlow::query()
            ->where('oid', $orderId)
            ->where('state', PointsFlowKind::WinCredit->value)
            ->count());
    }

    public function test_loser_decrements_reputation(): void
    {
        PointsBalance::query()->create(['uid' => 42, 'balance' => 100, 'ct' => 1, 'ut' => 1]);

        $ids = $this->seedFixture();
        $orderId = $this->submitWithIds($ids, 2, MatchOutcomeCode::HomeWin->value);

        $result = $this->recordAndSettle($ids['game_local_id'], [MatchOutcomeCode::AwayWin->value]);
        $this->assertSame(JobStatus::Completed, $result->status);

        $order = BetOrder::query()->whereKey($orderId)->firstOrFail();
        $this->assertSame(BetOrderStatus::Lost, $order->status);

        $this->assertSame(95, (int) PointsBalance::query()->where('uid', 42)->value('balance'));
        $this->assertSame(1, (int) PointsFlow::query()
            ->where('oid', $orderId)
            ->where('state', PointsFlowKind::LossDebit->value)
            ->count());
    }

    public function test_void_does_not_change_reputation(): void
    {
        PointsBalance::query()->create(['uid' => 42, 'balance' => 50, 'ct' => 1, 'ut' => 1]);

        $ids = $this->seedFixture();
        $orderId = $this->submitWithIds($ids, 3, MatchOutcomeCode::HomeWin->value);

        $result = $this->recordAndSettle($ids['game_local_id'], [], MatchOutcomeCode::allValues());
        $this->assertSame(JobStatus::Completed, $result->status);

        $order = BetOrder::query()->whereKey($orderId)->firstOrFail();
        $this->assertSame(BetOrderStatus::Void, $order->status);

        $this->assertSame(50, (int) PointsBalance::query()->where('uid', 42)->value('balance'));
        $this->assertSame(0, (int) PointsFlow::query()->where('oid', $orderId)->count());
    }

    public function test_second_apply_is_idempotent_for_reputation(): void
    {
        $ids = $this->seedFixture();
        $this->submitWithIds($ids, 4, MatchOutcomeCode::HomeWin->value);

        $first = $this->recordAndSettle($ids['game_local_id'], [MatchOutcomeCode::HomeWin->value]);
        $this->assertSame(JobStatus::Completed, $first->status);

        $scoreAfterFirst = (int) PointsBalance::query()->where('uid', 42)->value('balance');

        $second = app(BetSettlementService::class)->applyGameResult($ids['game_local_id']);
        $this->assertSame(JobStatus::Completed, $second->status);
        $this->assertSame(0, $second->total);

        $this->assertSame($scoreAfterFirst, (int) PointsBalance::query()->where('uid', 42)->value('balance'));
    }

    public function test_xxl_apply_game_settlement_runs_same_as_service(): void
    {
        config()->set('xxl.token', 'xxl-test-token');

        $ids = $this->seedFixture();
        $this->submitWithIds($ids, 5, MatchOutcomeCode::HomeWin->value);

        app(BetSettlementService::class)->recordPendingSettlement(
            $ids['game_local_id'],
            [MatchOutcomeCode::HomeWin->value],
            [],
        );

        $this->withHeader('XXL-JOB-ACCESS-TOKEN', 'xxl-test-token')
            ->postJson('/internal/xxl-job/run', [
                'jobId' => 9101,
                'executorHandler' => 'applyGameSettlement',
                'executorParams' => '',
                'logId' => 56_001,
                'logDateTime' => 1_700_000_000_000,
            ])
            ->assertOk()
            ->assertJsonPath('code', 200);

        $this->assertSame(10, (int) PointsBalance::query()->where('uid', 42)->value('balance'));
    }
}
