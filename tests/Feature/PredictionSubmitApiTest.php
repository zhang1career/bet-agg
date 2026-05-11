<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BetOrderStatus;
use App\Enums\MatchOutcomeCode;
use App\Models\BetOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Paganini\Constants\ResponseConstant;
use Tests\Support\CatalogSeeder;
use Tests\TestCase;

final class PredictionSubmitApiTest extends TestCase
{
    use RefreshDatabase;

    private const SNOWFLAKE_BASE = 7_200_000_000_000_000;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('api_gw.base_url', 'http://foundation.local');
        config()->set('bet_agg.foundation.base_url', 'http://foundation.local');
        config()->set('bet_agg.foundation.me_endpoint', '/api/user/me');
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
    private function submit(array $body, array $headers): TestResponse
    {
        $req = $this;
        foreach ($headers as $k => $v) {
            $req = $req->withHeader($k, $v);
        }

        return $req->postJson('/api/bet/submit', $body);
    }

    /**
     * @return array{market_id: int, outcome_code: string}
     */
    private function line(int $marketId, string $outcomeCode): array
    {
        return [
            'market_id' => $marketId,
            'outcome_code' => $outcomeCode,
        ];
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/bet/submit', [
            'lines' => [$this->line(1, MatchOutcomeCode::HomeWin->value)],
        ])->assertStatus(401)
            ->assertJsonPath('errorCode', ResponseConstant::RET_UNAUTHORIZED);
    }

    public function test_requires_x_request_id_header(): void
    {
        $this->fakeUserMe(42);
        $cat = CatalogSeeder::openHomeWinMarket();

        $this->submit(
            ['lines' => [$this->line($cat['market_id'], $cat['outcome_code'])]],
            ['X-User-Access-Token' => 'tok'],
        )
            ->assertStatus(400)
            ->assertJsonPath('errorCode', ResponseConstant::RET_MISSING_PARAM);
    }

    public function test_rejects_non_numeric_x_request_id(): void
    {
        $this->fakeUserMe(42);
        $cat = CatalogSeeder::openHomeWinMarket();

        $this->submit(
            ['lines' => [$this->line($cat['market_id'], $cat['outcome_code'])]],
            ['X-User-Access-Token' => 'tok', 'X-Request-Id' => 'not-a-number'],
        )
            ->assertStatus(400)
            ->assertJsonPath('errorCode', ResponseConstant::RET_MISSING_PARAM);
    }

    public function test_happy_path_creates_recorded_order(): void
    {
        $this->fakeUserMe(42);
        $cat = CatalogSeeder::openHomeWinMarket();
        $idem = self::SNOWFLAKE_BASE + 1;

        $res = $this->submit(
            ['lines' => [$this->line($cat['market_id'], $cat['outcome_code'])]],
            ['X-User-Access-Token' => 'tok', 'X-Request-Id' => (string) $idem],
        );

        $res->assertCreated()
            ->assertJsonPath('errorCode', ResponseConstant::RET_OK)
            ->assertJsonPath('data.is_replay', false)
            ->assertJsonPath('data.order.status', BetOrderStatus::Accepted->value);
        $this->assertArrayHasKey('_dict', $res->json('data'));
        $this->assertArrayHasKey('bet_order_status', $res->json('data._dict'));

        $orderId = (int) $res->json('data.order.id');
        $this->assertSame(1, BetOrder::query()->where('uid', 42)->where('idem_key', $idem)->count());
        $this->assertSame($orderId, (int) BetOrder::query()->where('uid', 42)->where('idem_key', $idem)->value('id'));
    }

    public function test_replay_returns_original_order(): void
    {
        $this->fakeUserMe(42);
        $cat = CatalogSeeder::openHomeWinMarket();
        $idem = self::SNOWFLAKE_BASE + 2;

        $first = $this->submit(
            ['lines' => [$this->line($cat['market_id'], $cat['outcome_code'])]],
            ['X-User-Access-Token' => 'tok', 'X-Request-Id' => (string) $idem],
        )->assertCreated();

        $orderId = (int) $first->json('data.order.id');

        $second = $this->submit(
            ['lines' => [$this->line($cat['market_id'], $cat['outcome_code'])]],
            ['X-User-Access-Token' => 'tok', 'X-Request-Id' => (string) $idem],
        );

        $second->assertOk()
            ->assertJsonPath('data.is_replay', true)
            ->assertJsonPath('data.order.id', $orderId);

        $this->assertSame(1, BetOrder::query()->where('uid', 42)->count());
    }
}
