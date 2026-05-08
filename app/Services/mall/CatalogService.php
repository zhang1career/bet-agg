<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Http\Controllers\api\BetGameController;
use App\Http\Controllers\api\BetMarketController;
use App\Models\Game;
use App\Models\Market;
use App\Services\mall\serv_fd\CmsGameClient;
use App\Support\SettleOutcomes;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Read-side catalog: {@code biz_game} + {@code biz_market}; 胜平负选项由 {@see SyntheticMatchMarket} 合成。
 */
final readonly class CatalogService
{
    public function __construct(
        private CmsGameClient        $cmsGames,
        private SyntheticMatchMarket $synthetic,
    ) {}

    /**
     * @param GameListFilter $filter Validated filter inputs from {@see BetGameController}.
     * @return array{items: list<array<string, mixed>>, pagination: array<string, mixed>}
     * @throws ConnectionException
     */
    public function listGames(GameListFilter $filter, int $page, int $perPage): array
    {
        $query = Game::query()->with(['sideASubject', 'sideBSubject']);
        $this->applyGameFilter($query, $filter);
        $this->applyGameSort($query, $filter);

        /** @var LengthAwarePaginator<int, Game> $p */
        $p = $query->paginate($perPage, ['*'], 'page', $page);

        /** @var list<Game> $games */
        $games = $p->items();
        $cmsByRawId = $this->cmsGamesByRawIds($this->uniqueRawIdsFromGames($games));

        $items = [];
        foreach ($games as $game) {
            $cmsRow = $cmsByRawId[$game->raw_id] ?? null;
            $items[] = $this->serializeGameRow($game, $cmsRow, false);
        }

        return [
            'items' => $items,
            'pagination' => $this->paginationPayload($p),
        ];
    }

    /**
     * @return array<string, mixed>
     * @throws ConnectionException
     */
    public function getGameDetail(int $localId): array
    {
        $game = Game::query()
            ->whereKey($localId)
            ->with(['groups', 'sideASubject', 'sideBSubject'])
            ->first();
        if ($game === null) {
            throw new NotFoundHttpException('Game not found.');
        }

        $cmsByRawId = $this->cmsGamesByRawIds([$game->raw_id]);

        /** @var list<array{id: int, code: string}> $groupRows */
        $groupRows = [];
        foreach ($game->groups->sortBy('id')->values()->all() as $gr) {
            $groupRows[] = ['id' => (int) $gr->id, 'code' => (string) $gr->code];
        }

        return $this->serializeGameRow($game, $cmsByRawId[$game->raw_id] ?? null, true, $groupRows);
    }

    /**
     * @param MarketListFilter $filter Validated filter inputs from {@see BetMarketController}.
     * @return array{items: list<array<string, mixed>>, pagination: array<string, mixed>}
     * @throws ConnectionException
     */
    public function listMarkets(MarketListFilter $filter, int $page, int $perPage): array
    {
        $query = Market::query()->with([
            'game.sideASubject',
            'game.sideBSubject',
        ]);
        $this->applyMarketFilter($query, $filter);
        $query->orderByDesc('id');

        /** @var LengthAwarePaginator<int, Market> $p */
        $p = $query->paginate($perPage, ['*'], 'page', $page);

        $marketsOnPage = $p->items();
        $cmsByRawId = $this->cmsGamesByRawIds($this->uniqueRawIdsFromMarkets($marketsOnPage));
        $selectionsByMarket = $filter->includeSelections
            ? $this->selectionsForMarkets(array_map(static fn (Market $m): int => $m->id, $marketsOnPage))
            : [];

        $items = [];
        foreach ($marketsOnPage as $market) {
            $items[] = $this->serializeMarketRow(
                $market,
                $filter->includeSelections ? ($selectionsByMarket[$market->id] ?? []) : null,
                $cmsByRawId,
            );
        }

        return [
            'items' => $items,
            'pagination' => $this->paginationPayload($p),
        ];
    }

    /**
     * @return array<string, mixed>
     * @throws ConnectionException
     */
    public function getMarketDetail(int $id): array
    {
        $market = Market::query()
            ->with(['game.sideASubject', 'game.sideBSubject'])
            ->whereKey($id)
            ->first();
        if ($market === null) {
            throw new NotFoundHttpException('Market not found.');
        }

        $selections = $this->selectionsForMarkets([$market->id])[$market->id] ?? [];

        $cmsByRawId = $this->cmsGamesByRawIds(
            $market->game !== null ? [(int) $market->game->raw_id] : [],
        );

        return $this->serializeMarketRow($market, $selections, $cmsByRawId);
    }

    /**
     * @param  Builder<Game>  $query
     */
    private function applyGameFilter(Builder $query, GameListFilter $filter): void
    {
        if ($filter->statuses !== []) {
            $query->whereIn('status', $filter->statuses);
        }
        if ($filter->updatedAfterMillis !== null) {
            $query->where('ut', '>=', $filter->updatedAfterMillis);
        }
        if ($filter->groupCode !== null) {
            $query->whereHas('groups', static function (Builder $q) use ($filter): void {
                $q->where('biz_game_group.code', $filter->groupCode);
            });
        }
    }

    /**
     * @param  Builder<Game>  $query
     */
    private function applyGameSort(Builder $query, GameListFilter $filter): void
    {
        if ($filter->sort === null) {
            $query->orderByDesc('id');

            return;
        }
        [$column, $direction] = $filter->sort;
        $query->orderBy($column, $direction);
    }

    /**
     * @param  Builder<Market>  $query
     */
    private function applyMarketFilter(Builder $query, MarketListFilter $filter): void
    {
        if ($filter->statuses !== []) {
            $query->whereIn('status', $filter->statuses);
        }
        if ($filter->localGameId !== null) {
            $query->where('game_id', $filter->localGameId);
        }
        if ($filter->updatedAfterMillis !== null) {
            $query->where('ut', '>=', $filter->updatedAfterMillis);
        }
        if ($filter->onlyMarketsUnderOpenGame) {
            $query->whereHas('game', static function (Builder $q): void {
                $q->where('status', Game::STATUS_OPEN);
            });
        }
    }

    /**
     * @param  list<int>  $marketIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function selectionsForMarkets(array $marketIds): array
    {
        if ($marketIds === []) {
            return [];
        }

        $markets = Market::query()
            ->whereIn('id', $marketIds)
            ->with(['game.sideASubject', 'game.sideBSubject'])
            ->get()
            ->keyBy(static fn (Market $m): int => $m->id);

        $byMarket = [];
        foreach ($marketIds as $mid) {
            $m = $markets->get($mid);
            if ($m === null) {
                continue;
            }
            $byMarket[$mid] = $this->synthetic->legsForApi($m, $m->game);
        }

        return $byMarket;
    }

    /**
     * @param  array<string, mixed>|null  $cmsRow
     * @param  list<array{id: int, code: string}>|null  $groups
     * @return array<string, mixed>
     */
    private function serializeGameRow(Game $game, ?array $cmsRow, bool $detail, ?array $groups = null): array
    {
        $row = [
            'id' => $game->id,
            'cms_id' => $game->raw_id,
            'status' => $game->status,
            'side_a_subject_id' => $game->side_a_subject_id !== null ? (int) $game->side_a_subject_id : null,
            'side_b_subject_id' => $game->side_b_subject_id !== null ? (int) $game->side_b_subject_id : null,
            'side_a_name' => $game->sideASubject !== null ? (string) $game->sideASubject->name : null,
            'side_b_name' => $game->sideBSubject !== null ? (string) $game->sideBSubject->name : null,
            'settle_outcomes' => SettleOutcomes::forApi(
                is_array($game->settle_outcomes) ? $game->settle_outcomes : null,
            ),
            'ut' => $game->ut,
        ];

        $row['title'] = $cmsRow !== null ? $this->cmsStringOrNull($cmsRow['title'] ?? null) : null;
        $row['description'] = $cmsRow !== null ? $this->cmsStringOrNull($cmsRow['description'] ?? null) : null;
        $row['banner'] = $cmsRow !== null ? $this->cmsStringOrNull($cmsRow['banner'] ?? null) : null;

        if ($detail) {
            $row['main_media'] = $cmsRow !== null ? $this->cmsStringOrNull($cmsRow['main_media'] ?? null) : null;
            $row['start_at'] = $cmsRow !== null ? $this->cmsStartsAtMillisOrNull($cmsRow['starts_at'] ?? null) : null;
            $row['groups'] = $groups ?? [];
        }

        return $row;
    }

    /**
     * @param  list<array<string, mixed>>|null  $selections
     * @param  array<int, array<string, mixed>>  $cmsByRawId
     * @return array<string, mixed>
     */
    private function serializeMarketRow(Market $market, ?array $selections, array $cmsByRawId): array
    {
        $game = $market->game;

        $nestedCms = $game === null ? null : ($cmsByRawId[(int) $game->raw_id] ?? null);

        $row = [
            'id' => $market->id,
            'game_id' => (int)$game?->id,
            'type' => $market->type->value,
            'name' => $market->name,
            'status' => $market->status,
            'odds_millis' => $market->outcomeOddsMillisMap(),
            'ut' => $market->ut,
            'game' => $game === null ? null : $this->serializeNestedGame($game, $nestedCms),
        ];

        if ($selections !== null) {
            $row['selections'] = $selections;
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>|null  $cmsRow
     * @return array<string, mixed>
     */
    private function serializeNestedGame(Game $game, ?array $cmsRow): array
    {
        $merged = $this->serializeGameRow($game, $cmsRow, false);
        unset($merged['settle_outcomes']);

        return $merged;
    }

    /**
     * @param  list<Game>  $games
     * @return list<int>
     */
    private function uniqueRawIdsFromGames(array $games): array
    {
        $seen = [];
        foreach ($games as $game) {
            $rid = $game->raw_id;
            if ($rid >= 1) {
                $seen[$rid] = true;
            }
        }

        return array_keys($seen);
    }

    /**
     * @param  list<Market>  $markets
     * @return list<int>
     */
    private function uniqueRawIdsFromMarkets(array $markets): array
    {
        $seen = [];
        foreach ($markets as $market) {
            $game = $market->game;
            if ($game === null) {
                continue;
            }
            $rid = (int) $game->raw_id;
            if ($rid >= 1) {
                $seen[$rid] = true;
            }
        }

        return array_keys($seen);
    }

    /**
     * @param list<int> $rawIds
     * @return array<int, array<string, mixed>>
     * @throws ConnectionException
     */
    private function cmsGamesByRawIds(array $rawIds): array
    {
        if ($rawIds === []) {
            return [];
        }

        return $this->cmsGames->findManyById($rawIds);
    }

    private function cmsStringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        return $value;
    }

    private function cmsStartsAtMillisOrNull(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return null;
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $p
     * @return array<string, int>
     */
    private function paginationPayload(LengthAwarePaginator $p): array
    {
        return [
            'total' => $p->total(),
            'per_page' => $p->perPage(),
            'current_page' => $p->currentPage(),
            'last_page' => $p->lastPage(),
        ];
    }
}
