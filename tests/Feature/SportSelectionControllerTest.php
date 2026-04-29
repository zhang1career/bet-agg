<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SportSeeder;
use Tests\TestCase;

final class SportSelectionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_open_selection(): void
    {
        SportSeeder::openSelection(1950);

        $response = $this->getJson('/api/bet/selections');

        $response->assertOk()
            ->assertJsonPath('errorCode', 0);
        $items = $response->json('data.items');
        $this->assertIsArray($items);
        $this->assertGreaterThanOrEqual(1, count($items));
    }

    public function test_show_returns_selection(): void
    {
        $sid = SportSeeder::openSelection(1950);

        $this->getJson('/api/bet/selections/'.$sid)
            ->assertOk()
            ->assertJsonPath('errorCode', 0)
            ->assertJsonPath('data.id', $sid);
    }
}
