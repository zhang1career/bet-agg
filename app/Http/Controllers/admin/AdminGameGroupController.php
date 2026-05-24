<?php

declare(strict_types=1);

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Services\mall\GameGroupAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminGameGroupController extends Controller
{
    public function __construct(
        private readonly GameGroupAdminService $groupAdmin,
    ) {}

    public function index(Request $request): View
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));
        $mallCreate = $request->boolean('mall_create');
        $mallEditId = (int) $request->query('mall_edit', 0);

        return view('admin.game-groups.index', [
            'groups' => $this->groupAdmin->paginateIndex($perPage),
            'mallCreate' => $mallCreate,
            'modalGroup' => $mallEditId >= 1 ? $this->groupAdmin->findForModal($mallEditId) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'code' => ['required', 'string', 'max:192', 'regex:/^[a-zA-Z0-9._-]+$/', Rule::unique('biz_game_group', 'code')],
        ]);

        $this->groupAdmin->create((string) $v['code']);

        return redirect()->route('admin.game-groups.index')->with('status', '分组已创建。');
    }

    public function show(int $id): View
    {
        $gameGroup = $this->groupAdmin->findForShow($id);

        return view('admin.game-groups.show', [
            'gameGroup' => $gameGroup,
            'cmsByRawId' => $this->groupAdmin->cmsByRawIdsForGroup($gameGroup),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $v = $request->validate([
            'code' => [
                'required',
                'string',
                'max:192',
                'regex:/^[a-zA-Z0-9._-]+$/',
                Rule::unique('biz_game_group', 'code')->ignore($id),
            ],
        ]);

        $this->groupAdmin->update($id, (string) $v['code']);

        return redirect()->route('admin.game-groups.index')->with('status', '分组已更新。');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->groupAdmin->delete($id);

        return redirect()->route('admin.game-groups.index')->with('status', '分组已删除。');
    }
}
