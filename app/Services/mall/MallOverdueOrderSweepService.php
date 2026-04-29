<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Contracts\InventoryOutboundContract;
use App\Enums\BetOrderStatus;
use App\Enums\CheckoutPhase;
use App\Enums\PointsHoldState;
use App\Models\BetOrder;
use App\Models\PointsFlow;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Closes pending bets that exceeded the payment window (XXL-Job / scheduled maintenance).
 */
final readonly class MallOverdueOrderSweepService
{
    public function __construct(
        private OrderCommandService $orders,
        private InventoryOutboundContract $inventory,
        private MallPointsTccService $pointsTcc,
    ) {}

    /**
     * @return array{closed: int, errors: int}
     */
    public function sweepExpired(): array
    {
        $timeoutMs = (int) config('bet_agg.orders.pending_payment_timeout_ms', 1_800_000);
        if ($timeoutMs < 1) {
            $timeoutMs = 1_800_000;
        }
        $now = BetOrder::nowMillis();

        $closed = 0;
        $errors = 0;

        $query = BetOrder::query()
            ->where('status', BetOrderStatus::Pending)
            ->where(function ($q) use ($now, $timeoutMs): void {
                $q->where(function ($q2) use ($now, $timeoutMs): void {
                    $q2->where('checkout_phase', CheckoutPhase::None->value)
                        ->whereRaw('ct + ? < ?', [$timeoutMs, $now]);
                })->orWhere(function ($q2) use ($now, $timeoutMs): void {
                    $q2->where('checkout_phase', CheckoutPhase::AwaitPayment->value)
                        ->whereRaw('ut + ? < ?', [$timeoutMs, $now]);
                });
            })
            ->orderBy('id');

        $query->chunkById(50, function ($orders) use (&$closed, &$errors): void {
            foreach ($orders as $order) {
                if (! $this->cancelStalePendingOrder($order)) {
                    $errors++;
                } else {
                    $closed++;
                }
            }
        });

        Log::info('[bet-sweep] overdue sweep done', ['closed' => $closed, 'errors' => $errors]);

        return ['closed' => $closed, 'errors' => $errors];
    }

    /**
     * @return bool true if this order was cancelled by this call or already non-pending
     */
    public function cancelStalePendingOrder(BetOrder $order): bool
    {
        $order->refresh();
        if ($order->status !== BetOrderStatus::Pending) {
            return true;
        }

        $extId = $order->ext_inventory ? trim($order->ext_id) : '';

        $holds = PointsFlow::query()
            ->where('oid', $order->id)
            ->where('state', PointsHoldState::TrySucceeded)
            ->whereNotNull('tcc_idem_key')
            ->get();

        foreach ($holds as $hold) {
            $k = (string) $hold->tcc_idem_key;
            if ($k === '') {
                continue;
            }
            try {
                $this->pointsTcc->cancel($k);
            } catch (Throwable $e) {
                Log::warning('[bet-sweep] points cancel failed', ['order_id' => $order->id, 'message' => $e->getMessage()]);
            }
        }

        try {
            $this->orders->transitionStatus($order, BetOrderStatus::Cancelled, false);
        } catch (Throwable $e) {
            Log::warning('[bet-sweep] transition failed', ['order_id' => $order->id, 'message' => $e->getMessage()]);

            return false;
        }

        if ($order->ext_inventory && $extId !== '') {
            try {
                $this->inventory->release($extId);
            } catch (Throwable $e) {
                Log::warning('[bet-sweep] inventory release failed', ['order_id' => $order->id, 'message' => $e->getMessage()]);
            }
        }

        return true;
    }
}
