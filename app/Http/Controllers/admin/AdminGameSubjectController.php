<?php

declare(strict_types=1);

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameGroup;
use App\Models\GameSubject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminGameSubjectController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));
        $subjects = GameSubject::query()
            ->withCount('groups')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.game-subjects.index', ['subjects' => $subjects]);
    }

    public function create(): View
    {
        return view('admin.game-subjects.create', [
            'groups' => GameGroup::query()->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'name' => 'required|string|max:256',
            'group_ids' => 'array',
            'group_ids.*' => 'integer|exists:biz_game_group,id',
        ]);
        $name = trim((string) $v['name']);
        $groupIds = array_values(array_unique(array_map('intval', $v['group_ids'] ?? [])));

        $subject = new GameSubject(['name' => $name]);
        $subject->save();
        $subject->groups()->sync($groupIds);

        return redirect()->route('admin.game-subjects.index')->with('status', '赛事主体已创建。');
    }

    public function show(GameSubject $gameSubject): View
    {
        $gameSubject->load(['groups' => static fn ($q) => $q->orderBy('code')]);

        return view('admin.game-subjects.show', ['subject' => $gameSubject]);
    }

    public function edit(GameSubject $gameSubject): View
    {
        $gameSubject->load('groups');
        $selected = $gameSubject->groups->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        return view('admin.game-subjects.edit', [
            'subject' => $gameSubject,
            'groups' => GameGroup::query()->orderBy('code')->get(),
            'selectedGroupIds' => $selected,
        ]);
    }

    public function update(Request $request, GameSubject $gameSubject): RedirectResponse
    {
        $v = $request->validate([
            'name' => 'required|string|max:256',
            'group_ids' => 'array',
            'group_ids.*' => 'integer|exists:biz_game_group,id',
        ]);
        $gameSubject->name = trim((string) $v['name']);
        $gameSubject->save();

        $groupIds = array_values(array_unique(array_map('intval', $v['group_ids'] ?? [])));
        $gameSubject->groups()->sync($groupIds);

        return redirect()->route('admin.game-subjects.index')->with('status', '已保存。');
    }

    public function destroy(GameSubject $gameSubject): RedirectResponse
    {
        if (Game::query()->where('side_a_subject_id', $gameSubject->id)->orWhere('side_b_subject_id', $gameSubject->id)->exists()) {
            return redirect()
                ->route('admin.game-subjects.show', $gameSubject)
                ->withErrors(['delete' => '有赛事仍引用该主体为 A/B 方，无法删除。']);
        }

        $gameSubject->groups()->detach();
        $gameSubject->delete();

        return redirect()->route('admin.game-subjects.index')->with('status', '已删除。');
    }
}
