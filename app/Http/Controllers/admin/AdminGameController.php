<?php

declare(strict_types=1);

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameGroup;
use App\Models\GameSubject;
use App\Services\mall\AdminSettlementFormViewData;
use App\Services\mall\serv_fd\CmsGameClient;
use App\Services\mall\SettlementConsoleOverviewService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Paganini\Aggregation\Exceptions\DownstreamServiceException;
use Throwable;

class AdminGameController extends Controller
{
    private const GAMES_LIST_CMS_MERGE_CAP = 2500;

    public function __construct(
        private readonly CmsGameClient $cmsGameClient,
        private readonly SettlementConsoleOverviewService $settlementOverview,
        private readonly AdminSettlementFormViewData $settlementFormViewData,
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
        $statusFilter = $this->parseOptionalStatusFilter($request->query('status'));

        $baseQuery = Game::query()->withCount('markets');
        if ($statusFilter !== null) {
            $baseQuery->where('status', $statusFilter);
        }

        $useCmsMergePath = $sort === 'starts_at';

        $gamesListTruncated = false;
        if ($useCmsMergePath) {
            [$games, $gamesListTruncated] = $this->paginateGamesWithCmsStartsAt(
                $request,
                $baseQuery,
                $sort,
                $dir,
                $perPage,
            );
        } else {
            $games = (clone $baseQuery)
                ->orderBy('id', $dir === 'asc' ? 'asc' : 'desc')
                ->paginate($perPage)
                ->withQueryString();
        }

        $cmsByRawId = [];
        try {
            $rawIds = $games
                ->getCollection()
                ->map(static fn (Game $g): int => (int) $g->raw_id)
                ->unique()
                ->filter(static fn (int $r): bool => $r >= 1)
                ->values()
                ->all();
            if ($rawIds !== []) {
                $cmsByRawId = $this->cmsGameClient->findManyById($rawIds);
            }
        } catch (Throwable) {
        }

        $mallCreate = $request->boolean('mall_create');
        $mallEditId = (int) $request->query('mall_edit', 0);
        $mallSettlement = $request->boolean('mall_settlement');
        $mallSettlementGamePrefill = (int) $request->query('mall_settlement_game', 0);

        $modalEditGame = null;
        $modalEditCms = null;
        $modalEditSelectedGroups = [];
        $allGroups = collect();
        $allSubjects = collect();

        if ($mallCreate || ($mallEditId >= 1)) {
            $allGroups = GameGroup::query()->orderBy('code')->get();
            $allSubjects = GameSubject::query()->with('groups')->orderBy('name')->get();
        }

        if ($mallEditId >= 1) {
            $modalEditGame = Game::query()->with('groups')->find($mallEditId);
            if ($modalEditGame instanceof Game) {
                $modalEditCms = $this->fetchCmsGameOrNull((int) $modalEditGame->raw_id);
                $modalEditSelectedGroups = $modalEditGame->groups->pluck('id')->map(static fn ($id): int => (int) $id)->all();
            } else {
                $modalEditGame = null;
            }
        }

        $settlementForm = $this->settlementFormViewData->build();

        return view('admin.games.index', [
            'games' => $games,
            'cmsByRawId' => $cmsByRawId,
            'mallCreate' => $mallCreate,
            'mallEditId' => $mallEditId,
            'mallSettlement' => $mallSettlement,
            'mallSettlementGamePrefill' => $mallSettlementGamePrefill >= 1 ? $mallSettlementGamePrefill : null,
            'modalEditGame' => $modalEditGame,
            'modalEditCms' => $modalEditCms,
            'modalEditSelectedGroups' => $modalEditSelectedGroups,
            'allGroups' => $allGroups,
            'allSubjects' => $allSubjects,
            'listSort' => $sort,
            'listDir' => $dir,
            'listStatusFilter' => $statusFilter,
            'gamesListTruncated' => $gamesListTruncated,
            'gamesListCap' => self::GAMES_LIST_CMS_MERGE_CAP,
            'settlementOpenGames' => $settlementForm['games'],
            'settlementOutcomesByGame' => $settlementForm['outcomesByGame'],
            'settlementGameSelectLabels' => $settlementForm['gameSelectLabels'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $request->validate($this->gameFormRules(forCreate: true));
        $groupIds = $this->normalizedGroupIds($v);
        $sideA = $this->normalizedOptionalSubjectId($v['side_a_subject_id'] ?? null);
        $sideB = $this->normalizedOptionalSubjectId($v['side_b_subject_id'] ?? null);
        $sideErrors = $this->sideSubjectValidationErrors($groupIds, $sideA, $sideB);
        if ($sideErrors !== []) {
            return redirect()->route('admin.games.index', ['mall_create' => 1])
                ->withInput()
                ->withErrors($sideErrors);
        }

        $cmsFields = $this->cmsWriteFieldsFromValidated($v);

        try {
            $created = $this->cmsGameClient->create($cmsFields);
        } catch (DownstreamServiceException $e) {
            return redirect()->route('admin.games.index', ['mall_create' => 1])
                ->withInput()
                ->withErrors(['cms' => $e->getMessage()]);
        } catch (Throwable $e) {
            return redirect()->route('admin.games.index', ['mall_create' => 1])
                ->withInput()
                ->withErrors(['cms' => $e->getMessage()]);
        }

        $rawId = $this->cmsResponseId($created);
        if ($rawId < 1) {
            return redirect()->route('admin.games.index', ['mall_create' => 1])
                ->withInput()
                ->withErrors(['cms' => 'CMS did not return a game id.']);
        }

        $game = new Game([
            'raw_id' => $rawId,
            'side_a_subject_id' => $sideA,
            'side_b_subject_id' => $sideB,
            'status' => (int) $v['status'],
        ]);
        $game->save();
        $game->groups()->sync($groupIds);

        return redirect()->route('admin.games.index')->with('status', 'Game created.');
    }

    public function show(Game $game): View
    {
        $game->load([
            'groups',
            'sideASubject',
            'sideBSubject',
            'markets' => static fn ($q) => $q->orderByDesc('id'),
        ]);

        $cmsGame = $this->fetchCmsGameOrNull((int) $game->raw_id);

        $gid = (int) $game->id;

        return view('admin.games.show', [
            'game' => $game,
            'cms_game' => $cmsGame,
            'settlementOrderCounts' => $this->settlementOverview->distinctOrderCountsByStatusForGame($gid),
            'settlementLineCounts' => $this->settlementOverview->lineResultCountsForGame($gid),
            'settlementJobs' => $this->settlementOverview->recentJobsForGame($gid),
        ]);
    }

    public function update(Request $request, Game $game): RedirectResponse
    {
        $v = $request->validate($this->gameFormRules(forCreate: false));
        $groupIds = $this->normalizedGroupIds($v);
        $sideA = $this->normalizedOptionalSubjectId($v['side_a_subject_id'] ?? null);
        $sideB = $this->normalizedOptionalSubjectId($v['side_b_subject_id'] ?? null);
        $sideErrors = $this->sideSubjectValidationErrors($groupIds, $sideA, $sideB);
        if ($sideErrors !== []) {
            return redirect()->route('admin.games.index', ['mall_edit' => $game->id])
                ->withInput()
                ->withErrors($sideErrors);
        }

        $cmsGame = $this->fetchCmsGameOrNull((int) $game->raw_id);
        if (is_array($cmsGame)) {
            if (trim((string) ($v['name'] ?? '')) === '') {
                return redirect()->route('admin.games.index', ['mall_edit' => $game->id])
                    ->withInput()
                    ->withErrors(['name' => 'Title is required when the CMS record can be loaded.']);
            }
            $cmsFields = $this->cmsWriteFieldsFromValidated($v);
            try {
                $this->cmsGameClient->update((int) $game->raw_id, $cmsFields);
            } catch (DownstreamServiceException $e) {
                return redirect()->route('admin.games.index', ['mall_edit' => $game->id])
                    ->withInput()
                    ->withErrors(['cms' => $e->getMessage()]);
            } catch (Throwable $e) {
                return redirect()->route('admin.games.index', ['mall_edit' => $game->id])
                    ->withInput()
                    ->withErrors(['cms' => $e->getMessage()]);
            }
        }

        $game->side_a_subject_id = $sideA;
        $game->side_b_subject_id = $sideB;
        $game->status = (int) $v['status'];
        $game->save();
        $game->groups()->sync($groupIds);

        return redirect()->route('admin.games.index')->with('status', 'Game updated.');
    }

    public function destroy(Game $game): RedirectResponse
    {
        if ($game->markets()->exists()) {
            return redirect()
                ->route('admin.games.show', $game)
                ->withErrors(['delete' => 'There are markets for this game; remove them first.']);
        }

        try {
            $this->cmsGameClient->delete((int) $game->raw_id);
        } catch (Throwable $e) {
            return redirect()
                ->route('admin.games.show', $game)
                ->withErrors(['delete' => 'CMS delete failed: '.$e->getMessage()]);
        }

        $game->groups()->detach();
        $game->delete();

        return redirect()->route('admin.games.index')->with('status', 'Game deleted.');
    }

    /**
     * Paginate games ordered by CMS {@code starts_at} (merge cap {@see GAMES_LIST_CMS_MERGE_CAP}).
     *
     * @param  Builder<Game>  $baseQuery
     * @return array{0: LengthAwarePaginator<Game>, 1: bool}
     */
    private function paginateGamesWithCmsStartsAt(
        Request $request,
        Builder $baseQuery,
        string $sort,
        string $dir,
        int $perPage,
    ): array {
        $totalMatching = (clone $baseQuery)->count();
        $truncated = $totalMatching > self::GAMES_LIST_CMS_MERGE_CAP;

        $rows = (clone $baseQuery)
            ->orderByDesc('id')
            ->limit(self::GAMES_LIST_CMS_MERGE_CAP)
            ->get();

        $rawIds = $rows
            ->map(static fn (Game $g): int => (int) $g->raw_id)
            ->unique()
            ->filter(static fn (int $r): bool => $r >= 1)
            ->values()
            ->all();

        $cmsByRawId = [];
        try {
            if ($rawIds !== []) {
                $cmsByRawId = $this->cmsGameClient->findManyById($rawIds);
            }
        } catch (Throwable) {
        }

        $withMeta = $rows->map(function (Game $g) use ($cmsByRawId): array {
            $ms = (int) (($cmsByRawId[(int) $g->raw_id] ?? [])['starts_at'] ?? 0);

            return ['game' => $g, 'starts_ms' => $ms];
        });

        $sorted = $withMeta
            ->sort(fn (array $a, array $b): int => $this->compareGameListRows($a, $b, $sort, $dir))
            ->values();

        /** @var Collection<int, Game> $gameModels */
        $gameModels = $sorted->map(static fn (array $row): Game => $row['game']);
        $total = $gameModels->count();
        $currentPage = max(1, (int) $request->query('page', 1));
        $slice = $gameModels->forPage($currentPage, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'pageName' => 'page'],
        );
        $paginator->withQueryString();

        return [$paginator, $truncated];
    }

    /**
     * @param  array{game: Game, starts_ms: int}  $a
     * @param  array{game: Game, starts_ms: int}  $b
     */
    private function compareGameListRows(array $a, array $b, string $sort, string $dir): int
    {
        $ga = $a['game'];
        $gb = $b['game'];
        $ma = $a['starts_ms'];
        $mb = $b['starts_ms'];
        $asc = $dir !== 'desc';

        if ($sort === 'starts_at') {
            $aValid = $ma > 0;
            $bValid = $mb > 0;
            if ($aValid !== $bValid) {
                if ($aValid) {
                    return -1;
                }
                if ($bValid) {
                    return 1;
                }

                return $gb->id <=> $ga->id;
            }
            if (! $aValid) {
                return $gb->id <=> $ga->id;
            }
            $cmp = $ma <=> $mb;
            if ($cmp === 0) {
                return $gb->id <=> $ga->id;
            }

            return $asc ? $cmp : -$cmp;
        }

        $cmp = $ga->id <=> $gb->id;

        return $asc ? $cmp : -$cmp;
    }

    private function parseOptionalStatusFilter(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (! is_numeric($raw)) {
            return null;
        }
        $n = (int) $raw;
        if (! in_array($n, [
            Game::STATUS_OPEN,
            Game::STATUS_CLOSED,
            Game::STATUS_SETTLED,
            Game::STATUS_PENDING_SETTLEMENT,
        ], true)) {
            return null;
        }

        return $n;
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
            'side_a_subject_id' => ['nullable', 'integer', Rule::exists('biz_game_subject', 'id')],
            'side_b_subject_id' => ['nullable', 'integer', Rule::exists('biz_game_subject', 'id')],
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

    /**
     * @param  array<string, mixed>  $v
     * @return list<int>
     */
    private function normalizedGroupIds(array $v): array
    {
        /** @var list<int|string> $raw */
        $raw = $v['group_ids'] ?? [];

        return array_values(array_unique(array_map(static fn ($x): int => (int) $x, $raw)));
    }

    private function normalizedOptionalSubjectId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $n = (int) $value;

        return $n >= 1 ? $n : null;
    }

    /**
     * @param  list<int>  $groupIds
     * @return array<string, list<string>>
     */
    private function sideSubjectValidationErrors(array $groupIds, ?int $sideA, ?int $sideB): array
    {
        $errors = [];
        foreach (['A' => $sideA, 'B' => $sideB] as $label => $sid) {
            if ($sid === null) {
                continue;
            }
            $ok = GameSubject::query()
                ->whereKey($sid)
                ->whereHas('groups', static fn ($q) => $q->whereIn('biz_game_group.id', $groupIds))
                ->exists();
            if (! $ok) {
                $errors['side_'.strtolower($label).'_subject_id'] = [
                    'The selected side '.$label.' subject is not in any of the chosen groups.',
                ];
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $v  Validated request subset
     * @return array<string, mixed>
     */
    private function cmsWriteFieldsFromValidated(array $v): array
    {
        $title = trim((string) ($v['name'] ?? ''));
        $fields = [
            'title' => $title,
        ];
        if (array_key_exists('starts_at', $v) && $v['starts_at'] !== null) {
            $fields['starts_at'] = (int) $v['starts_at'];
        }
        $banner = trim((string) ($v['banner_path'] ?? ''));
        $main = trim((string) ($v['main_image_path'] ?? ''));
        if ($banner !== '') {
            $fields['banner'] = $banner;
        }
        if ($main !== '') {
            $fields['main_media'] = $main;
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $cmsWriteResponse
     */
    private function cmsResponseId(array $cmsWriteResponse): int
    {
        if (isset($cmsWriteResponse['id']) && is_numeric($cmsWriteResponse['id'])) {
            return (int) $cmsWriteResponse['id'];
        }

        return 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchCmsGameOrNull(int $rawId): ?array
    {
        if ($rawId < 1) {
            return null;
        }
        try {
            $row = $this->cmsGameClient->find($rawId);
        } catch (Throwable) {
            return null;
        }

        return $row;
    }
}
