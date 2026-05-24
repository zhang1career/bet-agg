<?php

declare(strict_types=1);

namespace Tests\Unit\Repos;

use App\Enums\BetLineResult;
use App\Enums\MatchOutcomeCode;
use App\Enums\QuoteHistInterval;
use App\Repos\mall\MarketQuoteRepo;
use App\Repos\mall\OrderItemRepo;
use Tests\Support\CatalogSeeder;
use Tests\TestCase;

final class MarketQuoteRepoTest extends TestCase
{
    private MarketQuoteRepo $quotes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->quotes = app(MarketQuoteRepo::class);
    }

    public function test_seedEmptySnapshots_and_findSnapshotsByMarketIds(): void
    {
        $fixture = CatalogSeeder::oneXTwoSettlement();
        $marketId = $fixture['market_id'];
        $ut = 1_700_000_000_000;

        $this->quotes->seedEmptySnapshots($marketId, $ut);

        $grouped = $this->quotes->findSnapshotsByMarketIds([$marketId]);

        $this->assertArrayHasKey($marketId, $grouped);
        $this->assertCount(count(MatchOutcomeCode::allValues()), $grouped[$marketId]);
    }

    public function test_saveSnapshots_updates_pick_counts(): void
    {
        $fixture = CatalogSeeder::oneXTwoSettlement();
        $marketId = $fixture['market_id'];
        $ut = 1_700_000_000_000;

        $this->quotes->seedEmptySnapshots($marketId, $ut);
        $this->quotes->saveSnapshots($marketId, [
            MatchOutcomeCode::HomeWin->value => ['pick_count' => 3, 'share_bp' => 6000],
        ], $ut + 1);

        $rows = $this->quotes->findSnapshotsByMarketIds([$marketId])[$marketId];
        $home = collect($rows)->firstWhere('outcome_code', MatchOutcomeCode::HomeWin->value);

        $this->assertNotNull($home);
        $this->assertSame(3, $home->pick_count);
        $this->assertSame(6000, $home->share_bp);
    }

    public function test_upsertHistBucket_and_findHistBuckets(): void
    {
        $fixture = CatalogSeeder::oneXTwoSettlement();
        $marketId = $fixture['market_id'];
        $bucket = 1_700_000_000_000;
        $now = $bucket + 60_000;

        $this->quotes->upsertHistBucket(
            $marketId,
            QuoteHistInterval::Hour,
            $bucket,
            [MatchOutcomeCode::HomeWin->value => ['pick_count' => 2, 'share_bp' => 5000]],
            $now,
        );

        $rows = $this->quotes->findHistBuckets(
            $marketId,
            QuoteHistInterval::Hour,
            $bucket,
            $bucket,
            MatchOutcomeCode::HomeWin->value,
        );

        $this->assertCount(1, $rows);
        $this->assertSame(2, $rows[0]->pick_count);
    }
}
