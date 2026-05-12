<?php

declare(strict_types=1);

namespace App\Services\mall\settlement;

use App\Models\Game;
use App\Repos\mall\BetOrderRepo;
use App\Repos\mall\GameRepo;
use App\Repos\mall\MarketRepo;
use App\Support\SettleOutcomes;
use Paganini\Batch\Contracts\BatchPlanProviderContract;
use Paganini\Batch\DTO\BatchItem;
use Paganini\Batch\DTO\BatchPlan;
use RuntimeException;

final class SettlementBatchPlanProvider implements BatchPlanProviderContract
{
    public function __construct(
        private int $gameId,
        private GameRepo $games,
        private MarketRepo $markets,
        private BetOrderRepo $orders,
    ) {}

    public function makePlan(): BatchPlan
    {
        $game = $this->games->lockForUpdate($this->gameId);
        if ($game === null) {
            throw new RuntimeException('Game not found.');
        }

        $status = (int) $game->status;
        if ($status !== Game::STATUS_PENDING_SETTLEMENT && $status !== Game::STATUS_SETTLED) {
            throw new RuntimeException('Game '.$this->gameId.' is not pending settlement.');
        }

        [$winners, $voids] = SettleOutcomes::unpack(
            is_array($game->settle_outcomes) ? $game->settle_outcomes : null,
        );

        $overlap = array_intersect($winners, $voids);
        if ($overlap !== []) {
            throw new RuntimeException(
                'Outcome codes cannot appear in both winners and voids: '.implode(',', $overlap),
            );
        }

        if ($status === Game::STATUS_PENDING_SETTLEMENT && $winners === [] && $voids === []) {
            throw new RuntimeException('settle_outcomes is empty for pending game '.$this->gameId.'.');
        }

        if ($status === Game::STATUS_PENDING_SETTLEMENT) {
            $now = Game::nowMillis();
            $game->status = Game::STATUS_SETTLED;
            $game->ut = $now;
            $game->save();
            $this->markets->markAllSettledForGame($this->gameId, $now);
        }

        $marketIds = $this->markets->idsForGame($this->gameId);
        $orderIds = $this->orders->idsPendingSettlementTouchingMarkets($marketIds);

        $items = [];
        foreach ($orderIds as $oid) {
            $items[] = new BatchItem((string) $oid, []);
        }

        return new BatchPlan([
            'game_id' => $this->gameId,
            'winners' => $winners,
            'voids' => $voids,
        ], $items);
    }
}
