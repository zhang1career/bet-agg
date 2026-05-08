<?php

declare(strict_types=1);

namespace App\Repos\mall;

use App\Enums\BetOrderStatus;
use App\Models\BetOrder;

final class BetOrderRepo
{
    public function findWithLinesByUserIdem(int $uid, int $idemKey): ?BetOrder
    {
        return BetOrder::query()
            ->with('lines')
            ->where('uid', $uid)
            ->where('idem_key', $idemKey)
            ->first();
    }

    public function findLocked(int $orderId): ?BetOrder
    {
        return BetOrder::query()->whereKey($orderId)->lockForUpdate()->first();
    }

    /**
     * Accepted / settlement-failed orders that have at least one line on the given markets.
     *
     * @param  list<int>  $marketIds
     * @return list<int>
     */
    public function idsPendingSettlementTouchingMarkets(array $marketIds): array
    {
        return BetOrder::query()
            ->whereIn('status', [
                BetOrderStatus::Accepted->value,
                BetOrderStatus::SettlementFailed->value,
            ])
            ->whereHas('lines', static function ($q) use ($marketIds): void {
                $q->whereIn('market_id', $marketIds);
            })
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }
}
