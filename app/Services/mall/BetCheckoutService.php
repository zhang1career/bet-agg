<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Enums\BetOrderStatus;
use App\Enums\CheckoutPhase;
use App\Models\BetOrder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Local checkout: one DB transaction — re-validate book, freeze full stake from points balance, accept.
 */
final readonly class BetCheckoutService
{
    public function __construct(
        private OrderCommandService $orders,
        private SportSelectionBookService $book,
        private PointsTccService $points,
    ) {}

    /**
     * @return array{order: BetOrder}
     */
    public function checkoutExistingOrder(int $uid, BetOrder $order): array
    {
        if ($order->uid !== $uid) {
            throw new RuntimeException('Order does not belong to the current user.');
        }
        if ($order->status !== BetOrderStatus::Pending) {
            throw new RuntimeException('Order is not pending checkout.');
        }
        if ($order->checkout_phase !== CheckoutPhase::None) {
            throw new RuntimeException('Order is not a draft; checkout already started or completed.');
        }

        return DB::transaction(function () use ($uid, $order): array {
            /** @var BetOrder $order */
            $order = BetOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->uid !== $uid || $order->status !== BetOrderStatus::Pending
                || $order->checkout_phase !== CheckoutPhase::None) {
                throw new RuntimeException('Order state changed; retry checkout.');
            }

            $lines = $this->orders->linesFromOrderItems($order);
            $this->book->assertSelectionsAcceptingBets($uid, $lines);

            $total = (int) $order->total_price;
            if ($total < 1) {
                throw new RuntimeException('Order total is invalid.');
            }

            $order->points_held = $total;

            $this->points->tryFreeze($uid, $total, (int) $order->id);
            $this->points->confirmHoldForBetOrder((int) $order->id);

            $order = $this->orders->transitionStatus($order, BetOrderStatus::Accepted, false);
            $order->checkout_phase = CheckoutPhase::Completed;
            $order->save();

            return [
                'order' => $order->fresh(['lines']) ?? $order,
            ];
        });
    }
}
