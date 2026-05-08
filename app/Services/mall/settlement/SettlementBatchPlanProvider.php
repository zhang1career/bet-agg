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

/**
 * Outer-phase work: lock the game, mark game / markets settled,
 * persist {@see Game::$settle_outcomes}, emit accepted {@see BetOrder} ids.
 *
 * Winners/voids are read only from {@code biz_game.settle_outcomes} (pending or settled/retry).
 */
final readonly class SettlementBatchPlanProvider implements BatchPlanProviderContract
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

        [$winners, $voids] = $this->resolveWinnersAndVoids($game);
        $overlap = array_intersect($winners, $voids);
        if ($overlap !== []) {
            throw new RuntimeException(sprintf(
                'Outcome codes cannot appear in both winners and voids: %s',
                implode(',', $overlap),
            ));
        }

        if ($game->status !== Game::STATUS_SETTLED) {
            $now = Game::nowMillis();
            $this->markets->markAllSettledForGame($this->gameId, $now);

            $game->status = Game::STATUS_SETTLED;
            $game->settle_outcomes = SettleOutcomes::pack($winners, $voids);
            $game->save();
        }

        $marketIds = $this->markets->idsForGame($this->gameId);

        $orderIds = $this->orders->idsPendingSettlementTouchingMarkets($marketIds);

        $items = [];
        foreach ($orderIds as $orderId) {
            $items[] = new BatchItem(
                ref: (string)$orderId,
                payload: [],
            );
        }

        return new BatchPlan(
            payload: [
                'game_id' => $this->gameId,
                'winners' => $winners,
                'voids' => $voids,
            ],
            items: $items,
        );
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    private function resolveWinnersAndVoids(Game $game): array
    {
        if ($game->status === Game::STATUS_SETTLED) {
            return SettleOutcomes::unpack(
                is_array($game->settle_outcomes) ? $game->settle_outcomes : null,
            );
        }

        if ($game->status === Game::STATUS_PENDING_SETTLEMENT) {
            [$w, $v] = SettleOutcomes::unpack(
                is_array($game->settle_outcomes) ? $game->settle_outcomes : null,
            );
            if ($w === [] && $v === []) {
                throw new RuntimeException(
                    'settle_outcomes is empty for pending game '.$this->gameId.'. Record a result in admin first.',
                );
            }

            return [$w, $v];
        }

        throw new RuntimeException(
            'Game '.$this->gameId.' is not pending settlement. Record a result in admin first.',
        );
    }
}
