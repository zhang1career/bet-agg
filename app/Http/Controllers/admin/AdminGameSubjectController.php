<?php

declare(strict_types=1);

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Services\mall\GameSubjectAdminService;
use App\Support\AdminGroupIds;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminGameSubjectController extends Controller
{
    public function __construct(
        private readonly GameSubjectAdminService $subjectAdmin,
    ) {}

    public function index(Request $request): View
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));
        $groupFilter = GameSubjectAdminService::parseGroupFilter($request->query('group'));
        $mallCreate = $request->boolean('mall_create');
        $mallEditId = (int) $request->query('mall_edit', 0);
        $modalSubject = $mallEditId >= 1 ? $this->subjectAdmin->findForModal($mallEditId) : null;

        return view('admin.game-subjects.index', [
            'subjects' => $this->subjectAdmin->paginateIndex($groupFilter, $perPage),
            'groups' => $this->subjectAdmin->listGroups(),
            'listGroupFilter' => $groupFilter,
            'mallCreate' => $mallCreate,
            'modalSubject' => $modalSubject,
            'modalSelectedGroupIds' => $modalSubject !== null
                ? $modalSubject->groups->pluck('id')->map(static fn ($id): int => (int) $id)->all()
                : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'name' => 'required|string|max:256',
            'icon_path' => 'nullable|string|max:1024',
            'group_ids' => 'array',
            'group_ids.*' => 'integer|exists:biz_game_group,id',
        ]);

        $this->subjectAdmin->create(
            trim((string) $v['name']),
            trim((string) ($v['icon_path'] ?? '')),
            AdminGroupIds::fromValidated($v),
        );

        return redirect()->route('admin.game-subjects.index')->with('status', '赛事主体已创建。');
    }

    public function show(int $id): View
    {
        return view('admin.game-subjects.show', [
            'subject' => $this->subjectAdmin->findForShow($id),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $v = $request->validate([
            'name' => 'required|string|max:256',
            'icon_path' => 'nullable|string|max:1024',
            'group_ids' => 'array',
            'group_ids.*' => 'integer|exists:biz_game_group,id',
        ]);

        $this->subjectAdmin->update(
            $id,
            trim((string) $v['name']),
            trim((string) ($v['icon_path'] ?? '')),
            AdminGroupIds::fromValidated($v),
        );

        return redirect()->route('admin.game-subjects.index')->with('status', '已保存。');
    }

    public function destroy(int $id): RedirectResponse
    {
        $errors = $this->subjectAdmin->delete($id);
        if ($errors !== null) {
            return redirect()
                ->route('admin.game-subjects.show', $id)
                ->withErrors($errors);
        }

        return redirect()->route('admin.game-subjects.index')->with('status', '已删除。');
    }
}
