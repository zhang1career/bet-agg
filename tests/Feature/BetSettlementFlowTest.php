<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BetOrderStatus;
use App\Enums\MatchOutcomeCode;
use App\Enums\PointsHoldState;
use App\Models\BetOrder;
use App\Models\Game;
use App\Models\PointsBalance;
use App\Models\PointsFlow;
use App\Services\mall\BetPlaceService;
use App\Services\mall\BetSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
        config()->set('bet_agg.points.bookmaker_uid', (int) env('BET_BOOKMAKER_UID', 900_001));
    }

    private function bookUid(): int
    {
        return (int) config('bet_agg.points.bookmaker_uid');
    }

    private function assertBookNonNegative(): void
    {
        $row = PointsBalance::query()->where('uid', $this->bookUid())->first();
        if ($row === null) {
            return;
        }
        $this->assertGreaterThanOrEqual(0, (int) $row->balance, 'bookmaker balance must stay non-negative');
    }

    /**
     * @param  array{
     *     game_local_id: int,
     *     market_id: int,
     *     home_odds_millis: int,
     *     draw_odds_millis: int,
     *     away_odds_millis: int,
     * }  $ids
     */
    private function placeBet(
        int $uid,
        array $ids,
        int $stake,
        int $expectedOddsMillis,
        int $idemSeed,
        string $outcomeCode = 'home_win',
    ): int {
        $result = app(BetPlaceService::class)->place($uid, self::SNOWFLAKE_BASE + $idemSeed, [
            [
                'market_id' => $ids['market_id'],
                'outcome_code' => $outcomeCode,
                'stake_points' => $stake,
                'expected_odds_millis' => $expectedOddsMillis,
            ],
        ]);

        return (int) $result['order']->id;
    }

    public function test_winner_bet_pays_stake_times_odds_and_book_stays_solvent(): void
    {
        PointsBalance::query()->create(['uid' => $this->bookUid(), 'balance' => 1_000_000]);
        PointsBalance::query()->create(['uid' => 42, 'balance' => 500]);

        $ids = CatalogSeeder::oneXTwoSettlement(2500, 2000, 2000);
        $stake = 100;
        $expectedPayout = intdiv($stake * 2500, 1000);

        $orderId = $this->placeBet(42, $ids, $stake, 2500, 1, MatchOutcomeCode::HomeWin->value);
        $this->assertBookNonNegative();
        $this->assertSame(400, (int) PointsBalance::query()->where('uid', 42)->value('balance'));
        $this->assertSame(1_000_100, (int) PointsBalance::query()->where('uid', $this->bookUid())->value('balance'));

        $result = app(BetSettlementService::class)->applyGameResult($ids['game_local_id'], [MatchOutcomeCode::HomeWin->value]);
        $this->assertSame(JobStatus::Completed, $result->status);
        $this->assertSame(1, $result->successCount);
        $this->assertSame(0, $result->failureCount);
        $this->assertBookNonNegative();

        $this->assertSame(400 + $expectedPayout, (int) PointsBalance::query()->where('uid', 42)->value('balance'));
        $this->assertSame(1_000_100 - $expectedPayout, (int) PointsBalance::query()->where('uid', $this->bookUid())->value('balance'));

        $order = BetOrder::query()->whereKey($orderId)->firstOrFail();
        $this->assertSame(BetOrderStatus::Won, $order->status);

        $this->assertSame(1, (int) PointsFlow::query()
            ->where('uid', $this->bookUid())
            ->where('state', PointsHoldState::BookStakeCredit)
            ->count());
        $this->assertSame(1, (int) PointsFlow::query()
            ->where('uid', $this->bookUid())
            ->where('state', PointsHoldState::BookPayoutDebit)
            ->count());
    }

    public function test_loser_bet_keeps_stake_in_book(): void
    {
        PointsBalance::query()->create(['uid' => $this->bookUid(), 'balance' => 1_000_000]);
        PointsBalance::query()->create(['uid' => 42, 'balance' => 500]);

        $ids = CatalogSeeder::oneXTwoSettlement(2500, 2000, 2000);
        $orderId = $this->placeBet(42, $ids, 100, 2500, 2, MatchOutcomeCode::HomeWin->value);

        $result = app(BetSettlementService::class)->applyGameResult($ids['game_local_id'], [MatchOutcomeCode::AwayWin->value]);
        $this->assertSame(JobStatus::Completed, $result->status);
        $this->assertBookNonNegative();

        $this->assertSame(400, (int) PointsBalance::query()->where('uid', 42)->value('balance'));
        $this->assertSame(1_000_100, (int) PointsBalance::query()->where('uid', $this->bookUid())->value('balance'));

        $order = BetOrder::query()->whereKey($orderId)->firstOrFail();
        $this->assertSame(BetOrderStatus::Lost, $order->status);
    }

    public function test_voided_bet_refunds_stake_from_bookmaker_to_user(): void
    {
        PointsBalance::query()->create(['uid' => $this->bookUid(), 'balance' => 1_000_000]);
        PointsBalance::query()->create(['uid' => 42, 'balance' => 500]);

        $ids = CatalogSeeder::oneXTwoSettlement(2500, 2000, 2000);
        $orderId = $this->placeBet(42, $ids, 100, 2500, 3, MatchOutcomeCode::HomeWin->value);

        $result = app(BetSettlementService::class)->applyGameResult(
            $ids['game_local_id'],
            [],
            MatchOutcomeCode::allValues(),
        );
        $this->assertSame(JobStatus::Completed, $result->status);
        $this->assertBookNonNegative();

        $this->assertSame(500, (int) PointsBalance::query()->where('uid', 42)->value('balance'));
        $this->assertSame(1_000_000, (int) PointsBalance::query()->where('uid', $this->bookUid())->value('balance'));

        $order = BetOrder::query()->whereKey($orderId)->firstOrFail();
        $this->assertSame(BetOrderStatus::Void, $order->status);

        $this->assertSame(1, (int) PointsFlow::query()
            ->where('uid', 42)
            ->where('oid', $orderId)
            ->where('state', PointsHoldState::SettlementRefund)
            ->count());
        $this->assertSame(1, (int) PointsFlow::query()
            ->where('uid', $this->bookUid())
            ->where('oid', $orderId)
            ->where('state', PointsHoldState::BookStakeRefund)
            ->count());
    }

    public function test_settlement_marks_failed_orders_when_bookmaker_cannot_pay_and_keeps_game_settled(): void
    {
        PointsBalance::query()->create(['uid' => $this->bookUid(), 'balance' => 50]);
        PointsBalance::query()->create(['uid' => 42, 'balance' => 500]);

        $ids = CatalogSeeder::oneXTwoSettlement(5000, 2000, 2000);
        $orderId = $this->placeBet(42, $ids, 100, 5000, 4, MatchOutcomeCode::HomeWin->value);

        $result = app(BetSettlementService::class)->applyGameResult($ids['game_local_id'], [MatchOutcomeCode::HomeWin->value]);
        $this->assertSame(JobStatus::Partial, $result->status);
        $this->assertSame(0, $result->successCount);
        $this->assertSame(1, $result->failureCount);
        $this->assertBookNonNegative();

        $game = Game::query()->find($ids['game_local_id']);
        $this->assertNotNull($game);
        $this->assertSame(Game::STATUS_SETTLED, (int) $game->status);

        $order = BetOrder::query()->whereKey($orderId)->firstOrFail();
        $this->assertSame(BetOrderStatus::SettlementFailed, $order->status);
    }

    public function test_failed_settlement_can_be_retried_after_topping_up_bookmaker(): void
    {
        PointsBalance::query()->create(['uid' => $this->bookUid(), 'balance' => 50]);
        PointsBalance::query()->create(['uid' => 42, 'balance' => 500]);

        $ids = CatalogSeeder::oneXTwoSettlement(5000, 2000, 2000);
        $orderId = $this->placeBet(42, $ids, 100, 5000, 5, MatchOutcomeCode::HomeWin->value);

        $first = app(BetSettlementService::class)->applyGameResult($ids['game_local_id'], [MatchOutcomeCode::HomeWin->value]);
        $this->assertSame(JobStatus::Partial, $first->status);
        $this->assertSame(BetOrderStatus::SettlementFailed, BetOrder::query()->whereKey($orderId)->firstOrFail()->status);

        PointsBalance::query()->where('uid', $this->bookUid())->update(['balance' => 1_000_000]);

        $second = app(BetSettlementService::class)->applyGameResult($ids['game_local_id'], [MatchOutcomeCode::HomeWin->value]);
        $this->assertSame(JobStatus::Completed, $second->status);
        $this->assertSame(1, $second->successCount);

        $order = BetOrder::query()->whereKey($orderId)->firstOrFail();
        $this->assertSame(BetOrderStatus::Won, $order->status);
    }

    public function test_applying_result_twice_after_full_completion_emits_empty_batch_and_does_not_double_pay(): void
    {
        PointsBalance::query()->create(['uid' => $this->bookUid(), 'balance' => 1_000_000]);
        PointsBalance::query()->create(['uid' => 42, 'balance' => 500]);

        $ids = CatalogSeeder::oneXTwoSettlement(2500, 2000, 2000);
        $this->placeBet(42, $ids, 100, 2500, 6, MatchOutcomeCode::HomeWin->value);

        $first = app(BetSettlementService::class)->applyGameResult($ids['game_local_id'], [MatchOutcomeCode::HomeWin->value]);
        $this->assertSame(JobStatus::Completed, $first->status);
        $this->assertSame(1, $first->successCount);

        $userBalanceAfterFirst = (int) PointsBalance::query()->where('uid', 42)->value('balance');
        $bookBalanceAfterFirst = (int) PointsBalance::query()->where('uid', $this->bookUid())->value('balance');

        $second = app(BetSettlementService::class)->applyGameResult($ids['game_local_id'], [MatchOutcomeCode::HomeWin->value]);
        $this->assertSame(JobStatus::Completed, $second->status);
        $this->assertSame(0, $second->total, 'second pass should have zero items to process');

        $this->assertSame($userBalanceAfterFirst, (int) PointsBalance::query()->where('uid', 42)->value('balance'));
        $this->assertSame($bookBalanceAfterFirst, (int) PointsBalance::query()->where('uid', $this->bookUid())->value('balance'));
    }

    public function test_xxl_apply_game_settlement_runs_same_as_service(): void
    {
        config()->set('xxl.token', 'xxl-test-token');

        PointsBalance::query()->create(['uid' => $this->bookUid(), 'balance' => 1_000_000]);
        PointsBalance::query()->create(['uid' => 42, 'balance' => 500]);

        $ids = CatalogSeeder::oneXTwoSettlement(3000, 2000, 2000);
        $this->placeBet(42, $ids, 100, 3000, 7, MatchOutcomeCode::HomeWin->value);
        $expectedPayout = intdiv(100 * 3000, 1000);

        $params = json_encode([
            'game_id' => $ids['game_local_id'],
            'winning_outcomes' => [MatchOutcomeCode::HomeWin->value],
            'void_outcomes' => [],
        ], JSON_THROW_ON_ERROR);

        $this->withHeader('XXL-JOB-ACCESS-TOKEN', 'xxl-test-token')
            ->postJson('/internal/xxl-job/run', [
                'jobId' => 9101,
                'executorHandler' => 'applyGameSettlement',
                'executorParams' => $params,
                'logId' => 56_001,
                'logDateTime' => 1_700_000_000_000,
            ])
            ->assertOk()
            ->assertJsonPath('code', 200);

        $this->assertSame(400 + $expectedPayout, (int) PointsBalance::query()->where('uid', 42)->value('balance'));
    }
}
