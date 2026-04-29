<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Enums\BetLineResult;
use App\Enums\BetOrderStatus;
use App\Enums\PointsHoldState;
use App\Models\BetOrder;
use App\Models\BetOrderLine;
use App\Models\SportEvent;
use App\Models\SportEventResult;
use App\Models\SportMarket;
use App\Models\SportSelection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Applies internally-entered results and settles accepted bets (points payouts/refunds).
 */
final readonly class BetSettlementService
{
    public function __construct(
        private MallPointsAdminService $pointsAdmin,
    ) {}

    /**
     * @param  list<int>  $winningSelectionIds
     */
    public function applyEventResult(int $eventId, array $winningSelectionIds): SportEventResult
    {
        if ($eventId < 1) {
            throw new RuntimeException('Invalid event_id.');
        }
        $winners = array_values(array_unique(array_filter(
            array_map(static fn (mixed $v): int => (int) $v, $winningSelectionIds),
            static fn (int $id) => $id > 0
        )));

        return DB::transaction(function () use ($eventId, $winners): SportEventResult {
            $event = SportEvent::query()->whereKey($eventId)->lockForUpdate()->first();
            if ($event === null) {
                throw new RuntimeException('Event not found.');
            }
            if ($event->status === SportEvent::STATUS_SETTLED) {
                throw new RuntimeException('Event already settled.');
            }

            $existing = SportEventResult::query()->where('event_id', $eventId)->first();
            if ($existing !== null) {
                throw new RuntimeException('Event result already recorded.');
            }

            $result = new SportEventResult([
                'event_id' => $eventId,
                'winning_selection_ids' => $winners,
            ]);
            $result->save();

            $now = SportEvent::nowMillis();
            SportMarket::query()
                ->where('event_id', $eventId)
                ->update(['status' => SportMarket::STATUS_SETTLED, 'ut' => $now]);

            SportSelection::query()
                ->whereIn('market_id', SportMarket::query()->where('event_id', $eventId)->select('id'))
                ->update(['status' => SportSelection::STATUS_SETTLED, 'ut' => $now]);

            $event->status = SportEvent::STATUS_SETTLED;
            $event->save();

            $selectionIdsForEvent = SportSelection::query()
                ->whereIn('market_id', SportMarket::query()->where('event_id', $eventId)->select('id'))
                ->pluck('id')
                ->all();

            $winnerSet = array_fill_keys($winners, true);

            $orders = BetOrder::query()
                ->where('status', BetOrderStatus::Accepted)
                ->whereHas('lines', static function ($q) use ($selectionIdsForEvent): void {
                    $q->whereIn('selection_id', $selectionIdsForEvent);
                })
                ->with('lines')
                ->lockForUpdate()
                ->get();

            foreach ($orders as $order) {
                $this->settleOrderAgainstResult($order, $winnerSet);
            }

            return $result->fresh() ?? $result;
        });
    }

    /**
     * @param  array<int, true>  $winnerSet
     */
    private function settleOrderAgainstResult(BetOrder $order, array $winnerSet): void
    {
        $order->load('lines');
        if ($order->lines->count() !== 1) {
            throw new RuntimeException('Settlement currently supports single-line bets only (order '.$order->id.').');
        }

        /** @var BetOrderLine $line */
        $line = $order->lines->first();
        $sid = (int) $line->selection_id;

        if (! isset($winnerSet[$sid])) {
            $line->line_result = BetLineResult::Lose;
            $line->save();
            $order->status = BetOrderStatus::Lost;
            $order->save();

            return;
        }

        $line->line_result = BetLineResult::Win;
        $line->save();

        $payout = (int) $line->potential_return_points;
        $this->pointsAdmin->appendImmutableLedger(
            (int) $order->uid,
            $payout,
            (int) $order->id,
            PointsHoldState::SettlementPayout
        );

        $order->status = BetOrderStatus::Won;
        $order->save();
    }
}
