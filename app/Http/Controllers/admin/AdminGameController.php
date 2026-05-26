<?php

declare(strict_types=1);

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Services\mall\GameAdminService;
use App\Support\AdminGroupIds;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminGameController extends Controller
{
    public function __construct(
        private readonly GameAdminService $gameAdmin,
    ) {}

    public function index(Request $request): View
    {
        return view('admin.games.index', $this->gameAdmin->indexViewData($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $request->validate($this->gameFormRules(forCreate: true));
        $groupIds = AdminGroupIds::fromValidated($v);
        $sideA = GameAdminService::normalizedOptionalSubjectId($v['side_a_subj_id'] ?? null);
        $sideB = GameAdminService::normalizedOptionalSubjectId($v['side_b_subj_id'] ?? null);
        $sideErrors = $this->gameAdmin->sideSubjectValidationErrors($groupIds, $sideA, $sideB);
        if ($sideErrors !== []) {
            return redirect()->route('admin.games.index', ['mall_create' => 1])
                ->withInput()
                ->withErrors($sideErrors);
        }

        $errors = $this->gameAdmin->create($v, $groupIds, $sideA, $sideB);
        if ($errors !== null) {
            return redirect()->route('admin.games.index', ['mall_create' => 1])
                ->withInput()
                ->withErrors($errors);
        }

        return redirect()->route('admin.games.index')->with('status', 'Game created.');
    }

    public function show(int $id): View
    {
        return view('admin.games.show', $this->gameAdmin->showViewData($id));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $v = $request->validate($this->gameFormRules(forCreate: false));
        $groupIds = AdminGroupIds::fromValidated($v);
        $sideA = GameAdminService::normalizedOptionalSubjectId($v['side_a_subj_id'] ?? null);
        $sideB = GameAdminService::normalizedOptionalSubjectId($v['side_b_subj_id'] ?? null);
        $sideErrors = $this->gameAdmin->sideSubjectValidationErrors($groupIds, $sideA, $sideB);
        if ($sideErrors !== []) {
            return redirect()->route('admin.games.index', ['mall_edit' => $id])
                ->withInput()
                ->withErrors($sideErrors);
        }

        $errors = $this->gameAdmin->update($id, $v, $groupIds, $sideA, $sideB);
        if ($errors !== null) {
            return redirect()->route('admin.games.index', ['mall_edit' => $id])
                ->withInput()
                ->withErrors($errors);
        }

        return redirect()->route('admin.games.index')->with('status', 'Game updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $errors = $this->gameAdmin->delete($id);
        if ($errors !== null) {
            return redirect()
                ->route('admin.games.show', $id)
                ->withErrors($errors);
        }

        return redirect()->route('admin.games.index')->with('status', 'Game deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function gameFormRules(bool $forCreate): array
    {
        $rules = [
            'banner_path' => ['nullable', 'string', 'max:1024'],
            'main_image_path' => ['nullable', 'string', 'max:1024'],
            'group_ids' => ['required', 'array', 'min:1'],
            'group_ids.*' => ['integer', Rule::exists('biz_game_group', 'id')],
            'side_a_subj_id' => ['nullable', 'integer', Rule::exists('biz_game_subject', 'id')],
            'side_b_subj_id' => ['nullable', 'integer', Rule::exists('biz_game_subject', 'id')],
            'status' => ['required', 'integer', Rule::in([
                Game::STATUS_OPEN,
                Game::STATUS_CLOSED,
                Game::STATUS_SETTLED,
                Game::STATUS_PENDING_SETTLEMENT,
            ])],
        ];
        if ($forCreate) {
            $rules['name'] = ['required', 'string', 'max:500'];
            $rules['starts_at'] = ['required', 'integer', 'min:0'];
        } else {
            $rules['name'] = ['nullable', 'string', 'max:500'];
            $rules['starts_at'] = ['nullable', 'integer', 'min:0'];
        }

        return $rules;
    }
}
