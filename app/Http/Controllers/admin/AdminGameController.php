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
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));
        $sort = (string) $request->query('sort', 'id');
        if (! in_array($sort, ['id', 'starts_at'], true)) {
            $sort = 'id';
        }
        $dir = strtolower((string) $request->query('dir', ''));
        if (! in_array($dir, ['asc', 'desc'], true)) {
            $dir = $sort === 'starts_at' ? 'asc' : 'desc';
        }
        $statusFilter = GameAdminService::parseOptionalStatusFilter($request->query('status'));

        $list = $this->gameAdmin->paginateIndexList(
            $statusFilter,
            $sort,
            $dir,
            $perPage,
            $request->url(),
            max(1, (int) $request->query('page', 1)),
        );

        $mallCreate = $request->boolean('mall_create');
        $mallEditId = (int) $request->query('mall_edit', 0);
        $mallSettlement = $request->boolean('mall_settlement');
        $mallSettlementGamePrefill = (int) $request->query('mall_settlement_game', 0);

        $formOptions = ($mallCreate || $mallEditId >= 1)
            ? $this->gameAdmin->formSelectOptions()
            : ['allGroups' => collect(), 'allSubjects' => collect()];

        $modal = $mallEditId >= 1
            ? $this->gameAdmin->modalEditContext($mallEditId)
            : ['game' => null, 'cms' => null, 'selectedGroupIds' => []];

        $settlementForm = $this->gameAdmin->settlementFormViewData();

        return view('admin.games.index', [
            'games' => $list['games'],
            'cmsByRawId' => $list['cmsByRawId'],
            'mallCreate' => $mallCreate,
            'mallEditId' => $mallEditId,
            'mallSettlement' => $mallSettlement,
            'mallSettlementGamePrefill' => $mallSettlementGamePrefill >= 1 ? $mallSettlementGamePrefill : null,
            'modalEditGame' => $modal['game'],
            'modalEditCms' => $modal['cms'],
            'modalEditSelectedGroups' => $modal['selectedGroupIds'],
            'allGroups' => $formOptions['allGroups'],
            'allSubjects' => $formOptions['allSubjects'],
            'listSort' => $sort,
            'listDir' => $dir,
            'listStatusFilter' => $statusFilter,
            'gamesListTruncated' => $list['gamesListTruncated'],
            'gamesListCap' => GameAdminService::LIST_CMS_MERGE_CAP,
            'settlementOpenGames' => $settlementForm['games'],
            'settlementOutcomesByGame' => $settlementForm['outcomesByGame'],
            'settlementGameSelectLabels' => $settlementForm['gameSelectLabels'],
        ]);
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
