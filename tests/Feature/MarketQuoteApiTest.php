<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MatchOutcomeCode;
use App\Models\MarketQuote;
use App\Models\MarketQuoteHist;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Paganini\Constants\ResponseConstant;
use Tests\Support\CatalogSeeder;
use Tests\TestCase;

final class MarketQuoteApiTest extends TestCase
{
    private const SNOWFLAKE_BASE = 7_200_000_000_000_000;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('api_gw.base_url', 'http://foundation.local');
        config()->set('bet_agg.foundation.base_url', 'http://foundation.local');
        config()->set('bet_agg.foundation.me_endpoint', '/api/user/me');
    }

    public function test_markets_list_includes_quote_when_requested(): void
    {
        $cat = CatalogSeeder::openHomeWinMarket();

        $res = $this->getJson('/api/bet/markets?include=quote&per_page=50');
        $res->assertOk();
        $row = collect($res->json('data.items'))->firstWhere('id', $cat['market_id']);
        $this->assertNotNull($row);
        $this->assertArrayHasKey('quote', $row);
        $this->assertSame(0, $row['quote']['total_picks']);
        $this->assertNull($row['quote']['as_of']);
    }

    public function test_market_show_includes_quote(): void
    {
        $cat = CatalogSeeder::openHomeWinMarket();

        $res = $this->getJson('/api/bet/markets/'.$cat['market_id']);
        $res->assertOk();
        $this->assertArrayHasKey('quote', $res->json('data'));
        $this->assertCount(3, $res->json('data.quote.outcomes'));
    }

    public function test_place_updates_quote_and_history(): void
    {
        Http::fake(array_merge([
            'http://foundation.local/api/user/me' => Http::response([
                'errorCode' => ResponseConstant::RET_OK,
                'data' => ['id' => 42, 'username' => 'agent'],
                'message' => '',
            ], 200),
        ], self::cmsGatewayGameFakes()));

        $cat = CatalogSeeder::openHomeWinMarket();
        $idem = self::SNOWFLAKE_BASE + 11;

        $this->place(
            ['lines' => [[
                'market_id' => $cat['market_id'],
                'outcome_code' => MatchOutcomeCode::HomeWin->value,
            ]]],
            ['X-User-Access-Token' => 'tok', 'X-Request-Id' => (string) $idem],
        )->assertCreated();

        $quoteRes = $this->getJson('/api/bet/markets/quotes?market_ids='.$cat['market_id']);
        $quoteRes->assertOk();
        $quote = $quoteRes->json('data.items.0.quote');
        $this->assertSame(1, $quote['total_picks']);
        $this->assertSame(10_000, collect($quote['outcomes'])->firstWhere('outcome_code', 'home_win')['share_bp']);

        $this->assertSame(3, MarketQuote::query()->where('mid', $cat['market_id'])->count());
        $this->assertGreaterThanOrEqual(3, MarketQuoteHist::query()->where('mid', $cat['market_id'])->count());

        $histRes = $this->getJson('/api/bet/markets/'.$cat['market_id'].'/quote/history?interval=1h');
        $histRes->assertOk();
        $this->assertSame($cat['market_id'], $histRes->json('data.market_id'));
        $this->assertNotSame([], $histRes->json('data.series.0.points'));
    }

    public function test_batch_quotes_requires_market_ids(): void
    {
        $this->getJson('/api/bet/markets/quotes')->assertUnprocessable();
    }

    public function test_quote_history_returns_404_for_unknown_market(): void
    {
        $this->getJson('/api/bet/markets/9999991/quote/history')->assertNotFound();
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
}
