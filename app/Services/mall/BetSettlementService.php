<?php

declare(strict_types=1);

namespace App\Services\mall;

use App\Enums\BetLineResult;
use App\Enums\BetOrderStatus;
use App\Enums\PointsHoldState;
use App\Models\BetOrder;
use App\Models\BetOrderLine;
use App\Models\SportGame;
use App\Models\SportMarket;
use App\Models\SportSelection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Applies internally-entered results and settles accepted bets (points payouts/refunds).
 */
final readonly class BetSettlementService
{
    public function __construct(
        private MallPointsAdminService $pointsAdmin,
    ) {}

    /**
     * @param  list<int>  $winningSelectionIds
     */
    public function applyGameResult(int $gameId, array $winningSelectionIds): SportGame
    {
        if ($gameId < 1) {
            throw new RuntimeException('Invalid game_id.');
        }
        $winners = array_values(array_unique(array_filter(
            array_map(static fn (mixed $v): int => (int) $v, $winningSelectionIds),
            static fn (int $id) => $id > 0
        )));

        return DB::transaction(function () use ($gameId, $winners): SportGame {
            $game = SportGame::query()->whereKey($gameId)->lockForUpdate()->first();
            if ($game === null) {
                throw new RuntimeException('Game not found.');
            }
            if ($game->status === SportGame::STATUS_SETTLED) {
                throw new RuntimeException('Game already settled.');
            }

            $now = SportGame::nowMillis();
            SportMarket::query()
                ->where('game_id', $gameId)
                ->update(['status' => SportMarket::STATUS_SETTLED, 'ut' => $now]);

            SportSelection::query()
                ->whereIn('market_id', SportMarket::query()->where('game_id', $gameId)->select('id'))
                ->update(['status' => SportSelection::STATUS_SETTLED, 'ut' => $now]);

            $game->status = SportGame::STATUS_SETTLED;
            $game->winning_selection_ids = $winners;
            $game->save();

            $selectionIdsForGame = SportSelection::query()
                ->whereIn('market_id', SportMarket::query()->where('game_id', $gameId)->select('id'))
                ->pluck('id')
                ->all();

            $winnerSet = array_fill_keys($winners, true);

            $orders = BetOrder::query()
                ->where('status', BetOrderStatus::Accepted)
                ->whereHas('lines', static function ($q) use ($selectionIdsForGame): void {
                    $q->whereIn('kid', $selectionIdsForGame);
                })
                ->with('lines')
                ->lockForUpdate()
                ->get();

            foreach ($orders as $order) {
                $this->settleOrderAgainstResult($order, $winnerSet);
            }

            return $game->fresh() ?? $game;
        });
    }

    /**
     * @param  array<int, true>  $winnerSet
     */
    private function settleOrderAgainstResult(BetOrder $order, array $winnerSet): void
    {
        $order->load('lines');
        if ($order->lines->count() !== 1) {
            throw new RuntimeException('Settlement currently supports single-line bets only (order '.$order->id.').');
        }

        /** @var BetOrderLine $line */
        $line = $order->lines->first();
        $kid = (int) $line->kid;

        if (! isset($winnerSet[$kid])) {
            $line->result = BetLineResult::Lose;
            $line->save();
            $order->status = BetOrderStatus::Lost;
            $order->save();

            return;
        }

        $line->result = BetLineResult::Win;
        $line->save();

        $payout = (int) $line->potential_return_points;
        $this->pointsAdmin->appendImmutableLedger(
            (int) $order->uid,
            $payout,
            (int) $order->id,
            PointsHoldState::SettlementPayout
        );

        $order->status = BetOrderStatus::Won;
        $order->save();
    }
}
