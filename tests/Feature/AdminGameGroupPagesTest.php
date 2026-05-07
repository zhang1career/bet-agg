<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminGameGroupPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_game_groups_crud_flow(): void
    {
        $resIndex = $this->get('/admin/game-groups');
        $resIndex->assertOk();

        $store = $this->post('/admin/game-groups', [
            'code' => 'fixture-group',
        ]);
        $store->assertRedirect();
        $group = GameGroup::query()->where('code', 'fixture-group')->first();
        $this->assertNotNull($group);

        $game = new Game(['raw_id' => 909, 'status' => Game::STATUS_OPEN]);
        $game->save();
        $group->games()->attach((int) $game->id);

        $show = $this->get('/admin/game-groups/'.$group->id);
        $show->assertOk();
        $show->assertSee('909', false);

        $this->put('/admin/game-groups/'.$group->id, ['code' => 'fixture-renamed'])
            ->assertRedirect();
        $this->assertSame('fixture-renamed', $group->fresh()->code);

        $this->delete('/admin/game-groups/'.$group->fresh()->id)->assertRedirect();
        $this->assertNull(GameGroup::query()->find($group->id));
        $this->assertNotNull(Game::query()->find($game->id));
    }

    public function test_admin_game_groups_rejects_invalid_code_chars(): void
    {
        $this->post('/admin/game-groups', [
            'code' => 'has space',
        ])->assertSessionHasErrors(['code']);
    }
}
