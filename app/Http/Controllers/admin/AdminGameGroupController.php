<?php

declare(strict_types=1);

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\GameGroup;
use App\Services\mall\serv_fd\CmsGameClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class AdminGameGroupController extends Controller
{
    public function __construct(
        private readonly CmsGameClient $cmsGameClient,
    ) {}

    public function index(Request $request): View
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));
        $groups = GameGroup::query()
            ->withCount('games')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $mallCreate = $request->boolean('mall_create');
        $mallEditId = (int) $request->query('mall_edit', 0);
        $modalGroup = null;
        if ($mallEditId >= 1) {
            $modalGroup = GameGroup::query()->find($mallEditId);
        }

        return view('admin.game-groups.index', [
            'groups' => $groups,
            'mallCreate' => $mallCreate,
            'modalGroup' => $modalGroup,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'code' => ['required', 'string', 'max:192', 'regex:/^[a-zA-Z0-9._-]+$/', Rule::unique('biz_game_group', 'code')],
        ]);

        $group = new GameGroup(['code' => (string) $v['code']]);
        $group->save();

        return redirect()->route('admin.game-groups.index')->with('status', '分组已创建。');
    }

    public function show(GameGroup $gameGroup): View
    {
        $gameGroup->load([
            'games' => static fn ($q) => $q->orderBy('biz_game.id'),
        ]);

        $cmsByRawId = [];
        $rawIds = $gameGroup->games
            ->map(static fn ($g): int => (int) $g->raw_id)
            ->unique()
            ->filter(static fn (int $r): bool => $r >= 1)
            ->values()
            ->all();
        if ($rawIds !== []) {
            $cmsByRawId = $this->cmsGameClient->findManyById($rawIds);
        }

        return view('admin.game-groups.show', [
            'gameGroup' => $gameGroup,
            'cmsByRawId' => $cmsByRawId,
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

        return redirect()->route('admin.game-groups.index')->with('status', '分组已更新。');
    }

    public function destroy(GameGroup $gameGroup): RedirectResponse
    {
        $gameGroup->games()->detach();
        $gameGroup->subjects()->detach();
        $gameGroup->delete();

        return redirect()->route('admin.game-groups.index')->with('status', '分组已删除。');
    }
}
