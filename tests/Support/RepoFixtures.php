<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\BetLineResult;
use App\Enums\BetOrderStatus;
use App\Models\BetOrder;
use App\Models\OrderItem;
use App\Models\SettleJob;
use App\Repos\mall\BetOrderRepo;
use App\Repos\mall\OrderItemRepo;
use App\Support\SettlementBizKey;

/** Shared rows for {@see tests/Unit/Repos} integration-style repo tests. */
final class RepoFixtures
{
    /**
     * @return array{order: BetOrder, line: OrderItem}
     */
    public static function orderWithLine(
        int $marketId,
        int $uid = 42,
        BetOrderStatus $orderStatus = BetOrderStatus::Accepted,
        BetLineResult $lineResult = BetLineResult::Pending,
        int $idemKey = 9_001,
    ): array {
        $orders = app(BetOrderRepo::class);
        $items = app(OrderItemRepo::class);

        $order = $orders->createAccepted($uid, $idemKey);
        if ($orderStatus !== BetOrderStatus::Accepted) {
            $orders->saveStatus($order, $orderStatus);
            $order->refresh();
        }

        $line = $items->createForOrder(
            (int) $order->id,
            $marketId,
            ['code' => 'home_win'],
            'Home win',
            $lineResult,
        );

        return ['order' => $order, 'line' => $line];
    }

    public static function settleJob(int $gameId, int $suffixMillis = 1_700_000_000_000): SettleJob
    {
        $job = new SettleJob([
            'biz_key' => SettlementBizKey::prefixForGame($gameId).':'.$suffixMillis,
            'payload' => ['winners' => ['home_win']],
            'total' => 1,
            'status' => 0,
        ]);
        $job->save();

        return $job;
    }
}
