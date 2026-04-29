<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Enums\BetOrderStatus;
use App\Enums\CheckoutPhase;
use App\Models\BetOrder;
use RuntimeException;

/**
 * Payment gateway success path: confirm points hold (if any), mark bet accepted.
 */
final readonly class MallPaymentCallbackService
{
    public function __construct(
        private OrderCommandService $orders,
        private MallPointsTccService $pointsTcc,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handlePaidNotification(int $orderId, array $payload): BetOrder
    {
        if ($orderId < 1) {
            throw new RuntimeException('Invalid order_id.');
        }

        $order = $this->orders->findById($orderId);

        if ($order->status === BetOrderStatus::Accepted) {
            return $order;
        }

        if ($order->status !== BetOrderStatus::Pending) {
            throw new RuntimeException('Order is not pending; cannot apply payment.');
        }

        if ($order->checkout_phase !== CheckoutPhase::AwaitPayment) {
            throw new RuntimeException('Payment callback only applies to orders awaiting payment.');
        }

        $this->pointsTcc->confirm(BetCheckoutService::pointsHoldKey($orderId));

        $order = $this->orders->transitionStatus($order, BetOrderStatus::Accepted, false);
        $order->checkout_phase = CheckoutPhase::Completed;
        $order->save();

        return $order->fresh(['lines']) ?? $order;
    }
}
