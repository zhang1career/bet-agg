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
    public function listOpenSelections(int $page, int $perPage): array
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
                'market_type' => $market->market_type,
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
}
