<?php

declare(strict_types=1);

namespace Tests\Unit\Repos;

use App\Enums\BetLineResult;
use App\Repos\mall\BetOrderRepo;
use App\Repos\mall\OrderItemRepo;
use Tests\Support\CatalogSeeder;
use Tests\TestCase;

final class OrderItemRepoTest extends TestCase
{
    public function test_createForOrder_persists_line(): void
    {
        $fixture = CatalogSeeder::oneXTwoSettlement();
        $order = app(BetOrderRepo::class)->createAccepted(42, 7_001);
        $items = app(OrderItemRepo::class);

        $line = $items->createForOrder(
            (int) $order->id,
            $fixture['market_id'],
            ['code' => 'away_win'],
            'Away win',
            BetLineResult::Pending,
        );

        $this->assertGreaterThan(0, $line->id);
        $this->assertSame((int) $order->id, $line->oid);
        $this->assertSame($fixture['market_id'], $line->mid);
        $this->assertSame('away_win', $line->selection['code'] ?? null);
        $this->assertSame(BetLineResult::Pending, $line->result);
    }
}
