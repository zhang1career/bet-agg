<?php

declare(strict_types=1);

namespace Tests\Unit\Repos;

use App\Enums\BetLineResult;
use App\Enums\BetOrderStatus;
use App\Repos\mall\BetOrderRepo;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\Support\CatalogSeeder;
use Tests\Support\RepoFixtures;
use Tests\TestCase;

final class BetOrderRepoTest extends TestCase
{
    private BetOrderRepo $orders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orders = app(BetOrderRepo::class);
    }

    public function test_createAccepted_and_findWithLinesByUserIdem(): void
    {
        $order = $this->orders->createAccepted(42, 8_001);

        $found = $this->orders->findWithLinesByUserIdem(42, 8_001);

        $this->assertNotNull($found);
        $this->assertSame((int) $order->id, (int) $found->id);
        $this->assertSame(BetOrderStatus::Accepted, $found->status);
    }

    public function test_idsPendingSettlementTouchingMarkets_includes_accepted_and_failed(): void
    {
        $fixture = CatalogSeeder::oneXTwoSettlement();
        $marketId = $fixture['market_id'];

        $accepted = RepoFixtures::orderWithLine($marketId, 42, BetOrderStatus::Accepted, BetLineResult::Pending, 8_002);
        $failed = RepoFixtures::orderWithLine($marketId, 43, BetOrderStatus::SettlementFailed, BetLineResult::Pending, 8_003);
        RepoFixtures::orderWithLine($marketId, 44, BetOrderStatus::Won, BetLineResult::Win, 8_004);

        $ids = $this->orders->idsPendingSettlementTouchingMarkets([$marketId]);

        sort($ids);
        $this->assertSame(
            [(int) $accepted['order']->id, (int) $failed['order']->id],
            $ids,
        );
    }

    public function test_applySettlementOutcome_updates_order_and_line(): void
    {
        $fixture = CatalogSeeder::oneXTwoSettlement();
        $seed = RepoFixtures::orderWithLine($fixture['market_id'], 42, BetOrderStatus::Accepted, BetLineResult::Pending, 8_005);

        $this->orders->applySettlementOutcome(
            $seed['order'],
            $seed['line'],
            BetLineResult::Win,
            BetOrderStatus::Won,
        );

        $seed['order']->refresh();
        $seed['line']->refresh();
        $this->assertSame(BetOrderStatus::Won, $seed['order']->status);
        $this->assertSame(BetLineResult::Win, $seed['line']->result);
    }

    public function test_findOrFail_throws_when_missing(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->orders->findOrFail(999_999);
    }
}
