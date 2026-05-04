<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BetOrderStatus;
use App\Enums\PointsHoldState;
use App\Models\PointsBalance;
use App\Models\PointsFlow;
use App\Models\SportGame;
use App\Services\mall\BetCheckoutService;
use App\Services\mall\BetSettlementService;
use App\Services\mall\OrderCommandService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Paganini\Constants\ResponseConstant;
use RuntimeException;
use Tests\Support\SportSeeder;
use Tests\TestCase;

final class BetSettlementFlowTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_bet_checkout_settle_winner_pays_stake_times_odds_and_book_stays_solvent(): void
    {
        PointsBalance::query()->create(['uid' => $this->bookUid(), 'balance' => 1_000_000]);
        PointsBalance::query()->create(['uid' => 42, 'balance' => 500]);

        $ids = SportSeeder::duelSelections(2500, 2000);
        $stake = 100;
        $expectedPayout = intdiv($stake * 2500, 1000);

        $order = app(OrderCommandService::class)->createDraftPendingOrder(42, [
            ['kid' => $ids['selection_a_id'], 'stake_points' => $stake],
        ]);
        $line = $order->lines->first();
        $this->assertSame($expectedPayout, (int) $line->potential_return_points);

        app(BetCheckoutService::class)->checkoutExistingOrder(42, $order);
        $this->assertBookNonNegative();
        $this->assertSame(400, (int) PointsBalance::query()->where('uid', 42)->value('balance'));
        $this->assertSame(1_000_100, (int) PointsBalance::query()->where('uid', $this->bookUid())->value('balance'));

        app(BetSettlementService::class)->applyGameResult($ids['game_local_id'], [$ids['selection_a_id']]);
        $this->assertBookNonNegative();

        $this->assertSame(650, (int) PointsBalance::query()->where('uid', 42)->value('balance'));
        $this->assertSame(1_000_100 - $expectedPayout, (int) PointsBalance::query()->where('uid', $this->bookUid())->value('balance'));

        $order->refresh();
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

    public function test_settle_loser_keeps_stake_in_book(): void
    {
        PointsBalance::query()->create(['uid' => $this->bookUid(), 'balance' => 1_000_000]);
        PointsBalance::query()->create(['uid' => 42, 'balance' => 500]);

        $ids = SportSeeder::duelSelections(2500, 2000);
        $order = app(OrderCommandService::class)->createDraftPendingOrder(42, [
            ['kid' => $ids['selection_a_id'], 'stake_points' => 100],
        ]);
        app(BetCheckoutService::class)->checkoutExistingOrder(42, $order);

        app(BetSettlementService::class)->applyGameResult($ids['game_local_id'], [$ids['selection_b_id']]);
        $this->assertBookNonNegative();

        $this->assertSame(400, (int) PointsBalance::query()->where('uid', 42)->value('balance'));
        $this->assertSame(1_000_100, (int) PointsBalance::query()->where('uid', $this->bookUid())->value('balance'));

        $order->refresh();
        $this->assertSame(BetOrderStatus::Lost, $order->status);
    }

    public function test_settlement_fails_when_book_cannot_cover_payout_and_leaves_game_open(): void
    {
        PointsBalance::query()->create(['uid' => $this->bookUid(), 'balance' => 50]);
        PointsBalance::query()->create(['uid' => 42, 'balance' => 500]);

        $ids = SportSeeder::duelSelections(5000, 2000);
        $order = app(OrderCommandService::class)->createDraftPendingOrder(42, [
            ['kid' => $ids['selection_a_id'], 'stake_points' => 100],
        ]);
        app(BetCheckoutService::class)->checkoutExistingOrder(42, $order);

        try {
            app(BetSettlementService::class)->applyGameResult($ids['game_local_id'], [$ids['selection_a_id']]);
            $this->fail('Expected RuntimeException for insufficient bookmaker liquidity.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Insufficient bookmaker liquidity', $e->getMessage());
        }

        $game = SportGame::query()->find($ids['game_local_id']);
        $this->assertNotNull($game);
        $this->assertSame(SportGame::STATUS_OPEN, (int) $game->status);

        $order->refresh();
        $this->assertSame(BetOrderStatus::Accepted, $order->status);
    }

    public function test_xxl_apply_game_settlement_runs_same_as_service(): void
    {
        config()->set('xxl.token', 'xxl-test-token');

        PointsBalance::query()->create(['uid' => $this->bookUid(), 'balance' => 1_000_000]);
        PointsBalance::query()->create(['uid' => 42, 'balance' => 500]);

        $ids = SportSeeder::duelSelections(3000, 2000);
        $order = app(OrderCommandService::class)->createDraftPendingOrder(42, [
            ['kid' => $ids['selection_a_id'], 'stake_points' => 100],
        ]);
        app(BetCheckoutService::class)->checkoutExistingOrder(42, $order);
        $expectedPayout = intdiv(100 * 3000, 1000);

        $params = json_encode([
            'game_id' => $ids['game_local_id'],
            'winning_selection_ids' => [$ids['selection_a_id']],
        ], JSON_THROW_ON_ERROR);

        $this->withHeader('XXL-JOB-ACCESS-TOKEN', 'xxl-test-token')
            ->postJson('/api/xxl-job/run', [
                'jobId' => 9101,
                'executorHandler' => 'applyGameSettlement',
                'executorParams' => $params,
                'logId' => 56_001,
                'logDateTime' => 1_700_000_000_000,
            ])
            ->assertOk()
            ->assertJsonPath('code', 200);

        $this->assertSame(400 + $expectedPayout, (int) PointsBalance::query()->where('uid', 42)->value('balance'));
        $order->refresh();
        $this->assertSame(BetOrderStatus::Won, $order->status);
    }
}
