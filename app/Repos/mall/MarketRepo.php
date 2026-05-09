<?php

declare(strict_types=1);

namespace App\Repos\mall;

use App\Models\Market;

class MarketRepo
{
    /**
     * Lock row for bet placement: needs parent game and synthetic leg subjects.
     */
    public function lockWithGameAndSubjectsForBet(int $marketId): ?Market
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
            ->where('game_id', $gameId)
            ->update(['status' => Market::STATUS_SETTLED, 'ut' => $nowMillis]);
    }

    /**
     * @return list<int>
     */
    public function idsForGame(int $gameId): array
    {
        return Market::query()
            ->where('game_id', $gameId)
            ->pluck('id')
            ->all();
    }
}
