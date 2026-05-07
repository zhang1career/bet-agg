<?php

declare(strict_types=1);

namespace App\Services\mall\settlement;

use App\Enums\BetOrderStatus;
use App\Models\BetOrder;
use App\Models\Game;
use App\Models\Market;
use Paganini\Batch\Contracts\BatchPlanProviderContract;
use Paganini\Batch\DTO\BatchItem;
use Paganini\Batch\DTO\BatchPlan;
use RuntimeException;

/**
 * Outer-phase work: lock the game, mark game / markets settled,
 * persist {@code winning_outcomes}, emit accepted {@see BetOrder} ids.
 */
final readonly class SettlementBatchPlanProvider implements BatchPlanProviderContract
{
    /**
     * @param  list<string>  $winningOutcomeCodes
     * @param  list<string>  $voidOutcomeCodes
     */
    public function __construct(
        private int $gameId,
        private array $winningOutcomeCodes,
        private array $voidOutcomeCodes,
    ) {}

    public function makePlan(): BatchPlan
    {
        $game = Game::query()->whereKey($this->gameId)->lockForUpdate()->first();
        if ($game === null) {
            throw new RuntimeException('Game not found.');
        }

        if ($game->status !== Game::STATUS_SETTLED) {
            $now = Game::nowMillis();
            Market::query()
                ->where('game_id', $this->gameId)
                ->update(['status' => Market::STATUS_SETTLED, 'ut' => $now]);

            $game->status = Game::STATUS_SETTLED;
            $game->winning_outcomes = $this->winningOutcomeCodes;
            $game->save();
        }

        $marketIds = Market::query()
            ->where('game_id', $this->gameId)
            ->pluck('id')
            ->all();

        $orderIds = BetOrder::query()
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

        $items = [];
        foreach ($orderIds as $orderId) {
            $items[] = new BatchItem(
                ref: (string) (int) $orderId,
                payload: [],
            );
        }

        return new BatchPlan(
            payload: [
                'game_id' => $this->gameId,
                'winners' => $this->winningOutcomeCodes,
                'voids' => $this->voidOutcomeCodes,
            ],
            items: $items,
        );
    }
}
