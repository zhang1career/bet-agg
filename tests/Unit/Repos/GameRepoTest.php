<?php

declare(strict_types=1);

namespace Tests\Unit\Repos;

use App\Models\Game;
use App\Models\GameGroup;
use App\Repos\mall\GameRepo;
use App\Services\mall\GameListFilter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\Support\CatalogSeeder;
use Tests\TestCase;

final class GameRepoTest extends TestCase
{
    private GameRepo $games;

    protected function setUp(): void
    {
        parent::setUp();
        $this->games = app(GameRepo::class);
    }

    public function test_createForAdmin_persists_game(): void
    {
        $game = $this->games->createForAdmin(1001, 10, 20, Game::STATUS_OPEN);

        $this->assertGreaterThan(0, $game->id);
        $this->assertSame(1001, $game->raw_id);
        $this->assertSame(10, $game->side_a_subj_id);
        $this->assertSame(20, $game->side_b_subj_id);
        $this->assertSame(Game::STATUS_OPEN, $game->status);
    }

    public function test_existsReferencingSubject_matches_either_side(): void
    {
        $fixture = CatalogSeeder::oneXTwoSettlement();
        $game = Game::query()->whereKey($fixture['game_local_id'])->firstOrFail();

        $this->assertTrue($this->games->existsReferencingSubject((int) $game->side_a_subj_id));
        $this->assertTrue($this->games->existsReferencingSubject((int) $game->side_b_subj_id));
        $this->assertFalse($this->games->existsReferencingSubject(999_999));
    }

    public function test_listOpenWithBothSides_excludes_incomplete_games(): void
    {
        CatalogSeeder::oneXTwoSettlement();
        CatalogSeeder::seedGame(null, null);

        $rows = $this->games->listOpenWithBothSides();

        $this->assertCount(1, $rows);
        $this->assertNotNull($rows->first()?->side_a_subj_id);
        $this->assertNotNull($rows->first()?->side_b_subj_id);
    }

    public function test_listPendingSettlement_returns_only_pending_rows(): void
    {
        $open = CatalogSeeder::seedGame(1, 2);
        $pending = CatalogSeeder::seedGame(3, 4);
        $this->games->markPendingSettlement($pending, ['winners' => ['home_win']], 1_700_000_000_000);

        $rows = $this->games->listPendingSettlement();

        $this->assertCount(1, $rows);
        $this->assertSame((int) $pending->id, (int) $rows->first()?->id);
        $this->assertSame(Game::STATUS_OPEN, $open->fresh()?->status);
    }

    public function test_markPendingSettlement_and_markSettled_update_status(): void
    {
        $game = CatalogSeeder::seedGame(1, 2);
        $now = 1_700_000_000_001;

        $this->games->markPendingSettlement($game, ['winners' => ['draw'], 'voids' => []], $now);

        $game->refresh();
        $this->assertSame(Game::STATUS_PENDING_SETTLEMENT, $game->status);
        $this->assertSame(['winners' => ['draw'], 'voids' => []], $game->settle_outcomes);
        $this->assertGreaterThan(0, $game->ut);

        $this->games->markSettled($game, $now + 1);
        $game->refresh();
        $this->assertSame(Game::STATUS_SETTLED, $game->status);
    }

    public function test_syncGroups_attaches_pivot_rows(): void
    {
        $game = CatalogSeeder::seedGame();
        $group = new GameGroup(['code' => 'repo-g-'.uniqid('', true)]);
        $group->save();

        $this->games->syncGroups($game, [(int) $group->id]);

        $game->refresh();
        $this->assertSame([(int) $group->id], $game->groups->pluck('id')->all());
    }

    public function test_paginateForCatalog_filters_by_group_code(): void
    {
        $suffix = uniqid('', true);
        $groupA = new GameGroup(['code' => 'grp-a-'.$suffix]);
        $groupA->save();
        $groupB = new GameGroup(['code' => 'grp-b-'.$suffix]);
        $groupB->save();

        $gameA = CatalogSeeder::seedGame();
        $gameB = CatalogSeeder::seedGame();
        $this->games->syncGroups($gameA, [(int) $groupA->id]);
        $this->games->syncGroups($gameB, [(int) $groupB->id]);

        $filter = new GameListFilter([], null, null, 'grp-a-'.$suffix);
        $page = $this->games->paginateForCatalog($filter, 1, 20);

        $this->assertSame(1, $page->total());
        $this->assertSame((int) $gameA->id, (int) $page->items()[0]->id);
    }

    public function test_findOrFail_throws_when_missing(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->games->findOrFail(999_999);
    }
}
