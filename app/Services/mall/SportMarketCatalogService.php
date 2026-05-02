<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Models\SportGame;
use App\Models\SportMarket;
use App\Models\SportSelection;
use App\Services\mall\serv_fd\CmsGameClient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Paganini\Aggregation\Exceptions\DownstreamServiceException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Catalog: CMS game metadata + local {@see SportGame} betting state; markets and selections local.
 */
final class SportMarketCatalogService
{
    public function __construct(
        private readonly CmsGameClient $cms,
    ) {}

    /**
     * @return array{items: list<array<string, mixed>>, pagination: array<string, mixed>}
     */
    public function listOpenGames(int $page, int $perPage): array
    {
        $query = SportGame::query()
            ->where('status', SportGame::STATUS_OPEN)
            ->orderBy('id');

        /** @var LengthAwarePaginator<int, SportGame> $p */
        $p = $query->paginate($perPage, ['*'], 'page', $page);

        $items = [];
        foreach ($p->items() as $game) {
            try {
                $cms = $this->cms->find((int) $game->raw_id);
            } catch (DownstreamServiceException) {
                continue;
            }
            $items[] = $this->serializeGame($cms, $game);
        }

        return [
            'items' => $items,
            'pagination' => [
                'total' => $p->total(),
                'per_page' => $p->perPage(),
                'current_page' => $p->currentPage(),
                'last_page' => $p->lastPage(),
            ],
        ];
    }

    /**
     * @param  int  $rawId  {@see SportGame::$raw_id} / CMS path segment
     * @return array<string, mixed>
     */
    public function getGameDetail(int $rawId): array
    {
        $cms = $this->cms->find($rawId);
        $local = SportGame::query()->where('raw_id', $rawId)->first();

        return $this->serializeGame($cms, $local);
    }

    /**
     * @param  int|null  $rawId  Filter by external/CMS game id (not local {@see SportGame::$id})
     * @return array{items: list<array<string, mixed>>, pagination: array<string, mixed>}
     */
    public function listOpenMarkets(int $page, int $perPage, ?int $rawId): array
    {
        $query = SportMarket::query()
            ->with(['game'])
            ->where('status', SportMarket::STATUS_OPEN)
            ->whereHas('game', static function ($q): void {
                $q->where('status', SportGame::STATUS_OPEN);
            });
        if ($rawId !== null) {
            $query->whereHas('game', static function ($q) use ($rawId): void {
                $q->where('raw_id', $rawId);
            });
        }
        $query->orderByDesc('id');

        /** @var LengthAwarePaginator<int, SportMarket> $p */
        $p = $query->paginate($perPage, ['*'], 'page', $page);

        $items = [];
        foreach ($p->items() as $market) {
            $items[] = $this->serializeMarket($market);
        }

        return [
            'items' => $items,
            'pagination' => [
                'total' => $p->total(),
                'per_page' => $p->perPage(),
                'current_page' => $p->currentPage(),
                'last_page' => $p->lastPage(),
            ],
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

        return $this->serializeMarket($market);
    }

    /**
     * @return array{items: list<array<string, mixed>>, pagination: array<string, mixed>}
     */
    public function listOpenSelections(int $page, int $perPage, ?int $marketId): array
    {
        $query = SportSelection::query()
            ->with(['market.game'])
            ->where('status', SportSelection::STATUS_OPEN)
            ->whereHas('market', static function ($q): void {
                $q->where('status', SportMarket::STATUS_OPEN)
                    ->whereHas('game', static function ($q2): void {
                        $q2->where('status', SportGame::STATUS_OPEN);
                    });
            })
            ->when($marketId !== null, static function ($q) use ($marketId): void {
                $q->where('market_id', $marketId);
            })
            ->orderByDesc('id');

        /** @var LengthAwarePaginator<int, SportSelection> $p */
        $p = $query->paginate($perPage, ['*'], 'page', $page);

        $items = [];
        foreach ($p->items() as $sel) {
            $items[] = $this->serializeSelection($sel);
        }

        return [
            'items' => $items,
            'pagination' => [
                'total' => $p->total(),
                'per_page' => $p->perPage(),
                'current_page' => $p->currentPage(),
                'last_page' => $p->lastPage(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getSelectionDetail(int $id): array
    {
        $sel = SportSelection::query()
            ->with(['market.game'])
            ->whereKey($id)
            ->first();
        if ($sel === null) {
            throw new NotFoundHttpException('Selection not found.');
        }

        return $this->serializeSelection($sel);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeGame(array $cms, ?SportGame $local): array
    {
        $id = (int) ($cms['id'] ?? 0);
        $status = $local !== null ? (int) $local->status : SportGame::STATUS_CLOSED;

        return [
            'id' => $id,
            'name' => (string) ($cms['title'] ?? ''),
            'image_path' => $this->cmsString($cms, 'main_media'),
            'banner_path' => $this->cmsString($cms, 'banner'),
            'starts_at' => (int) ($cms['starts_at'] ?? 0),
            'status' => $status,
            'winning_selection_ids' => $local !== null ? ($local->winning_selection_ids ?? []) : [],
        ];
    }

    private function cmsString(array $cms, string $key): ?string
    {
        if (! array_key_exists($key, $cms) || $cms[$key] === null) {
            return null;
        }

        return is_string($cms[$key]) ? $cms[$key] : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSelection(SportSelection $sel): array
    {
        $market = $sel->market;
        $game = $market?->game;

        return [
            'id' => (int) $sel->id,
            'label' => $sel->label,
            'current_odds_millis' => (int) $sel->current_odds_millis,
            'status' => (int) $sel->status,
            'market' => $market === null ? null : [
                'id' => (int) $market->id,
                'market_type' => (int) $market->market_type->value,
                'status' => (int) $market->status,
            ],
            'game' => $game === null ? null : $this->serializeGameForNested($game),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMarket(SportMarket $market): array
    {
        $game = $market->game;

        return [
            'id' => (int) $market->id,
            'game_id' => (int) ($game?->raw_id ?? 0),
            'market_type' => (int) $market->market_type->value,
            'status' => (int) $market->status,
            'game' => $game === null ? null : $this->serializeGameForNested($game),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeGameForNested(SportGame $local): array
    {
        $cmsKey = (int) $local->raw_id;
        try {
            $cms = $this->cms->find($cmsKey);
        } catch (DownstreamServiceException|NotFoundHttpException) {
            $cms = ['id' => $cmsKey];
        }

        $merged = $this->serializeGame($cms, $local);
        unset($merged['winning_selection_ids']);

        return $merged;
    }
}
