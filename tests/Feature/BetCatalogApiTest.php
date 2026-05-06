<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Market;
use App\Models\Selection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CatalogSeeder;
use Tests\TestCase;

final class BetCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_games_list_filters_by_status_and_returns_local_id_with_cms_id(): void
    {
        $open = new Game([
            'raw_id' => 201,
            'status' => Game::STATUS_OPEN,
        ]);
        $open->save();

        (new Game([
            'raw_id' => 202,
            'status' => Game::STATUS_CLOSED,
        ]))->save();

        $res = $this->getJson('/api/bet/games?status=1&per_page=50');
        $res->assertOk();
        $items = $res->json('data.items');
        $this->assertCount(1, $items);
        $row = $items[0];
        $this->assertSame((int) $open->id, $row['id']);
        $this->assertSame(201, $row['cms_id']);
        $this->assertSame(Game::STATUS_OPEN, $row['status']);
        $this->assertSame('CMS game 201', $row['title']);
        $this->assertSame('CMS description 201', $row['description']);
        $this->assertSame('cms/banner.png', $row['banner']);
        $this->assertArrayNotHasKey('name', $row);
        $this->assertArrayNotHasKey('main_media', $row);
        $this->assertArrayNotHasKey('start_at', $row);
        $this->assertArrayHasKey('_dict', $res->json('data'));
        $this->assertArrayHasKey('game_status', $res->json('data._dict'));
    }

    public function test_games_show_uses_local_id_not_raw_id(): void
    {
        $g = new Game([
            'raw_id' => 303,
            'status' => Game::STATUS_CLOSED,
        ]);
        $g->save();

        $res = $this->getJson('/api/bet/games/'.$g->id);
        $res->assertOk();
        $this->assertSame((int) $g->id, $res->json('data.id'));
        $this->assertSame(303, $res->json('data.cms_id'));
        $this->assertSame('CMS game 303', $res->json('data.title'));
        $this->assertSame('CMS description 303', $res->json('data.description'));
        $this->assertSame('cms/banner.png', $res->json('data.banner'));
        $this->assertSame('cms/cover.png', $res->json('data.main_media'));
        $this->assertSame(1_700_000_000_000, $res->json('data.start_at'));
        $this->assertArrayNotHasKey('name', $res->json('data'));
        $this->assertSame([], $res->json('data.winning_selection_ids'));
    }

    public function test_games_show_returns_404_when_local_id_unknown(): void
    {
        $this->getJson('/api/bet/games/9999991')->assertNotFound();
    }

    public function test_games_list_sort_by_id(): void
    {
        $a = new Game(['raw_id' => 301, 'status' => Game::STATUS_OPEN]);
        $a->save();
        $b = new Game(['raw_id' => 302, 'status' => Game::STATUS_OPEN]);
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
        CatalogSeeder::openSelection(2000);
        $other = CatalogSeeder::openSelection(1700);
        $otherSelection = Selection::query()->findOrFail($other);
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
        CatalogSeeder::openSelection(2000);

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
        $sid = CatalogSeeder::openSelection(2000);
        $sel = Selection::query()->findOrFail($sid);
        $marketId = (int) $sel->market_id;

        $res = $this->getJson('/api/bet/markets/'.$marketId);
        $res->assertOk();
        $this->assertSame($marketId, $res->json('data.id'));
        $this->assertSame('Full-time 1X2', $res->json('data.name'));
        $this->assertArrayHasKey('game', $res->json('data'));
        $this->assertSame((int) $sel->market->game->id, $res->json('data.game.id'));
        $rawId = (int) $sel->market->game->raw_id;
        $this->assertSame('CMS game '.$rawId, $res->json('data.game.title'));
        $this->assertArrayNotHasKey('main_media', $res->json('data.game'));
        $this->assertArrayNotHasKey('start_at', $res->json('data.game'));
        $this->assertArrayHasKey('selections', $res->json('data'));
        $selections = $res->json('data.selections');
        $this->assertCount(1, $selections);
        $this->assertSame($sid, $selections[0]['id']);
        $this->assertSame(2000, $selections[0]['current_odds_millis']);
    }

    public function test_markets_list_only_under_open_games_when_no_filters_passed(): void
    {
        $sid = CatalogSeeder::openSelection(2000);
        $sel = Selection::query()->findOrFail($sid);
        // Close the parent game; the market should be hidden by default.
        $sel->market->game->status = Game::STATUS_CLOSED;
        $sel->market->game->save();

        $res = $this->getJson('/api/bet/markets');
        $res->assertOk();
        $this->assertCount(0, $res->json('data.items'));
    }

    public function test_market_can_be_filtered_by_status_even_under_closed_game(): void
    {
        // When agent passes an explicit status filter, parent-game gating is skipped so settled
        // markets remain visible for history/replay.
        $sid = CatalogSeeder::openSelection(2000);
        $sel = Selection::query()->findOrFail($sid);
        $sel->market->status = Market::STATUS_SETTLED;
        $sel->market->save();
        $sel->market->game->status = Game::STATUS_SETTLED;
        $sel->market->game->save();

        $res = $this->getJson('/api/bet/markets?status=3');
        $res->assertOk();
        $this->assertCount(1, $res->json('data.items'));
        $this->assertSame((int) $sel->market->id, $res->json('data.items.0.id'));
    }
}
