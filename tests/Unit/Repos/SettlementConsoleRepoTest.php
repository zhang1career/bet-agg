<?php

declare(strict_types=1);

namespace Tests\Unit\Repos;

use App\Enums\BetLineResult;
use App\Enums\BetOrderStatus;
use App\Repos\mall\SettlementConsoleRepo;
use Tests\Support\CatalogSeeder;
use Tests\Support\RepoFixtures;
use Tests\TestCase;

final class SettlementConsoleRepoTest extends TestCase
{
    private SettlementConsoleRepo $console;

    protected function setUp(): void
    {
        parent::setUp();
        $this->console = app(SettlementConsoleRepo::class);
    }

    public function test_recentJobsForGame_filters_by_biz_key_prefix_and_orders_desc(): void
    {
        $fixture = CatalogSeeder::oneXTwoSettlement();
        $gameId = $fixture['game_local_id'];

        RepoFixtures::settleJob($gameId, 1_700_000_000_001);
        RepoFixtures::settleJob($gameId, 1_700_000_000_002);
        RepoFixtures::settleJob($gameId + 99, 1_700_000_000_003);

        $jobs = $this->console->recentJobsForGame($gameId, 10);

        $this->assertCount(2, $jobs);
        $this->assertTrue($jobs->first()->id > $jobs->last()->id);
    }

    public function test_distinctOrderCountsByStatusForGame_and_market(): void
    {
        $fixture = CatalogSeeder::oneXTwoSettlement();
        $gameId = $fixture['game_local_id'];
        $marketId = $fixture['market_id'];

        RepoFixtures::orderWithLine($marketId, 42, BetOrderStatus::Accepted, BetLineResult::Pending, 9_001);
        RepoFixtures::orderWithLine($marketId, 43, BetOrderStatus::Won, BetLineResult::Win, 9_002);

        $byGame = $this->console->distinctOrderCountsByStatusForGame($gameId);
        $byMarket = $this->console->distinctOrderCountsByStatusForMarket($marketId);

        $this->assertSame(1, $byGame[BetOrderStatus::Accepted->value] ?? 0);
        $this->assertSame(1, $byGame[BetOrderStatus::Won->value] ?? 0);
        $this->assertSame($byGame, $byMarket);
    }

    public function test_lineResultCounts_for_game_and_market(): void
    {
        $fixture = CatalogSeeder::oneXTwoSettlement();
        $gameId = $fixture['game_local_id'];
        $marketId = $fixture['market_id'];

        RepoFixtures::orderWithLine($marketId, 42, BetOrderStatus::Accepted, BetLineResult::Pending, 9_003);
        RepoFixtures::orderWithLine($marketId, 43, BetOrderStatus::Won, BetLineResult::Win, 9_004);

        $byGame = $this->console->lineResultCountsForGame($gameId);
        $byMarket = $this->console->lineResultCountsForMarket($marketId);

        $this->assertSame(1, $byGame[BetLineResult::Pending->value] ?? 0);
        $this->assertSame(1, $byGame[BetLineResult::Win->value] ?? 0);
        $this->assertSame($byGame, $byMarket);
    }
}
