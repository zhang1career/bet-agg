<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BetOrder;

final class BetOrderApiArray
{
    /**
     * @return array<string, mixed>
     */
    public static function detail(BetOrder $order): array
    {
        $order->loadMissing('lines');

        $lines = [];
        foreach ($order->lines as $item) {
            $lines[] = [
                'market_id' => $item->mid,
                'selection' => $item->selection ?? [],
                'pick_label' => $item->pick_label,
                'result' => $item->result->value,
            ];
        }

        return [
            'id' => $order->id,
            'uid' => $order->uid,
            'status' => $order->status->value,
            'ct' => $order->ct,
            'ut' => $order->ut,
            'lines' => $lines,
        ];
    }
}
