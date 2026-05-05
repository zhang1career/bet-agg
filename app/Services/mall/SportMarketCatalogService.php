<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Http\Controllers\api\BetGameController;
use App\Http\Controllers\api\BetMarketController;
use App\Models\SportGame;
use App\Models\SportMarket;
use App\Models\SportSelection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Read-side catalog: local {@code biz_game} betting state plus
 * {@code biz_market} / {@code biz_selection}.
 *
 * Game display fields (title, media, kickoff) are not stored on {@code biz_game};
 * clients resolve them via CMS or a future dedicated path.
 *
 * Markets are returned with their selections inlined so agents can evaluate a
 * full market in one round-trip; the standalone {@code /bet/selections} endpoint
 * is gone.
 */
final class SportMarketCatalogService
{
    /**
     * @param  GameListFilter  $filter  Validated filter inputs from {@see BetGameController}.
     * @return array{items: list<array<string, mixed>>, pagination: array<string, mixed>}
     */
    public function listGames(GameListFilter $filter, int $page, int $perPage): array
    {
        $query = SportGame::query();
        $this->applyGameFilter($query, $filter);
        $this->applyGameSort($query, $filter);

        /** @var LengthAwarePaginator<int, SportGame> $p */
        $p = $query->paginate($perPage, ['*'], 'page', $page);

        $items = [];
        foreach ($p->items() as $game) {
            $items[] = $this->serializeGame($game);
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
        $game = SportGame::query()->whereKey($localId)->first();
        if ($game === null) {
            throw new NotFoundHttpException('Game not found.');
        }

        return $this->serializeGame($game);
    }

    /**
     * @param  MarketListFilter  $filter  Validated filter inputs from {@see BetMarketController}.
     * @return array{items: list<array<string, mixed>>, pagination: array<string, mixed>}
     */
    public function listMarkets(MarketListFilter $filter, int $page, int $perPage): array
    {
        $query = SportMarket::query()->with(['game']);
        $this->applyMarketFilter($query, $filter);
        $query->orderByDesc('id');

        /** @var LengthAwarePaginator<int, SportMarket> $p */
        $p = $query->paginate($perPage, ['*'], 'page', $page);

        $marketsOnPage = $p->items();
        $selectionsByMarket = $filter->includeSelections
            ? $this->selectionsForMarkets(array_map(static fn (SportMarket $m): int => (int) $m->id, $marketsOnPage))
            : [];

        $items = [];
        foreach ($marketsOnPage as $market) {
            $items[] = $this->serializeMarket(
                $market,
                $filter->includeSelections ? ($selectionsByMarket[(int) $market->id] ?? []) : null,
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
        $market = SportMarket::query()
            ->with(['game'])
            ->whereKey($id)
            ->first();
        if ($market === null) {
            throw new NotFoundHttpException('Market not found.');
        }

        $selections = $this->selectionsForMarkets([(int) $market->id])[(int) $market->id] ?? [];

        return $this->serializeMarket($market, $selections);
    }

    /**
     * @param  Builder<SportGame>  $query
     */
    private function applyGameFilter(Builder $query, GameListFilter $filter): void
    {
        if ($filter->statuses !== []) {
            $query->whereIn('status', $filter->statuses);
        }
        if ($filter->updatedAfterMillis !== null) {
            $query->where('ut', '>=', $filter->updatedAfterMillis);
        }
    }

    /**
     * @param  Builder<SportGame>  $query
     */
    private function applyGameSort(Builder $query, GameListFilter $filter): void
    {
        // Default: newest first.
        if ($filter->sort === null) {
            $query->orderByDesc('id');

            return;
        }
        [$column, $direction] = $filter->sort;
        $query->orderBy($column, $direction);
    }

    /**
     * @param  Builder<SportMarket>  $query
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
                $q->where('status', SportGame::STATUS_OPEN);
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

        /** @var Collection<int, SportSelection> $rows */
        $rows = SportSelection::query()
            ->whereIn('market_id', $marketIds)
            ->orderBy('id')
            ->get();

        $byMarket = [];
        foreach ($rows as $sel) {
            $byMarket[(int) $sel->market_id][] = $this->serializeSelection($sel);
        }

        return $byMarket;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeGame(SportGame $game): array
    {
        return [
            'id' => (int) $game->id,
            'cms_id' => (int) $game->raw_id,
            'status' => (int) $game->status,
            'winning_selection_ids' => $game->winning_selection_ids ?? [],
            'ut' => (int) $game->ut,
        ];
    }

    /**
     * @param  list<array<string, mixed>>|null  $selections  null = caller did not request inline; key omitted from output.
     * @return array<string, mixed>
     */
    private function serializeMarket(SportMarket $market, ?array $selections): array
    {
        $game = $market->game;

        $row = [
            'id' => (int) $market->id,
            'game_id' => $game === null ? 0 : (int) $game->id,
            'name' => (string) $market->name,
            'status' => (int) $market->status,
            'ut' => (int) $market->ut,
            'game' => $game === null ? null : $this->serializeGameForNested($game),
        ];

        if ($selections !== null) {
            $row['selections'] = $selections;
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSelection(SportSelection $sel): array
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
     * @return array<string, mixed>
     */
    private function serializeGameForNested(SportGame $game): array
    {
        $merged = $this->serializeGame($game);
        unset($merged['winning_selection_ids']);

        return $merged;
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
