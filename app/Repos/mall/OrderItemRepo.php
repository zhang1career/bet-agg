<?php

declare(strict_types=1);

namespace App\Repos\mall;

use App\Enums\BetLineResult;
use App\Models\OrderItem;

class OrderItemRepo
{
    /**
     * @param  array<string, mixed>  $selection
     */
    public function createForOrder(
        int $orderId,
        int $marketId,
        array $selection,
        string $pickLabel,
        BetLineResult $result,
    ): OrderItem {
        $line = new OrderItem([
            'oid' => $orderId,
            'mid' => $marketId,
            'selection' => $selection,
            'pick_label' => $pickLabel,
            'result' => $result,
        ]);
        $line->save();

        return $line;
    }
}
