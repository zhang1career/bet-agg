<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Models\Game;
use App\Repos\mall\GameGroupRepo;
use App\Repos\mall\GameRepo;
use App\Repos\mall\GameSubjectRepo;
use App\Repos\mall\SettlementConsoleRepo;
use App\Services\mall\serv_fd\CmsGameClient;
use App\Support\AdminGameSelectOptionLabels;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as PaginatorImpl;
use Illuminate\Support\Collection;
use Paganini\Aggregation\Exceptions\DownstreamServiceException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final readonly class GameAdminService
{
    public const LIST_CMS_MERGE_CAP = 2500;

    public function __construct(
        private GameRepo $games,
        private GameGroupRepo $groups,
        private GameSubjectRepo $subjects,
        private CmsGameClient $cmsGames,
        private SettlementConsoleRepo $settlementConsole,
        private AdminGameSelectOptionLabels $gameSelectLabels,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function indexViewData(Request $request): array
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
        $statusFilter = self::parseOptionalStatusFilter($request->query('status'));

        $list = $this->paginateIndexList(
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
            ? $this->formSelectOptions()
            : ['allGroups' => collect(), 'allSubjects' => collect()];

        $modal = $mallEditId >= 1
            ? $this->modalEditContext($mallEditId)
            : ['game' => null, 'cms' => null, 'selectedGroupIds' => []];

        $settlementForm = $this->settlementFormViewData();

        return [
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
            'gamesListCap' => self::LIST_CMS_MERGE_CAP,
            'settlementOpenGames' => $settlementForm['games'],
            'settlementOutcomesByGame' => $settlementForm['outcomesByGame'],
            'settlementGameSelectLabels' => $settlementForm['gameSelectLabels'],
        ];
    }

    /**
     * @return array{
     *     games: LengthAwarePaginator<int, Game>,
     *     cmsByRawId: array<int, array<string, mixed>>,
     *     gamesListTruncated: bool
     * }
     */
    public function paginateIndexList(
        ?int $statusFilter,
        string $sort,
        string $dir,
        int $perPage,
        string $paginatorPath,
        int $currentPage,
    ): array {
        if ($sort === 'starts_at') {
            [$games, $truncated] = $this->paginateByCmsStartsAt(
                $statusFilter,
                $dir,
                $perPage,
                $paginatorPath,
                $currentPage,
            );
        } else {
            $games = $this->games->paginateForAdmin($statusFilter, $dir, $perPage);
            $truncated = false;
        }

        return [
            'games' => $games,
            'cmsByRawId' => $this->cmsByRawIdsForGames($games->getCollection()),
            'gamesListTruncated' => $truncated,
        ];
    }

    /**
     * @return array{
     *     allGroups: Collection,
     *     allSubjects: Collection
     * }
     */
    public function formSelectOptions(): array
    {
        return [
            'allGroups' => $this->groups->listOrderedByCode(),
            'allSubjects' => $this->subjects->listWithGroupsOrderedByName(),
        ];
    }

    /**
     * @return array{
     *     game: Game|null,
     *     cms: array<string, mixed>|null,
     *     selectedGroupIds: list<int>
     * }
     */
    public function modalEditContext(int $gameId): array
    {
        $game = $this->games->findForAdmin($gameId);
        if ($game === null) {
            return ['game' => null, 'cms' => null, 'selectedGroupIds' => []];
        }

        return [
            'game' => $game,
            'cms' => $this->cmsGames->findOrNull($game->raw_id),
            'selectedGroupIds' => $game->groups->pluck('id')->map(static fn ($id): int => (int) $id)->all(),
        ];
    }

    /**
     * @return array{
     *     games: \Illuminate\Support\Collection<int, Game>,
     *     outcomesByGame: array<string, list<array{value: string, label: string}>>,
     *     gameSelectLabels: array<int, string>
     * }
     */
    public function settlementFormViewData(): array
    {
        $games = $this->games->listOpenWithBothSides();
        $outcomesByGame = [];
        foreach ($games as $game) {
            $outcomesByGame[(string) $game->id] = $this->outcomeOptionsForGame($game);
        }

        return [
            'games' => $games,
            'outcomesByGame' => $outcomesByGame,
            'gameSelectLabels' => $this->gameSelectLabels->mapByLocalId($games),
        ];
    }

    /**
     * @return array{
     *     game: Game,
     *     cms_game: array<string, mixed>|null,
     *     settlementOrderCounts: array<int, int>,
     *     settlementLineCounts: array<int, int>,
     *     settlementJobs: \Illuminate\Support\Collection<int, \App\Models\SettleJob>
     * }
     */
    public function showViewData(int $id): array
    {
        $game = $this->findForShow($id);
        $gid = $game->id;

        return [
            'game' => $game,
            'cms_game' => $this->cmsGames->findOrNull($game->raw_id),
            'settlementOrderCounts' => $this->settlementConsole->distinctOrderCountsByStatusForGame($gid),
            'settlementLineCounts' => $this->settlementConsole->lineResultCountsForGame($gid),
            'settlementJobs' => $this->settlementConsole->recentJobsForGame($gid),
        ];
    }

    public function findForShow(int $id): Game
    {
        $game = $this->games->findForAdminShow($id);
        if ($game === null) {
            throw new NotFoundHttpException();
        }

        return $game;
    }

    public function findForSettlement(int $id): Game
    {
        return $this->games->findOrFail($id);
    }

    /**
     * @param  list<int>  $groupIds
     * @return array<string, list<string>>
     */
    public function sideSubjectValidationErrors(array $groupIds, ?int $sideA, ?int $sideB): array
    {
        $errors = [];
        foreach (['A' => $sideA, 'B' => $sideB] as $label => $sid) {
            if ($sid === null) {
                continue;
            }
            if (! $this->subjects->existsInAnyOfGroups($sid, $groupIds)) {
                $errors['side_'.strtolower($label).'_subj_id'] = [
                    'The selected side '.$label.' subject is not in any of the chosen groups.',
                ];
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  list<int>  $groupIds
     * @return array<string, list<string>>|null
     */
    public function create(array $validated, array $groupIds, ?int $sideA, ?int $sideB): ?array
    {
        $cmsFields = $this->cmsWriteFieldsFromValidated($validated);

        try {
            $created = $this->cmsGames->create($cmsFields);
        } catch (DownstreamServiceException $e) {
            return ['cms' => [$e->getMessage()]];
        } catch (Throwable $e) {
            return ['cms' => [$e->getMessage()]];
        }

        $rawId = $this->cmsResponseId($created);
        if ($rawId < 1) {
            return ['cms' => ['CMS did not return a game id.']];
        }

        $game = $this->games->createForAdmin($rawId, $sideA, $sideB, (int) $validated['status']);
        $this->games->syncGroups($game, $groupIds);

        return null;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  list<int>  $groupIds
     * @return array<string, list<string>>|null
     */
    public function update(int $id, array $validated, array $groupIds, ?int $sideA, ?int $sideB): ?array
    {
        $game = $this->games->findForAdmin($id);
        if ($game === null) {
            throw new NotFoundHttpException();
        }

        $cmsGame = $this->cmsGames->findOrNull($game->raw_id);
        if (is_array($cmsGame)) {
            if (trim((string) ($validated['name'] ?? '')) === '') {
                return ['name' => ['Title is required when the CMS record can be loaded.']];
            }
            $cmsFields = $this->cmsWriteFieldsFromValidated($validated);
            try {
                $this->cmsGames->update($game->raw_id, $cmsFields);
            } catch (DownstreamServiceException $e) {
                return ['cms' => [$e->getMessage()]];
            } catch (Throwable $e) {
                return ['cms' => [$e->getMessage()]];
            }
        }

        $this->games->updateForAdmin($game, $sideA, $sideB, (int) $validated['status']);
        $this->games->syncGroups($game, $groupIds);

        return null;
    }

    /**
     * @return array<string, list<string>>|null
     */
    public function delete(int $id): ?array
    {
        $game = $this->games->findForAdmin($id);
        if ($game === null) {
            throw new NotFoundHttpException();
        }

        if ($this->games->hasMarkets($game)) {
            return ['delete' => ['There are markets for this game; remove them first.']];
        }

        try {
            $this->cmsGames->delete($game->raw_id);
        } catch (Throwable $e) {
            return ['delete' => ['CMS delete failed: '.$e->getMessage()]];
        }

        $this->games->detachGroups($game);
        $this->games->delete($game);

        return null;
    }

    public static function parseOptionalStatusFilter(mixed $raw): ?int
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

    public static function normalizedOptionalSubjectId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $n = (int) $value;

        return $n >= 1 ? $n : null;
    }

    /**
     * @param  Collection<int, Game>  $games
     * @return array<int, array<string, mixed>>
     */
    private function cmsByRawIdsForGames(Collection $games): array
    {
        return $this->cmsGames->findManyByIdOrEmpty($this->rawIdsFromGames($games));
    }

    /**
     * @param  Collection<int, Game>  $games
     * @return list<int>
     */
    private function rawIdsFromGames(Collection $games): array
    {
        return $games
            ->map(static fn (Game $g): int => $g->raw_id)
            ->unique()
            ->filter(static fn (int $r): bool => $r >= 1)
            ->values()
            ->all();
    }

    /**
     * @return array{0: LengthAwarePaginator<int, Game>, 1: bool}
     */
    private function paginateByCmsStartsAt(
        ?int $statusFilter,
        string $dir,
        int $perPage,
        string $paginatorPath,
        int $currentPage,
    ): array {
        $totalMatching = $this->games->countForAdmin($statusFilter);
        $truncated = $totalMatching > self::LIST_CMS_MERGE_CAP;

        $rows = $this->games->listForAdminStartsAtMerge($statusFilter, self::LIST_CMS_MERGE_CAP);

        $cmsByRawId = $this->cmsGames->findManyByIdOrEmpty($this->rawIdsFromGames($rows));

        $withMeta = $rows->map(function (Game $g) use ($cmsByRawId): array {
            $ms = (int) (($cmsByRawId[$g->raw_id] ?? [])['starts_at'] ?? 0);

            return ['game' => $g, 'starts_ms' => $ms];
        });

        $sorted = $withMeta
            ->sort(fn (array $a, array $b): int => $this->compareGameListRows($a, $b, 'starts_at', $dir))
            ->values();

        /** @var Collection<int, Game> $gameModels */
        $gameModels = $sorted->map(static fn (array $row): Game => $row['game']);
        $total = $gameModels->count();
        $slice = $gameModels->forPage($currentPage, $perPage)->values();

        $paginator = new PaginatorImpl(
            $slice,
            $total,
            $perPage,
            $currentPage,
            ['path' => $paginatorPath, 'pageName' => 'page'],
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

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function cmsWriteFieldsFromValidated(array $validated): array
    {
        $title = trim((string) ($validated['name'] ?? ''));
        $fields = [
            'title' => $title,
        ];
        if (array_key_exists('starts_at', $validated) && $validated['starts_at'] !== null) {
            $fields['starts_at'] = (int) $validated['starts_at'];
        }
        $banner = trim((string) ($validated['banner_path'] ?? ''));
        $main = trim((string) ($validated['main_image_path'] ?? ''));
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
     * @return list<array{value: string, label: string}>
     */
    private function outcomeOptionsForGame(Game $game): array
    {
        $aId = (int) $game->side_a_subj_id;
        $bId = (int) $game->side_b_subj_id;
        $aName = $game->sideASubject?->name ?? (string) __('console.settlement.side_a_placeholder');
        $bName = $game->sideBSubject?->name ?? (string) __('console.settlement.side_b_placeholder');

        return [
            [
                'value' => 'subject:'.$aId,
                'label' => (string) __('console.settlement.outcome_a_win', ['name' => $aName]),
            ],
            [
                'value' => 'draw',
                'label' => (string) __('console.settlement.outcome_draw'),
            ],
            [
                'value' => 'subject:'.$bId,
                'label' => (string) __('console.settlement.outcome_b_win', ['name' => $bName]),
            ],
            [
                'value' => 'void_all',
                'label' => (string) __('console.settlement.outcome_void_all'),
            ],
        ];
    }
}
