<?php

declare(strict_types=1);

namespace Tests\Unit\Repos;

use App\Enums\MarketStatus;
use App\Enums\MarketType;
use App\Models\Game;
use App\Models\Market;
use App\Repos\mall\MarketRepo;
use Tests\Support\CatalogSeeder;
use Tests\TestCase;

final class MarketRepoTest extends TestCase
{
    private MarketRepo $markets;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markets = app(MarketRepo::class);
    }

    public function test_createForAdmin_and_existsById(): void
    {
        $game = CatalogSeeder::seedGame();

        $market = $this->markets->createForAdmin(
            (int) $game->id,
            MarketType::OneX2,
            'Full-time',
            MarketStatus::Open,
        );

        $this->assertTrue($this->markets->existsById((int) $market->id));
        $this->assertSame('Full-time', $market->name);
    }

    public function test_idsForGame_returns_market_ids(): void
    {
        $fixture = CatalogSeeder::oneXTwoSettlement();

        $ids = $this->markets->idsForGame($fixture['game_local_id']);

        $this->assertSame([$fixture['market_id']], $ids);
    }

    public function test_markAllSettledForGame_updates_status(): void
    {
        $fixture = CatalogSeeder::oneXTwoSettlement();
        $now = 1_700_000_000_000;

        $this->markets->markAllSettledForGame($fixture['game_local_id'], $now);

        $market = Market::query()->whereKey($fixture['market_id'])->firstOrFail();
        $this->assertSame(Market::STATUS_SETTLED, $market->status);
        $this->assertSame($now, $market->ut);
    }

    public function test_paginateForAdmin_includes_parent_game(): void
    {
        CatalogSeeder::oneXTwoSettlement();

        $page = $this->markets->paginateForAdmin(10);

        $this->assertGreaterThanOrEqual(1, $page->total());
        $this->assertNotNull($page->items()[0]->game);
        $this->assertInstanceOf(Game::class, $page->items()[0]->game);
    }
}
