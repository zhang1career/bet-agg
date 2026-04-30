<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SportEvent;
use App\Models\SportSelection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SportSeeder;
use Tests\TestCase;

final class BetCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_events_list_includes_only_open_events(): void
    {
        $open = new SportEvent([
            'name' => 'Open match',
            'starts_at' => SportEvent::nowMillis(),
            'status' => SportEvent::STATUS_OPEN,
        ]);
        $open->save();

        (new SportEvent([
            'name' => 'Closed match',
            'starts_at' => SportEvent::nowMillis(),
            'status' => SportEvent::STATUS_CLOSED,
        ]))->save();

        $res = $this->getJson('/api/bet/events?per_page=50');
        $res->assertOk();
        $ids = collect($res->json('data.items'))->pluck('id')->all();
        $this->assertContains($open->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_event_show_returns_404_for_missing_id(): void
    {
        $this->getJson('/api/bet/events/9999991')->assertNotFound();
    }

    public function test_event_show_returns_row(): void
    {
        $e = new SportEvent([
            'name' => 'Listed',
            'starts_at' => SportEvent::nowMillis(),
            'status' => SportEvent::STATUS_CLOSED,
        ]);
        $e->save();

        $res = $this->getJson('/api/bet/events/'.$e->id);
        $res->assertOk();
        $this->assertSame($e->id, $res->json('data.id'));
        $this->assertSame('Listed', $res->json('data.name'));
        $this->assertSame([], $res->json('data.winning_selection_ids'));
    }

    public function test_markets_list_filters_by_event_id(): void
    {
        SportSeeder::openSelection(2000);
        $e2 = new SportEvent([
            'name' => 'Other',
            'starts_at' => SportEvent::nowMillis(),
            'status' => SportEvent::STATUS_OPEN,
        ]);
        $e2->save();

        $all = $this->getJson('/api/bet/markets?per_page=50');
        $all->assertOk();
        $this->assertGreaterThanOrEqual(1, count($all->json('data.items')));

        $empty = $this->getJson('/api/bet/markets?event_id='.$e2->id);
        $empty->assertOk();
        $this->assertCount(0, $empty->json('data.items'));
    }

    public function test_market_show_returns_nested_event(): void
    {
        $sid = SportSeeder::openSelection(2000);
        $sel = SportSelection::query()->findOrFail($sid);
        $marketId = (int) $sel->market_id;

        $res = $this->getJson('/api/bet/markets/'.$marketId);
        $res->assertOk();
        $this->assertSame($marketId, $res->json('data.id'));
        $this->assertArrayHasKey('event', $res->json('data'));
    }

    public function test_selections_list_accepts_market_id(): void
    {
        $sid = SportSeeder::openSelection(2000);
        $sel = SportSelection::query()->findOrFail($sid);
        $mid = (int) $sel->market_id;

        $res = $this->getJson('/api/bet/selections?market_id='.$mid);
        $res->assertOk();
        $items = $res->json('data.items');
        $this->assertNotEmpty($items);
        foreach ($items as $row) {
            $this->assertSame($mid, $row['market']['id']);
        }
    }

    public function test_selections_list_rejects_invalid_market_id(): void
    {
        $this->getJson('/api/bet/selections?market_id=0')->assertUnprocessable();
    }
}
