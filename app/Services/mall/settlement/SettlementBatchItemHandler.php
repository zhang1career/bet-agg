<?php

declare(strict_types=1);

namespace App\Services\mall\settlement;

use App\Enums\BetLineResult;
use App\Enums\BetOrderStatus;
use App\Models\BetOrder;
use App\Models\BetOrderLine;
use App\Services\mall\PointsAdminService;
use Paganini\Batch\Contracts\BatchItemHandlerContract;
use Paganini\Batch\DTO\BatchItem;
use RuntimeException;

/**
 * Per-order settlement: compare {@see BetOrderLine::selectionSettlementKey()} to payload winners/voids.
 */
final readonly class SettlementBatchItemHandler implements BatchItemHandlerContract
{
    public function __construct(
        private PointsAdminService $pointsAdmin,
    ) {}

    public function handle(BatchItem $item, array $jobPayload): void
    {
        $orderId = (int) $item->ref;
        if ($orderId < 1) {
            throw new RuntimeException('Invalid order ref: '.$item->ref);
        }

        /** @var list<string> $winners */
        $winners = self::stringList($jobPayload['winners'] ?? []);
        /** @var list<string> $voids */
        $voids = self::stringList($jobPayload['voids'] ?? []);
        $winnerSet = array_fill_keys($winners, true);
        $voidSet = array_fill_keys($voids, true);

        /** @var BetOrder|null $order */
        $order = BetOrder::query()->whereKey($orderId)->lockForUpdate()->first();
        if ($order === null) {
            throw new RuntimeException('Order disappeared mid-settlement: '.$orderId);
        }

        if (in_array($order->status, [BetOrderStatus::Won, BetOrderStatus::Lost, BetOrderStatus::Void], true)) {
            return;
        }

        if (! in_array($order->status, [BetOrderStatus::Accepted, BetOrderStatus::SettlementFailed], true)) {
            throw new RuntimeException(sprintf(
                'Order %d is not eligible for settlement (status=%s).',
                $orderId,
                $order->status->value,
            ));
        }

        $order->loadMissing('lines');
        if ($order->lines->count() !== 1) {
            throw new RuntimeException('Settlement currently supports single-line bets only (order '.$orderId.').');
        }

        /** @var BetOrderLine $line */
        $line = $order->lines->first();
        $outcome = $line->selectionSettlementKey();

        if (isset($voidSet[$outcome])) {
            $this->settleVoid($order, $line);

            return;
        }
        if (isset($winnerSet[$outcome])) {
            $this->settleWin($order, $line);

            return;
        }

        $this->settleLoss($order, $line);
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $v) {
            if (! is_string($v)) {
                continue;
            }
            $t = trim($v);
            if ($t === '') {
                continue;
            }
            $out[$t] = true;
        }

        return array_keys($out);
    }

    private function settleWin(BetOrder $order, BetOrderLine $line): void
    {
        $next = BetOrderStatus::Won;
        $this->assertCanTransition($order, $next);

        $line->result = BetLineResult::Win;
        $line->save();

        $payout = $line->potential_return_points;
        $bookmakerUid = (int) config('bet_agg.points.bookmaker_uid');
        if ($bookmakerUid < 1) {
            throw new RuntimeException('Bookmaker account is not configured (bet_agg.points.bookmaker_uid).');
        }
        $this->pointsAdmin->payoutBetWinFromBookmaker(
            $bookmakerUid,
            $order->uid,
            $payout,
            $order->id,
        );

        $order->status = $next;
        $order->save();
    }

    private function settleLoss(BetOrder $order, BetOrderLine $line): void
    {
        $next = BetOrderStatus::Lost;
        $this->assertCanTransition($order, $next);

        $line->result = BetLineResult::Lose;
        $line->save();

        $order->status = $next;
        $order->save();
    }

    private function settleVoid(BetOrder $order, BetOrderLine $line): void
    {
        $next = BetOrderStatus::Void;
        $this->assertCanTransition($order, $next);

        $line->result = BetLineResult::Void;
        $line->save();

        $stake = $order->total_price;
        $bookmakerUid = (int) config('bet_agg.points.bookmaker_uid');
        if ($bookmakerUid < 1) {
            throw new RuntimeException('Bookmaker account is not configured (bet_agg.points.bookmaker_uid).');
        }
        $this->pointsAdmin->refundBetStakeFromBookmaker(
            $bookmakerUid,
            $order->uid,
            $stake,
            $order->id,
        );

        $order->status = $next;
        $order->save();
    }

    private function assertCanTransition(BetOrder $order, BetOrderStatus $next): void
    {
        if (! $order->status->canTransitionTo($next)) {
            throw new RuntimeException(sprintf(
                'Cannot transition order %d from %s to %s.',
                $order->id,
                $order->status->value,
                $next->value,
            ));
        }
    }
}
