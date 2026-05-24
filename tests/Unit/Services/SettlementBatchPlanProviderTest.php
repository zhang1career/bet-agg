<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Game;
use App\Repos\mall\BetOrderRepo;
use App\Repos\mall\GameRepo;
use App\Repos\mall\MarketRepo;
use App\Services\mall\settlement\SettlementBatchPlanProvider;
use App\Support\SettleOutcomes;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SettlementBatchPlanProviderTest extends TestCase
{
    private const GAME_ID = 42;

    public function test_make_plan_throws_when_game_missing(): void
    {
        $games = $this->createMock(GameRepo::class);
        $games->method('lockForUpdate')->with(self::GAME_ID)->willReturn(null);

        $sut = new SettlementBatchPlanProvider(
            self::GAME_ID,
            $games,
            $this->createMock(MarketRepo::class),
            $this->createMock(BetOrderRepo::class),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Game not found.');

        $sut->makePlan();
    }

    public function test_make_plan_throws_when_pending_but_outcomes_empty(): void
    {
        $game = $this->makeGamePartialMock(['save']);
        $game->forceFill([
            'status' => Game::STATUS_PENDING_SETTLEMENT,
            'settle_outcomes' => SettleOutcomes::pack([], []),
        ]);

        $games = $this->createMock(GameRepo::class);
        $games->method('lockForUpdate')->willReturn($game);

        $sut = new SettlementBatchPlanProvider(
            self::GAME_ID,
            $games,
            $this->createMock(MarketRepo::class),
            $this->createMock(BetOrderRepo::class),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('settle_outcomes is empty for pending game '.self::GAME_ID.'.');

        $sut->makePlan();
    }

    public function test_make_plan_throws_when_winners_and_voids_overlap(): void
    {
        $game = $this->makeGamePartialMock(['save']);
        $game->forceFill([
            'status' => Game::STATUS_PENDING_SETTLEMENT,
            'settle_outcomes' => SettleOutcomes::pack(['x'], ['x']),
        ]);

        $games = $this->createMock(GameRepo::class);
        $games->method('lockForUpdate')->willReturn($game);

        $sut = new SettlementBatchPlanProvider(
            self::GAME_ID,
            $games,
            $this->createMock(MarketRepo::class),
            $this->createMock(BetOrderRepo::class),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Outcome codes cannot appear in both winners and voids: x');

        $sut->makePlan();
    }

    public function test_make_plan_throws_when_game_not_pending_or_settled(): void
    {
        $game = $this->makeGamePartialMock(['save']);
        $game->forceFill([
            'status' => Game::STATUS_OPEN,
            'settle_outcomes' => null,
        ]);

        $games = $this->createMock(GameRepo::class);
        $games->method('lockForUpdate')->willReturn($game);

        $sut = new SettlementBatchPlanProvider(
            self::GAME_ID,
            $games,
            $this->createMock(MarketRepo::class),
            $this->createMock(BetOrderRepo::class),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Game '.self::GAME_ID.' is not pending settlement.');

        $sut->makePlan();
    }

    public function test_make_plan_from_pending_marks_settled_and_emits_orders(): void
    {
        $game = $this->makeGamePartialMock([]);
        $game->forceFill([
            'status' => Game::STATUS_PENDING_SETTLEMENT,
            'settle_outcomes' => SettleOutcomes::pack(['home_win'], ['away_win']),
        ]);

        $games = $this->createMock(GameRepo::class);
        $games->method('lockForUpdate')->willReturn($game);
        $games->expects($this->once())
            ->method('markSettled')
            ->with($game, $this->greaterThan(0))
            ->willReturnCallback(static function (Game $lockedGame, int $nowMillis): void {
                $lockedGame->status = Game::STATUS_SETTLED;
                $lockedGame->ut = $nowMillis;
            });

        $markets = $this->createMock(MarketRepo::class);
        $markets->expects($this->once())
            ->method('markAllSettledForGame')
            ->with(self::GAME_ID, $this->greaterThan(0));
        $markets->method('idsForGame')->with(self::GAME_ID)->willReturn([101, 102]);

        $orders = $this->createMock(BetOrderRepo::class);
        $orders->method('idsPendingSettlementTouchingMarkets')->with([101, 102])->willReturn([9001, 9002]);

        $plan = (new SettlementBatchPlanProvider(self::GAME_ID, $games, $markets, $orders))->makePlan();

        $this->assertSame(Game::STATUS_SETTLED, $game->status);
        $this->assertSame(
            SettleOutcomes::pack(['home_win'], ['away_win']),
            $game->settle_outcomes,
        );
        $this->assertSame([
            'game_id' => self::GAME_ID,
            'winners' => ['home_win'],
            'voids' => ['away_win'],
        ], $plan->payload);
        $this->assertCount(2, $plan->items);
        $this->assertSame('9001', $plan->items[0]->ref);
        $this->assertSame('9002', $plan->items[1]->ref);
    }

    public function test_make_plan_when_already_settled_skips_save_and_markets_update(): void
    {
        $packed = SettleOutcomes::pack(['home_win'], []);
        $game = $this->makeGamePartialMock([]);
        $game->forceFill([
            'status' => Game::STATUS_SETTLED,
            'settle_outcomes' => $packed,
        ]);

        $games = $this->createMock(GameRepo::class);
        $games->method('lockForUpdate')->willReturn($game);
        $games->expects($this->never())->method('markSettled');

        $markets = $this->createMock(MarketRepo::class);
        $markets->expects($this->never())->method('markAllSettledForGame');
        $markets->method('idsForGame')->willReturn([10]);

        $orders = $this->createMock(BetOrderRepo::class);
        $orders->method('idsPendingSettlementTouchingMarkets')->willReturn([55]);

        $plan = (new SettlementBatchPlanProvider(self::GAME_ID, $games, $markets, $orders))->makePlan();

        $this->assertSame(['home_win'], $plan->payload['winners']);
        $this->assertSame([], $plan->payload['voids']);
        $this->assertSame('55', $plan->items[0]->ref);
    }

    /**
     * @param  list<string>  $methods
     */
    private function makeGamePartialMock(array $methods): Game
    {
        return $this->createPartialMock(Game::class, $methods);
    }
}
