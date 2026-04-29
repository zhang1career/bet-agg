<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Enums\BetLineResult;
use App\Enums\BetOrderStatus;
use App\Enums\CheckoutPhase;
use App\Models\BetOrder;
use App\Models\BetOrderLine;
use App\Models\SportSelection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Bet orders: draft slip then local checkout (points hold + payment stub).
 */
final readonly class OrderCommandService
{
    public function __construct(
        private SportSelectionBookService $book,
    ) {}

    /**
     * Draft bet: single line only (selection + stake). Odds snapshot is persisted from current quote.
     *
     * @param  list<array{kid: int, stake_points: int}>  $lines
     */
    public function createDraftPendingOrder(int $userId, array $lines): BetOrder
    {
        if ($lines === []) {
            throw new RuntimeException('Bet must contain exactly one line.');
        }
        if (count($lines) !== 1) {
            throw new RuntimeException('Only single-selection bets are supported in this version.');
        }

        return DB::transaction(function () use ($userId, $lines): BetOrder {
            $line = $lines[0];
            $kid = (int) ($line['kid'] ?? 0);
            $stake = (int) ($line['stake_points'] ?? 0);
            if ($kid < 1 || $stake < 1) {
                throw new RuntimeException('Invalid bet line.');
            }

            $this->book->assertSelectionsAcceptingBets($userId, [
                ['product_id' => $kid, 'quantity' => $stake],
            ]);

            $selection = SportSelection::query()
                ->with(['market.event'])
                ->whereKey($kid)
                ->first();
            if ($selection === null) {
                throw new RuntimeException('Selection not found.');
            }
            $market = $selection->market;
            $event = $market?->event;

            $millis = (int) $selection->current_odds_millis;
            $potentialReturn = intdiv($stake * $millis, 1000);
            if ($potentialReturn < 1) {
                throw new RuntimeException('Potential return rounds down to zero; stake or odds too small.');
            }

            $snapshot = [
                'kid' => $kid,
                'market_id' => $market ? (int) $market->id : 0,
                'event_id' => $event ? (int) $event->id : 0,
                'event_name' => $event ? $event->name : '',
                'market_type' => $market ? $market->market_type->value : 0,
                'label' => $selection->label,
                'decimal_odds_millis' => $millis,
            ];

            $order = new BetOrder([
                'uid' => $userId,
                'status' => BetOrderStatus::Pending,
                'total_price' => $stake,
                'checkout_phase' => CheckoutPhase::None,
                'ext_inventory' => false,
                'ext_id' => '',
                'points_deduct_minor' => 0,
                'cash_payable_minor' => 0,
            ]);
            $order->save();

            $item = new BetOrderLine([
                'oid' => $order->id,
                'kid' => $kid,
                'stake_points' => $stake,
                'odds_snapshot' => $snapshot,
                'decimal_odds_millis' => $millis,
                'potential_return_points' => $potentialReturn,
                'result' => BetLineResult::Pending,
            ]);
            $item->save();

            return $order->load('lines');
        });
    }

    /**
     * @return list<array{product_id: int, quantity: int}>
     */
    public function linesFromOrderItems(BetOrder $order): array
    {
        $order->loadMissing('lines');
        $lines = [];
        foreach ($order->lines as $item) {
            $lines[] = [
                'product_id' => (int) $item->kid,
                'quantity' => (int) $item->stake_points,
            ];
        }

        if ($lines === []) {
            throw new RuntimeException('Order has no lines.');
        }

        return $lines;
    }

    public function findById(int $orderId): BetOrder
    {
        $order = BetOrder::query()->with('lines')->find($orderId);
        if ($order === null) {
            throw (new ModelNotFoundException)->setModel(BetOrder::class, [$orderId]);
        }

        return $order;
    }

    public function transitionStatus(
        BetOrder $order,
        BetOrderStatus $next,
        bool $restoreLocalInventoryOnCancel = false,
    ): BetOrder {
        $current = $order->status;
        if (! $current->canTransitionTo($next)) {
            throw new RuntimeException(
                sprintf('Cannot transition order %d from %s to %s.', $order->id, $current->value, $next->value)
            );
        }

        return DB::transaction(function () use ($order, $next, $current, $restoreLocalInventoryOnCancel): BetOrder {
            $order->load('lines');
            if ($current === BetOrderStatus::Pending && $next === BetOrderStatus::Cancelled) {
                if ($restoreLocalInventoryOnCancel) {
                    // Sportsbook has no local inventory to restore; reserve is logical only.
                }
            }

            $order->status = $next;
            $order->save();

            return $order;
        });
    }

    /**
     * @return LengthAwarePaginator<int, BetOrder>
     */
    public function paginateForUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return BetOrder::query()
            ->where('uid', $userId)
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findForUser(int $orderId, int $userId): BetOrder
    {
        $order = BetOrder::query()
            ->where('id', $orderId)
            ->where('uid', $userId)
            ->with('lines')
            ->first();

        if ($order === null) {
            throw (new ModelNotFoundException)->setModel(BetOrder::class, [$orderId]);
        }

        return $order;
    }
}
