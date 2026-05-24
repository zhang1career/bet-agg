<?php

declare(strict_types=1);

namespace Tests\Unit\Repos;

use App\Models\Game;
use App\Models\GameGroup;
use App\Models\GameSubject;
use App\Repos\mall\GameGroupRepo;
use Tests\Support\CatalogSeeder;
use Tests\TestCase;

final class GameGroupRepoTest extends TestCase
{
    private GameGroupRepo $groups;

    protected function setUp(): void
    {
        parent::setUp();
        $this->groups = app(GameGroupRepo::class);
    }

    public function test_create_and_find(): void
    {
        $group = $this->groups->create('repo-group-'.uniqid('', true));

        $found = $this->groups->find((int) $group->id);

        $this->assertNotNull($found);
        $this->assertSame($group->code, $found->code);
    }

    public function test_updateCode_persists_new_code(): void
    {
        $group = $this->groups->create('old-code');

        $this->groups->updateCode($group, 'new-code');
        $group->refresh();

        $this->assertSame('new-code', $group->code);
    }

    public function test_detachAllAndDelete_removes_group_and_pivots(): void
    {
        $group = $this->groups->create('delete-me');
        $game = CatalogSeeder::seedGame();
        $subject = new GameSubject(['name' => 'Pivot subject']);
        $subject->save();
        $group->games()->attach((int) $game->id);
        $group->subjects()->attach((int) $subject->id);

        $this->groups->detachAllAndDelete($group);

        $this->assertNull(GameGroup::query()->whereKey($group->id)->first());
        $this->assertSame(0, Game::query()->find($game->id)?->groups()->count());
    }
}
