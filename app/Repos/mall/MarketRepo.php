<?php

declare(strict_types=1);

namespace App\Repos\mall;

use App\Models\Market;

class MarketRepo
{
    /**
     * Lock row for prediction submission: needs parent game and synthetic leg subjects.
     */
    public function lockWithGameAndSubjectsForPrediction(int $marketId): ?Market
    {
        return Market::query()
            ->with(['game.sideASubject', 'game.sideBSubject'])
            ->whereKey($marketId)
            ->lockForUpdate()
            ->first();
    }

    public function markAllSettledForGame(int $gameId, int $nowMillis): void
    {
        Market::query()
            ->where('gid', $gameId)
            ->update(['status' => Market::STATUS_SETTLED, 'ut' => $nowMillis]);
    }

    /**
     * @return list<int>
     */
    public function idsForGame(int $gameId): array
    {
        return Market::query()
            ->where('gid', $gameId)
            ->pluck('id')
            ->all();
    }
}
