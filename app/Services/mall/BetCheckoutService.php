<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Contracts\PaymentOutboundContract;
use App\Enums\BetOrderStatus;
use App\Enums\CheckoutPhase;
use App\Models\BetOrder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Local checkout: one DB transaction — re-validate book, optional points hold, prepay or immediate acceptance.
 */
final readonly class BetCheckoutService
{
    public function __construct(
        private OrderCommandService $orders,
        private SportSelectionBookService $book,
        private MallPointsTccService $points,
        private PaymentOutboundContract $payment,
    ) {}

    public static function pointsHoldKey(int $orderId): string
    {
        return 'bet:order:'.$orderId.':hold';
    }

    /**
     * @return array{order: BetOrder, prepay: array<string, mixed>}
     */
    public function checkoutExistingOrder(int $uid, BetOrder $order, int $pointsMinor): array
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
        if ($pointsMinor < 0 || $pointsMinor > $order->total_price) {
            throw new RuntimeException('Invalid points_minor.');
        }

        return DB::transaction(function () use ($uid, $order, $pointsMinor): array {
            /** @var BetOrder $order */
            $order = BetOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->uid !== $uid || $order->status !== BetOrderStatus::Pending
                || $order->checkout_phase !== CheckoutPhase::None) {
                throw new RuntimeException('Order state changed; retry checkout.');
            }

            $lines = $this->orders->linesFromOrderItems($order);
            $this->book->assertSelectionsAcceptingBets($uid, $lines);

            $total = (int) $order->total_price;
            $cash = $total - $pointsMinor;
            $order->points_deduct_minor = $pointsMinor;
            $order->cash_payable_minor = $cash;

            $holdKey = self::pointsHoldKey((int) $order->id);
            if ($pointsMinor > 0) {
                $this->points->tryFreeze($uid, $pointsMinor, (int) $order->id, $holdKey);
            }

            if ($cash < 1) {
                if ($pointsMinor > 0) {
                    $this->points->confirm($holdKey);
                }
                $order = $this->orders->transitionStatus($order, BetOrderStatus::Accepted, false);
                $order->checkout_phase = CheckoutPhase::Completed;
                $order->save();

                return [
                    'order' => $order->fresh(['lines']) ?? $order,
                    'prepay' => [
                        'schema_version' => '1',
                        'pay_channel' => 'stub',
                        'order_id' => (int) $order->id,
                        'uid' => $uid,
                        'amount_minor' => 0,
                        'invoke_payment' => 'none',
                        'status' => 'points_only_accepted',
                    ],
                ];
            }

            $payIdem = 'bet:order:'.$order->id.':prepay';
            $partial = $this->payment->prepay($payIdem, (int) $order->id, $cash, $uid);
            $prepay = array_merge([
                'schema_version' => '1',
                'pay_channel' => 'stub',
                'order_id' => (int) $order->id,
                'uid' => $uid,
                'amount_minor' => $cash,
                'invoke_payment' => 'placeholder',
            ], $partial);

            $order->checkout_phase = CheckoutPhase::AwaitPayment;
            $order->save();

            return [
                'order' => $order->fresh(['lines']) ?? $order,
                'prepay' => $prepay,
            ];
        });
    }
}
