<?php

declare(strict_types=1);

namespace App\Services\mall\settlement;

use App\Enums\BetOrderStatus;
use App\Models\BetOrder;
use App\Models\Game;
use App\Models\Market;
use App\Models\Selection;
use Paganini\Batch\Contracts\BatchPlanProviderContract;
use Paganini\Batch\DTO\BatchItem;
use Paganini\Batch\DTO\BatchPlan;
use Paganini\Batch\Execution\BatchExecutor;
use RuntimeException;

/**
 * Outer-phase work: lock the game, mark game / markets / selections settled,
 * and emit the list of accepted-state {@see BetOrder} ids that need an
 * inner-phase payout/refund/lose pass.
 *
 * Runs inside the outer transaction owned by
 * {@see BatchExecutor}; raises on already-settled
 * games so the executor rolls everything back atomically.
 */
final readonly class SettlementBatchPlanProvider implements BatchPlanProviderContract
{
    /**
     * @param  list<int>  $winningSelectionIds
     * @param  list<int>  $voidedSelectionIds
     */
    public function __construct(
        private int $gameId,
        private array $winningSelectionIds,
        private array $voidedSelectionIds,
    ) {}

    public function makePlan(): BatchPlan
    {
        $game = Game::query()->whereKey($this->gameId)->lockForUpdate()->first();
        if ($game === null) {
            throw new RuntimeException('Game not found.');
        }

        // First-time settlement: mutate game / market / selection state. Subsequent calls (e.g.
        // retry after SettlementFailed) skip these mutations and only re-pick up the orders that
        // still need money movement, so applyGameResult is idempotent on top-level state.
        if ($game->status !== Game::STATUS_SETTLED) {
            $now = Game::nowMillis();
            Market::query()
                ->where('game_id', $this->gameId)
                ->update(['status' => Market::STATUS_SETTLED, 'ut' => $now]);

            Selection::query()
                ->whereIn('market_id', Market::query()->where('game_id', $this->gameId)->select('id'))
                ->update(['status' => Selection::STATUS_SETTLED, 'ut' => $now]);

            $game->status = Game::STATUS_SETTLED;
            $game->winning_selection_ids = $this->winningSelectionIds;
            $game->save();
        }

        $selectionIds = Selection::query()
            ->whereIn('market_id', Market::query()->where('game_id', $this->gameId)->select('id'))
            ->pluck('id')
            ->all();

        $orderIds = BetOrder::query()
            ->whereIn('status', [
                BetOrderStatus::Accepted->value,
                // Pull SettlementFailed orders along too so retrying applyGameResult
                // re-tries those (after the operator topped up bookmaker liquidity etc.).
                BetOrderStatus::SettlementFailed->value,
            ])
            ->whereHas('lines', static function ($q) use ($selectionIds): void {
                $q->whereIn('kid', $selectionIds);
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
                'winners' => $this->winningSelectionIds,
                'voids' => $this->voidedSelectionIds,
            ],
            items: $items,
        );
    }
}
