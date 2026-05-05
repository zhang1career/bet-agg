<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SportGame;
use App\Models\SportMarket;
use App\Models\SportSelection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SportSeeder;
use Tests\TestCase;

final class BetCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_games_list_filters_by_status_and_returns_local_id_with_cms_id(): void
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

        $res = $this->getJson('/api/bet/games?status=1&per_page=50');
        $res->assertOk();
        $items = $res->json('data.items');
        $this->assertCount(1, $items);
        $row = $items[0];
        $this->assertSame((int) $open->id, $row['id']);
        $this->assertSame(201, $row['cms_id']);
        $this->assertSame(SportGame::STATUS_OPEN, $row['status']);
        $this->assertArrayNotHasKey('name', $row);
        $this->assertArrayNotHasKey('main_media', $row);
        $this->assertArrayHasKey('_dict', $res->json('data'));
        $this->assertArrayHasKey('sport_game_status', $res->json('data._dict'));
    }

    public function test_games_show_uses_local_id_not_raw_id(): void
    {
        $g = new SportGame([
            'raw_id' => 303,
            'status' => SportGame::STATUS_CLOSED,
        ]);
        $g->save();

        $res = $this->getJson('/api/bet/games/'.$g->id);
        $res->assertOk();
        $this->assertSame((int) $g->id, $res->json('data.id'));
        $this->assertSame(303, $res->json('data.cms_id'));
        $this->assertArrayNotHasKey('name', $res->json('data'));
        $this->assertArrayNotHasKey('main_media', $res->json('data'));
        $this->assertSame([], $res->json('data.winning_selection_ids'));
    }

    public function test_games_show_returns_404_when_local_id_unknown(): void
    {
        $this->getJson('/api/bet/games/9999991')->assertNotFound();
    }

    public function test_games_list_sort_by_id(): void
    {
        $a = new SportGame(['raw_id' => 301, 'status' => SportGame::STATUS_OPEN]);
        $a->save();
        $b = new SportGame(['raw_id' => 302, 'status' => SportGame::STATUS_OPEN]);
        $b->save();

        $res = $this->getJson('/api/bet/games?status=1&sort=id');
        $res->assertOk();
        $items = $res->json('data.items');
        $ids = array_column($items, 'id');
        $sorted = $ids;
        sort($sorted);
        $this->assertSame($sorted, $ids);
    }

    public function test_markets_list_embeds_selections_by_default_and_filters_by_local_game_id(): void
    {
        SportSeeder::openSelection(2000);
        $other = SportSeeder::openSelection(1700);
        $otherSelection = SportSelection::query()->findOrFail($other);
        $otherMarket = $otherSelection->market;

        $resAll = $this->getJson('/api/bet/markets?per_page=50');
        $resAll->assertOk();
        $items = $resAll->json('data.items');
        $this->assertGreaterThanOrEqual(2, count($items));
        foreach ($items as $row) {
            $this->assertArrayHasKey('selections', $row);
            $this->assertNotEmpty($row['selections']);
            $this->assertArrayHasKey('current_odds_millis', $row['selections'][0]);
        }
        $this->assertArrayHasKey('_dict', $resAll->json('data'));

        $localGameId = (int) $otherMarket->game->id;
        $resOne = $this->getJson('/api/bet/markets?game_id='.$localGameId);
        $resOne->assertOk();
        $filtered = $resOne->json('data.items');
        $this->assertCount(1, $filtered);
        $this->assertSame($localGameId, $filtered[0]['game_id']);
    }

    public function test_markets_list_can_disable_selections_inlining(): void
    {
        SportSeeder::openSelection(2000);

        $res = $this->getJson('/api/bet/markets?include_selections=0');
        $res->assertOk();
        $items = $res->json('data.items');
        $this->assertNotEmpty($items);
        foreach ($items as $row) {
            $this->assertArrayNotHasKey('selections', $row);
        }
    }

    public function test_market_show_includes_selections_and_nested_game(): void
    {
        $sid = SportSeeder::openSelection(2000);
        $sel = SportSelection::query()->findOrFail($sid);
        $marketId = (int) $sel->market_id;

        $res = $this->getJson('/api/bet/markets/'.$marketId);
        $res->assertOk();
        $this->assertSame($marketId, $res->json('data.id'));
        $this->assertSame('Full-time 1X2', $res->json('data.name'));
        $this->assertArrayHasKey('game', $res->json('data'));
        $this->assertSame((int) $sel->market->game->id, $res->json('data.game.id'));
        $this->assertArrayHasKey('selections', $res->json('data'));
        $selections = $res->json('data.selections');
        $this->assertCount(1, $selections);
        $this->assertSame($sid, $selections[0]['id']);
        $this->assertSame(2000, $selections[0]['current_odds_millis']);
    }

    public function test_markets_list_only_under_open_games_when_no_filters_passed(): void
    {
        $sid = SportSeeder::openSelection(2000);
        $sel = SportSelection::query()->findOrFail($sid);
        // Close the parent game; the market should be hidden by default.
        $sel->market->game->status = SportGame::STATUS_CLOSED;
        $sel->market->game->save();

        $res = $this->getJson('/api/bet/markets');
        $res->assertOk();
        $this->assertCount(0, $res->json('data.items'));
    }

    public function test_market_can_be_filtered_by_status_even_under_closed_game(): void
    {
        // When agent passes an explicit status filter, parent-game gating is skipped so settled
        // markets remain visible for history/replay.
        $sid = SportSeeder::openSelection(2000);
        $sel = SportSelection::query()->findOrFail($sid);
        $sel->market->status = SportMarket::STATUS_SETTLED;
        $sel->market->save();
        $sel->market->game->status = SportGame::STATUS_SETTLED;
        $sel->market->game->save();

        $res = $this->getJson('/api/bet/markets?status=3');
        $res->assertOk();
        $this->assertCount(1, $res->json('data.items'));
        $this->assertSame((int) $sel->market->id, $res->json('data.items.0.id'));
    }
}
