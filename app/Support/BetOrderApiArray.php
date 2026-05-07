<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BetOrder;

final class BetOrderApiArray
{
    /**
     * Full order payload for bet API responses (list item detail / place result / order show).
     *
     * @return array<string, mixed>
     */
    public static function detail(BetOrder $order): array
    {
        $order->loadMissing('lines');

        $lines = [];
        foreach ($order->lines as $item) {
            $lines[] = [
                'market_id' => $item->market_id,
                'selection' => $item->selection ?? [],
                'stake_points' => $item->stake_points,
                'decimal_odds_millis' => $item->decimal_odds_millis,
                'potential_return_points' => $item->potential_return_points,
                'odds_snapshot' => $item->odds_snapshot,
                'result' => $item->result->value,
            ];
        }

        return [
            'id' => $order->id,
            'uid' => $order->uid,
            'status' => $order->status->value,
            'total_price' => $order->total_price,
            'points_held' => $order->points_held,
            'ct' => $order->ct,
            'ut' => $order->ut,
            'lines' => $lines,
        ];
    }
}
