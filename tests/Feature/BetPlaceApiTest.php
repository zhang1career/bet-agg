<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BetOrderStatus;
use App\Enums\PointsHoldState;
use App\Models\BetOrder;
use App\Models\PointsBalance;
use App\Models\PointsFlow;
use App\Models\SportGame;
use App\Models\SportMarket;
use App\Models\SportSelection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Paganini\Constants\ResponseConstant;
use Tests\Support\SportSeeder;
use Tests\TestCase;

final class BetPlaceApiTest extends TestCase
{
    use RefreshDatabase;

    private const BOOKMAKER_UID = 900_001;

    private const SNOWFLAKE_BASE = 7_200_000_000_000_000;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('api_gw.base_url', 'http://foundation.local');
        config()->set('bet_agg.foundation.base_url', 'http://foundation.local');
        config()->set('bet_agg.foundation.me_endpoint', '/api/user/me');
        config()->set('bet_agg.points.bookmaker_uid', self::BOOKMAKER_UID);
    }

    private function fakeUserMe(int $userId): void
    {
        Http::fake(array_merge([
            'http://foundation.local/api/user/me' => Http::response([
                'errorCode' => ResponseConstant::RET_OK,
                'data' => ['id' => $userId, 'username' => 'agent'],
                'message' => '',
            ], 200),
        ], self::cmsGatewayGameFakes()));
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     */
    private function place(array $body, array $headers): TestResponse
    {
        $req = $this;
        foreach ($headers as $k => $v) {
            $req = $req->withHeader($k, $v);
        }

        return $req->postJson('/api/bet/place', $body);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/bet/place', [
            'lines' => [['kid' => 1, 'stake_points' => 100, 'expected_odds_millis' => 2000]],
        ])->assertStatus(401)
            ->assertJsonPath('errorCode', ResponseConstant::RET_UNAUTHORIZED);
    }

    public function test_requires_idempotency_key_header(): void
    {
        $this->fakeUserMe(42);
        $sid = SportSeeder::openSelection(2000);

        $this->place(
            ['lines' => [['kid' => $sid, 'stake_points' => 100, 'expected_odds_millis' => 2000]]],
            ['X-User-Access-Token' => 'tok'],
        )
            ->assertStatus(400)
            ->assertJsonPath('errorCode', ResponseConstant::RET_MISSING_PARAM);
    }

    public function test_rejects_non_numeric_idempotency_key(): void
    {
        $this->fakeUserMe(42);
        $sid = SportSeeder::openSelection(2000);

        $this->place(
            ['lines' => [['kid' => $sid, 'stake_points' => 100, 'expected_odds_millis' => 2000]]],
            ['X-User-Access-Token' => 'tok', 'Idempotency-Key' => 'not-a-number'],
        )
            ->assertStatus(400)
            ->assertJsonPath('errorCode', ResponseConstant::RET_MISSING_PARAM);
    }

    public function test_requires_expected_odds_millis(): void
    {
        $this->fakeUserMe(42);
        $sid = SportSeeder::openSelection(2000);

        $this->place(
            ['lines' => [['kid' => $sid, 'stake_points' => 100]]],
            ['X-User-Access-Token' => 'tok', 'Idempotency-Key' => (string) self::SNOWFLAKE_BASE],
        )
            ->assertStatus(422)
            ->assertJsonPath('errorCode', ResponseConstant::RET_INVALID_PARAM);
    }

    public function test_happy_path_atomically_creates_accepted_order_debits_balance_and_credits_book(): void
    {
        $this->fakeUserMe(42);
        PointsBalance::query()->create(['uid' => 42, 'balance' => 500]);
        PointsBalance::query()->create(['uid' => self::BOOKMAKER_UID, 'balance' => 1_000_000]);
        $sid = SportSeeder::openSelection(2000);
        $idem = self::SNOWFLAKE_BASE + 1;

        $res = $this->place(
            ['lines' => [['kid' => $sid, 'stake_points' => 100, 'expected_odds_millis' => 2000]]],
            ['X-User-Access-Token' => 'tok', 'Idempotency-Key' => (string) $idem],
        );

        $res->assertCreated()
            ->assertJsonPath('errorCode', ResponseConstant::RET_OK)
            ->assertJsonPath('data.is_replay', false)
            ->assertJsonPath('data.order.status', BetOrderStatus::Accepted->value)
            ->assertJsonPath('data.order.total_price', 100)
            ->assertJsonPath('data.order.points_held', 100);
        $this->assertArrayHasKey('_dict', $res->json('data'));
        $this->assertArrayHasKey('bet_order_status', $res->json('data._dict'));

        $orderId = (int) $res->json('data.order.id');
        $this->assertSame(400, (int) PointsBalance::query()->where('uid', 42)->value('balance'));
        $this->assertSame(1_000_100, (int) PointsBalance::query()->where('uid', self::BOOKMAKER_UID)->value('balance'));

        $this->assertSame(1, BetOrder::query()->where('uid', 42)->where('idem_key', $idem)->count());
        $this->assertSame($orderId, (int) BetOrder::query()->where('uid', 42)->where('idem_key', $idem)->value('id'));

        $this->assertSame(1, PointsFlow::query()
            ->where('uid', 42)
            ->where('oid', $orderId)
            ->where('state', PointsHoldState::Confirmed)
            ->count());
        $this->assertSame(1, PointsFlow::query()
            ->where('uid', self::BOOKMAKER_UID)
            ->where('oid', $orderId)
            ->where('state', PointsHoldState::BookStakeCredit)
            ->count());
    }

    public function test_replay_returns_original_order_and_does_not_double_debit(): void
    {
        $this->fakeUserMe(42);
        PointsBalance::query()->create(['uid' => 42, 'balance' => 500]);
        PointsBalance::query()->create(['uid' => self::BOOKMAKER_UID, 'balance' => 1_000_000]);
        $sid = SportSeeder::openSelection(2000);
        $idem = self::SNOWFLAKE_BASE + 2;

        $first = $this->place(
            ['lines' => [['kid' => $sid, 'stake_points' => 100, 'expected_odds_millis' => 2000]]],
            ['X-User-Access-Token' => 'tok', 'Idempotency-Key' => (string) $idem],
        )->assertCreated();

        $orderId = (int) $first->json('data.order.id');

        $second = $this->place(
            ['lines' => [['kid' => $sid, 'stake_points' => 100, 'expected_odds_millis' => 2000]]],
            ['X-User-Access-Token' => 'tok', 'Idempotency-Key' => (string) $idem],
        );
        $second->assertOk()
            ->assertJsonPath('data.is_replay', true)
            ->assertJsonPath('data.order.id', $orderId);

        // Balance unchanged from after the first call.
        $this->assertSame(400, (int) PointsBalance::query()->where('uid', 42)->value('balance'));
        $this->assertSame(1_000_100, (int) PointsBalance::query()->where('uid', self::BOOKMAKER_UID)->value('balance'));
        $this->assertSame(1, BetOrder::query()->where('uid', 42)->count());
    }

    public function test_two_distinct_keys_create_two_orders_on_the_same_selection(): void
    {
        $this->fakeUserMe(42);
        PointsBalance::query()->create(['uid' => 42, 'balance' => 500]);
        PointsBalance::query()->create(['uid' => self::BOOKMAKER_UID, 'balance' => 1_000_000]);
        $sid = SportSeeder::openSelection(2000);

        $idemA = self::SNOWFLAKE_BASE + 100;
        $idemB = self::SNOWFLAKE_BASE + 101;

        $this->place(
            ['lines' => [['kid' => $sid, 'stake_points' => 50, 'expected_odds_millis' => 2000]]],
            ['X-User-Access-Token' => 'tok', 'Idempotency-Key' => (string) $idemA],
        )->assertCreated();

        $this->place(
            ['lines' => [['kid' => $sid, 'stake_points' => 70, 'expected_odds_millis' => 2000]]],
            ['X-User-Access-Token' => 'tok', 'Idempotency-Key' => (string) $idemB],
        )->assertCreated();

        $this->assertSame(2, BetOrder::query()->where('uid', 42)->count());
        $this->assertEqualsCanonicalizing(
            [$idemA, $idemB],
            BetOrder::query()->where('uid', 42)->pluck('idem_key')->all(),
        );
        $this->assertSame(380, (int) PointsBalance::query()->where('uid', 42)->value('balance'));
    }

    public function test_odds_moved_returns_409_and_does_not_create_order(): void
    {
        $this->fakeUserMe(42);
        PointsBalance::query()->create(['uid' => 42, 'balance' => 500]);
        PointsBalance::query()->create(['uid' => self::BOOKMAKER_UID, 'balance' => 1_000_000]);
        $sid = SportSeeder::openSelection(2000);

        $res = $this->place(
            ['lines' => [['kid' => $sid, 'stake_points' => 100, 'expected_odds_millis' => 1900]]],
            ['X-User-Access-Token' => 'tok', 'Idempotency-Key' => (string) (self::SNOWFLAKE_BASE + 200)],
        );
        $res->assertStatus(409)
            ->assertJsonPath('errorCode', ResponseConstant::RET_INVALID_STATE);

        $this->assertSame(0, BetOrder::query()->count());
        $this->assertSame(500, (int) PointsBalance::query()->where('uid', 42)->value('balance'));
    }

    public function test_selection_not_accepting_when_parent_game_closed(): void
    {
        $this->fakeUserMe(42);
        PointsBalance::query()->create(['uid' => 42, 'balance' => 500]);
        PointsBalance::query()->create(['uid' => self::BOOKMAKER_UID, 'balance' => 1_000_000]);
        $sid = SportSeeder::openSelection(2000);
        $sel = SportSelection::query()->findOrFail($sid);
        $sel->market->game->status = SportGame::STATUS_CLOSED;
        $sel->market->game->save();

        $res = $this->place(
            ['lines' => [['kid' => $sid, 'stake_points' => 100, 'expected_odds_millis' => 2000]]],
            ['X-User-Access-Token' => 'tok', 'Idempotency-Key' => (string) (self::SNOWFLAKE_BASE + 300)],
        );
        $res->assertStatus(409)
            ->assertJsonPath('errorCode', ResponseConstant::RET_INVALID_STATE);
        $this->assertSame(0, BetOrder::query()->count());
    }

    public function test_selection_not_accepting_when_market_suspended(): void
    {
        $this->fakeUserMe(42);
        PointsBalance::query()->create(['uid' => 42, 'balance' => 500]);
        PointsBalance::query()->create(['uid' => self::BOOKMAKER_UID, 'balance' => 1_000_000]);
        $sid = SportSeeder::openSelection(2000);
        $sel = SportSelection::query()->findOrFail($sid);
        $sel->market->status = SportMarket::STATUS_SUSPENDED;
        $sel->market->save();

        $res = $this->place(
            ['lines' => [['kid' => $sid, 'stake_points' => 100, 'expected_odds_millis' => 2000]]],
            ['X-User-Access-Token' => 'tok', 'Idempotency-Key' => (string) (self::SNOWFLAKE_BASE + 301)],
        );
        $res->assertStatus(409);
        $this->assertSame(0, BetOrder::query()->count());
    }

    public function test_insufficient_points_returns_422_and_does_not_create_order(): void
    {
        $this->fakeUserMe(42);
        PointsBalance::query()->create(['uid' => 42, 'balance' => 50]);
        PointsBalance::query()->create(['uid' => self::BOOKMAKER_UID, 'balance' => 1_000_000]);
        $sid = SportSeeder::openSelection(2000);

        $res = $this->place(
            ['lines' => [['kid' => $sid, 'stake_points' => 100, 'expected_odds_millis' => 2000]]],
            ['X-User-Access-Token' => 'tok', 'Idempotency-Key' => (string) (self::SNOWFLAKE_BASE + 400)],
        );
        $res->assertStatus(422)
            ->assertJsonPath('errorCode', ResponseConstant::RET_BUSINESS_ERROR);

        $this->assertSame(0, BetOrder::query()->count());
        $this->assertSame(50, (int) PointsBalance::query()->where('uid', 42)->value('balance'));
    }

    public function test_get_orders_index_returns_dict_and_paginated_summary(): void
    {
        $this->fakeUserMe(42);
        PointsBalance::query()->create(['uid' => 42, 'balance' => 500]);
        PointsBalance::query()->create(['uid' => self::BOOKMAKER_UID, 'balance' => 1_000_000]);
        $sid = SportSeeder::openSelection(2000);

        $this->place(
            ['lines' => [['kid' => $sid, 'stake_points' => 100, 'expected_odds_millis' => 2000]]],
            ['X-User-Access-Token' => 'tok', 'Idempotency-Key' => (string) (self::SNOWFLAKE_BASE + 500)],
        )->assertCreated();

        $list = $this->withHeader('X-User-Access-Token', 'tok')->getJson('/api/bet/orders');
        $list->assertOk()
            ->assertJsonPath('data.items.0.status', BetOrderStatus::Accepted->value);
        $this->assertArrayHasKey('_dict', $list->json('data'));
    }
}
