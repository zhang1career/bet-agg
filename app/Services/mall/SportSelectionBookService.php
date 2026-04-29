<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Models\SportEvent;
use App\Models\SportMarket;
use App\Models\SportSelection;
use RuntimeException;

/**
 * Validates sport selections are open for betting (used at draft create and checkout).
 */
final readonly class SportSelectionBookService
{
    /**
     * Lines reuse shape {@code product_id} = biz_selection.id (kid), {@code quantity} = stake_points.
     *
     * @param  list<array{product_id: int, quantity: int}>  $lines
     */
    public function assertSelectionsAcceptingBets(int $uid, array $lines): void
    {
        if ($uid < 1) {
            throw new RuntimeException('Invalid uid.');
        }
        if ($lines === []) {
            throw new RuntimeException('lines must not be empty.');
        }

        foreach ($lines as $line) {
            $selectionId = (int) ($line['product_id'] ?? 0);
            $stake = (int) ($line['quantity'] ?? 0);
            if ($selectionId < 1 || $stake < 1) {
                throw new RuntimeException('Invalid order line.');
            }

            $selection = SportSelection::query()
                ->with(['market.event'])
                ->whereKey($selectionId)
                ->first();
            if ($selection === null) {
                throw new RuntimeException('Selection not found: '.$selectionId);
            }

            $market = $selection->market;
            if ($market === null) {
                throw new RuntimeException('Market missing for selection '.$selectionId);
            }
            $event = $market->event;
            if ($event === null) {
                throw new RuntimeException('Event missing for selection '.$selectionId);
            }

            if ($event->status !== SportEvent::STATUS_OPEN) {
                throw new RuntimeException('Event is not open for betting.');
            }
            if ($market->status !== SportMarket::STATUS_OPEN) {
                throw new RuntimeException('Market is not open for betting.');
            }
            if ($selection->status !== SportSelection::STATUS_OPEN) {
                throw new RuntimeException('Selection is not open for betting.');
            }
            if ($selection->current_odds_millis < 1000) {
                throw new RuntimeException('Selection odds are invalid.');
            }
        }
    }

    /**
     * @param  list<int>  $selectionIds
     * @return array<int, int> selection_id => current_odds_millis
     */
    public function getOddsMillisBySelectionIds(array $selectionIds): array
    {
        if ($selectionIds === []) {
            return [];
        }
        $rows = SportSelection::query()
            ->whereIn('id', $selectionIds)
            ->get(['id', 'current_odds_millis']);

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->id] = (int) $row->current_odds_millis;
        }

        return $out;
    }

    /**
     * @param  list<int>  $selectionIds
     * @return array<int, bool> selection_id => accepting
     */
    public function getAcceptingBySelectionIds(array $selectionIds): array
    {
        if ($selectionIds === []) {
            return [];
        }
        $selections = SportSelection::query()
            ->with(['market.event'])
            ->whereIn('id', $selectionIds)
            ->get();

        $out = [];
        foreach ($selections as $sel) {
            $market = $sel->market;
            $event = $market?->event;
            $ok = $event !== null
                && $market !== null
                && $event->status === SportEvent::STATUS_OPEN
                && $market->status === SportMarket::STATUS_OPEN
                && $sel->status === SportSelection::STATUS_OPEN
                && $sel->current_odds_millis >= 1000;
            $out[(int) $sel->id] = $ok;
        }

        return $out;
    }
}
