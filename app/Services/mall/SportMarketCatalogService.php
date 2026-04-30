<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Models\SportEvent;
use App\Models\SportMarket;
use App\Models\SportSelection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Read-side catalog: sport events, markets, and selections (replaces mall CMS catalog).
 */
final readonly class SportMarketCatalogService
{
    /**
     * @return array{items: list<array<string, mixed>>, pagination: array<string, mixed>}
     */
    public function listOpenEvents(int $page, int $perPage): array
    {
        $query = SportEvent::query()
            ->where('status', SportEvent::STATUS_OPEN)
            ->orderBy('starts_at')
            ->orderBy('id');

        /** @var LengthAwarePaginator<int, SportEvent> $p */
        $p = $query->paginate($perPage, ['*'], 'page', $page);

        $items = [];
        foreach ($p->items() as $event) {
            $items[] = $this->serializeEvent($event);
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
    public function getEventDetail(int $id): array
    {
        $event = SportEvent::query()->whereKey($id)->first();
        if ($event === null) {
            throw new \RuntimeException('Event not found.');
        }

        return $this->serializeEvent($event);
    }

    /**
     * @return array{items: list<array<string, mixed>>, pagination: array<string, mixed>}
     */
    public function listOpenMarkets(int $page, int $perPage, ?int $eventId): array
    {
        $query = SportMarket::query()
            ->with(['event'])
            ->where('status', SportMarket::STATUS_OPEN)
            ->whereHas('event', static function ($q): void {
                $q->where('status', SportEvent::STATUS_OPEN);
            });
        if ($eventId !== null) {
            $query->where('event_id', $eventId);
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
            ->with(['event'])
            ->whereKey($id)
            ->first();
        if ($market === null) {
            throw new \RuntimeException('Market not found.');
        }

        return $this->serializeMarket($market);
    }

    /**
     * @return array{items: list<array<string, mixed>>, pagination: array<string, mixed>}
     */
    public function listOpenSelections(int $page, int $perPage, ?int $marketId): array
    {
        $query = SportSelection::query()
            ->with(['market.event'])
            ->where('status', SportSelection::STATUS_OPEN)
            ->whereHas('market', static function ($q): void {
                $q->where('status', SportMarket::STATUS_OPEN)
                    ->whereHas('event', static function ($q2): void {
                        $q2->where('status', SportEvent::STATUS_OPEN);
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
            ->with(['market.event'])
            ->whereKey($id)
            ->first();
        if ($sel === null) {
            throw new \RuntimeException('Selection not found.');
        }

        return $this->serializeSelection($sel);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSelection(SportSelection $sel): array
    {
        $market = $sel->market;
        $event = $market?->event;

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
            'event' => $event === null ? null : [
                'id' => (int) $event->id,
                'name' => $event->name,
                'starts_at' => (int) $event->starts_at,
                'status' => (int) $event->status,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeEvent(SportEvent $event): array
    {
        return [
            'id' => (int) $event->id,
            'name' => $event->name,
            'starts_at' => (int) $event->starts_at,
            'status' => (int) $event->status,
            'winning_selection_ids' => $event->winning_selection_ids ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMarket(SportMarket $market): array
    {
        $event = $market->event;

        return [
            'id' => (int) $market->id,
            'event_id' => (int) $market->event_id,
            'market_type' => (int) $market->market_type->value,
            'status' => (int) $market->status,
            'event' => $event === null ? null : [
                'id' => (int) $event->id,
                'name' => $event->name,
                'starts_at' => (int) $event->starts_at,
                'status' => (int) $event->status,
            ],
        ];
    }
}
