<?php

declare(strict_types=1);

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminGameGroupController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));
        $groups = GameGroup::query()
            ->withCount('games')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.game-groups.index', [
            'groups' => $groups,
        ]);
    }

    public function create(): View
    {
        return view('admin.game-groups.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'code' => ['required', 'string', 'max:192', 'regex:/^[a-zA-Z0-9._-]+$/', Rule::unique('biz_game_group', 'code')],
        ]);

        $group = new GameGroup(['code' => (string) $v['code']]);
        $group->save();

        return redirect()->route('admin.game-groups.show', $group)->with('status', '分组已创建。');
    }

    public function show(GameGroup $gameGroup): View
    {
        $gameGroup->load([
            'games' => static fn ($q) => $q->orderBy('biz_game.id'),
        ]);

        $linkedIds = $gameGroup->games->pluck('id');
        $availableGames = Game::query()
            ->when($linkedIds->isNotEmpty(), static fn ($q) => $q->whereNotIn('id', $linkedIds->all()))
            ->orderByDesc('id')
            ->get();

        return view('admin.game-groups.show', [
            'gameGroup' => $gameGroup,
            'availableGames' => $availableGames,
        ]);
    }

    public function edit(GameGroup $gameGroup): View
    {
        return view('admin.game-groups.edit', [
            'gameGroup' => $gameGroup,
        ]);
    }

    public function update(Request $request, GameGroup $gameGroup): RedirectResponse
    {
        $v = $request->validate([
            'code' => [
                'required',
                'string',
                'max:192',
                'regex:/^[a-zA-Z0-9._-]+$/',
                Rule::unique('biz_game_group', 'code')->ignore($gameGroup->id),
            ],
        ]);

        $gameGroup->code = (string) $v['code'];
        $gameGroup->save();

        return redirect()->route('admin.game-groups.show', $gameGroup)->with('status', '分组已更新。');
    }

    public function destroy(GameGroup $gameGroup): RedirectResponse
    {
        $gameGroup->games()->detach();
        $gameGroup->delete();

        return redirect()->route('admin.game-groups.index')->with('status', '分组已删除。');
    }

    public function storeGame(Request $request, GameGroup $gameGroup): RedirectResponse
    {
        $v = $request->validate([
            'game_id' => ['required', 'integer', Rule::exists('biz_game', 'id')],
        ]);
        $gameId = (int) $v['game_id'];

        if ($gameGroup->games()->whereKey($gameId)->exists()) {
            return redirect()
                ->route('admin.game-groups.show', $gameGroup)
                ->withErrors(['game_id' => '该赛事已在当前分组中。']);
        }

        $gameGroup->games()->attach($gameId);

        return redirect()->route('admin.game-groups.show', $gameGroup)->with('status', '已添加赛事。');
    }

    public function destroyGame(GameGroup $gameGroup, Game $game): RedirectResponse
    {
        if (! $gameGroup->games()->whereKey($game->id)->exists()) {
            return redirect()
                ->route('admin.game-groups.show', $gameGroup)
                ->withErrors(['detach' => '该赛事未关联到此分组。']);
        }

        $gameGroup->games()->detach($game->id);

        return redirect()->route('admin.game-groups.show', $gameGroup)->with('status', '已移除赛事。');
    }
}
