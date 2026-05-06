<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Http\Controllers\api\BetGameController;
use App\Http\Controllers\api\BetMarketController;
use App\Models\Game;
use App\Models\Market;
use App\Models\Selection;
use App\Services\mall\serv_fd\CmsGameClient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Read-side catalog: {@code biz_game} betting state plus {@code biz_market} / {@code biz_selection}.
 * CMS fields merged via {@see CmsGameClient::findManyById} using {@code biz_game.raw_id}.
 */
final class CatalogService
{
    public function __construct(
        private readonly CmsGameClient $cmsGames,
    ) {}

    /**
     * @param  GameListFilter  $filter  Validated filter inputs from {@see BetGameController}.
     * @return array{items: list<array<string, mixed>>, pagination: array<string, mixed>}
     */
    public function listGames(GameListFilter $filter, int $page, int $perPage): array
    {
        $query = Game::query();
        $this->applyGameFilter($query, $filter);
        $this->applyGameSort($query, $filter);

        /** @var LengthAwarePaginator<int, Game> $p */
        $p = $query->paginate($perPage, ['*'], 'page', $page);

        /** @var list<Game> $games */
        $games = $p->items();
        $cmsByRawId = $this->cmsGamesByRawIds($this->uniqueRawIdsFromGames($games));

        $items = [];
        foreach ($games as $game) {
            $cmsRow = $cmsByRawId[(int) $game->raw_id] ?? null;
            $items[] = $this->serializeGameRow($game, $cmsRow, false);
        }

        return [
            'items' => $items,
            'pagination' => $this->paginationPayload($p),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getGameDetail(int $localId): array
    {
        $game = Game::query()->whereKey($localId)->with(['groups'])->first();
        if ($game === null) {
            throw new NotFoundHttpException('Game not found.');
        }

        $cmsByRawId = $this->cmsGamesByRawIds([(int) $game->raw_id]);

        /** @var list<array{id: int, code: string}> $groupRows */
        $groupRows = [];
        foreach ($game->groups->sortBy('id')->values()->all() as $gr) {
            $groupRows[] = ['id' => (int) $gr->id, 'code' => (string) $gr->code];
        }

        return $this->serializeGameRow($game, $cmsByRawId[(int) $game->raw_id] ?? null, true, $groupRows);
    }

    /**
     * @param  MarketListFilter  $filter  Validated filter inputs from {@see BetMarketController}.
     * @return array{items: list<array<string, mixed>>, pagination: array<string, mixed>}
     */
    public function listMarkets(MarketListFilter $filter, int $page, int $perPage): array
    {
        $query = Market::query()->with(['game']);
        $this->applyMarketFilter($query, $filter);
        $query->orderByDesc('id');

        /** @var LengthAwarePaginator<int, Market> $p */
        $p = $query->paginate($perPage, ['*'], 'page', $page);

        $marketsOnPage = $p->items();
        $cmsByRawId = $this->cmsGamesByRawIds($this->uniqueRawIdsFromMarkets($marketsOnPage));
        $selectionsByMarket = $filter->includeSelections
            ? $this->selectionsForMarkets(array_map(static fn (Market $m): int => (int) $m->id, $marketsOnPage))
            : [];

        $items = [];
        foreach ($marketsOnPage as $market) {
            $items[] = $this->serializeMarketRow(
                $market,
                $filter->includeSelections ? ($selectionsByMarket[(int) $market->id] ?? []) : null,
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
     */
    public function getMarketDetail(int $id): array
    {
        $market = Market::query()
            ->with(['game'])
            ->whereKey($id)
            ->first();
        if ($market === null) {
            throw new NotFoundHttpException('Market not found.');
        }

        $selections = $this->selectionsForMarkets([(int) $market->id])[(int) $market->id] ?? [];

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

        /** @var Collection<int, Selection> $rows */
        $rows = Selection::query()
            ->whereIn('market_id', $marketIds)
            ->orderBy('id')
            ->get();

        $byMarket = [];
        foreach ($rows as $sel) {
            $byMarket[(int) $sel->market_id][] = $this->serializeSelectionRow($sel);
        }

        return $byMarket;
    }

    /**
     * @param  array<string, mixed>|null  $cmsRow  CMS batch/detail row keyed like API columns.
     * @param  list<array{id: int, code: string}>|null  $groups  Embedded on detail responses only; omit on list paths.
     * @return array<string, mixed>
     */
    private function serializeGameRow(Game $game, ?array $cmsRow, bool $detail, ?array $groups = null): array
    {
        $row = [
            'id' => (int) $game->id,
            'cms_id' => (int) $game->raw_id,
            'status' => (int) $game->status,
            'winning_selection_ids' => $game->winning_selection_ids ?? [],
            'ut' => (int) $game->ut,
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
            'id' => (int) $market->id,
            'game_id' => $game === null ? 0 : (int) $game->id,
            'name' => (string) $market->name,
            'status' => (int) $market->status,
            'ut' => (int) $market->ut,
            'game' => $game === null ? null : $this->serializeNestedGame($game, $nestedCms),
        ];

        if ($selections !== null) {
            $row['selections'] = $selections;
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSelectionRow(Selection $sel): array
    {
        return [
            'id' => (int) $sel->id,
            'market_id' => (int) $sel->market_id,
            'label' => (string) $sel->label,
            'current_odds_millis' => (int) $sel->current_odds_millis,
            'status' => (int) $sel->status,
            'ut' => (int) $sel->ut,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $cmsRow
     * @return array<string, mixed>
     */
    private function serializeNestedGame(Game $game, ?array $cmsRow): array
    {
        $merged = $this->serializeGameRow($game, $cmsRow, false);
        unset($merged['winning_selection_ids']);

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
            $rid = (int) $game->raw_id;
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
     * @param  list<int>  $rawIds
     * @return array<int, array<string, mixed>>
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
        if ($value === null || ! is_string($value)) {
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
