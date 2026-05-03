<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SportGame;
use App\Models\SportSelection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SportSeeder;
use Tests\TestCase;

final class BetCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_games_list_includes_only_open_games_with_cms_payload(): void
    {
        $open = new SportGame([
            'raw_id' => 201,
            'status' => SportGame::STATUS_OPEN,
        ]);
        $open->save();

        (new SportGame([
            'raw_id' => 202,
            'status' => SportGame::STATUS_CLOSED,
        ]))->save();

        $res = $this->getJson('/api/bet/games?per_page=50');
        $res->assertOk();
        $ids = collect($res->json('data.items'))->pluck('id')->all();
        $this->assertContains(201, $ids);
        $this->assertCount(1, $ids);
        $row = collect($res->json('data.items'))->firstWhere('id', 201);
        $this->assertSame('CMS game 201', $row['name']);
    }

    public function test_game_show_returns_404_for_missing_cms_game(): void
    {
        $this->getJson('/api/bet/games/9999991')->assertNotFound();
    }

    public function test_game_show_returns_row(): void
    {
        $g = new SportGame([
            'raw_id' => 303,
            'status' => SportGame::STATUS_CLOSED,
        ]);
        $g->save();

        $res = $this->getJson('/api/bet/games/303');
        $res->assertOk();
        $this->assertSame(303, $res->json('data.id'));
        $this->assertSame('CMS game 303', $res->json('data.name'));
        $this->assertSame('cms/cover.png', $res->json('data.main_media'));
        $this->assertSame([], $res->json('data.winning_selection_ids'));
    }

    public function test_markets_list_filters_by_game_id(): void
    {
        SportSeeder::openSelection(2000);
        $g2 = new SportGame([
            'raw_id' => 9_999,
            'status' => SportGame::STATUS_OPEN,
        ]);
        $g2->save();

        $all = $this->getJson('/api/bet/markets?per_page=50');
        $all->assertOk();
        $this->assertGreaterThanOrEqual(1, count($all->json('data.items')));

        $empty = $this->getJson('/api/bet/markets?game_id=9999');
        $empty->assertOk();
        $this->assertCount(0, $empty->json('data.items'));
    }

    public function test_market_show_returns_nested_game(): void
    {
        $sid = SportSeeder::openSelection(2000);
        $sel = SportSelection::query()->findOrFail($sid);
        $marketId = (int) $sel->market_id;

        $res = $this->getJson('/api/bet/markets/'.$marketId);
        $res->assertOk();
        $this->assertSame($marketId, $res->json('data.id'));
        $this->assertSame('Full-time 1X2', $res->json('data.name'));
        $this->assertArrayHasKey('game', $res->json('data'));
    }

    public function test_selections_list_accepts_market_id(): void
    {
        $sid = SportSeeder::openSelection(2000);
        $sel = SportSelection::query()->findOrFail($sid);
        $mid = (int) $sel->market_id;

        $res = $this->getJson('/api/bet/selections?market_id='.$mid);
        $res->assertOk();
        $items = $res->json('data.items');
        $this->assertNotEmpty($items);
        foreach ($items as $row) {
            $this->assertSame($mid, $row['market']['id']);
        }
    }

    public function test_selections_list_rejects_invalid_market_id(): void
    {
        $this->getJson('/api/bet/selections?market_id=0')->assertUnprocessable();
    }
}
