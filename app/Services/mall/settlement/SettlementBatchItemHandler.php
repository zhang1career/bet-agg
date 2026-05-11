<?php

declare(strict_types=1);

namespace App\Services\mall\settlement;

use App\Enums\BetLineResult;
use App\Enums\BetOrderStatus;
use App\Models\BetOrder;
use App\Services\mall\ReputationLedgerService;
use Paganini\Batch\Contracts\BatchItemHandlerContract;
use Paganini\Batch\DTO\BatchItem;
use RuntimeException;

final readonly class SettlementBatchItemHandler implements BatchItemHandlerContract
{
    public function __construct(private ReputationLedgerService $reputation) {}

    public function handle(BatchItem $item, array $jobPayload): void
    {
        $orderId = (int) $item->ref;
        if ($orderId < 1) {
            throw new RuntimeException('Invalid settlement item ref.');
        }

        /** @var BetOrder|null $order */
        $order = BetOrder::query()->whereKey($orderId)->with('lines')->lockForUpdate()->first();
        if ($order === null) {
            throw new RuntimeException('Bet order not found: '.$orderId);
        }

        if (! in_array($order->status, [BetOrderStatus::Accepted, BetOrderStatus::SettlementFailed], true)) {
            return;
        }

        $line = $order->lines->first();
        if ($line === null) {
            throw new RuntimeException('Bet order has no lines: '.$orderId);
        }

        $pick = $line->selectionSettlementKey();
        if ($pick === '') {
            throw new RuntimeException('Order item has no outcome code: '.$orderId);
        }

        /** @var list<string> $winners */
        $winners = array_values(array_filter($jobPayload['winners'] ?? [], static fn ($v): bool => is_string($v) && $v !== ''));
        /** @var list<string> $voids */
        $voids = array_values(array_filter($jobPayload['voids'] ?? [], static fn ($v): bool => is_string($v) && $v !== ''));

        $nextLine = BetLineResult::Pending;
        $nextOrder = BetOrderStatus::Accepted;

        if (in_array($pick, $voids, true)) {
            $nextLine = BetLineResult::Void;
            $nextOrder = BetOrderStatus::Void;
        } elseif (in_array($pick, $winners, true)) {
            $nextLine = BetLineResult::Win;
            $nextOrder = BetOrderStatus::Won;
        } else {
            $nextLine = BetLineResult::Lose;
            $nextOrder = BetOrderStatus::Lost;
        }

        if (! $order->status->canTransitionTo($nextOrder)) {
            throw new RuntimeException('Invalid bet order status transition for order '.$orderId);
        }

        $line->result = $nextLine;
        $line->save();

        $order->status = $nextOrder;
        $order->save();

        if ($nextOrder === BetOrderStatus::Won) {
            $this->reputation->creditWin((int) $order->uid, $orderId);
        } elseif ($nextOrder === BetOrderStatus::Lost) {
            $this->reputation->debitLoss((int) $order->uid, $orderId);
        }
    }
}
