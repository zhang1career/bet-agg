<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\MallDictionaryService;
use PHPUnit\Framework\TestCase;

final class MallDictionaryServiceTest extends TestCase
{
    public function test_resolve_returns_empty_for_empty_codes(): void
    {
        $svc = new MallDictionaryService;

        $this->assertSame([], $svc->resolve([]));
    }

    public function test_resolve_skips_empty_string_codes(): void
    {
        $svc = new MallDictionaryService;

        $this->assertSame([], $svc->resolve(['', '']));
    }

    public function test_resolve_ignores_unknown_codes(): void
    {
        $svc = new MallDictionaryService;

        $this->assertSame([], $svc->resolve(['not_a_real_dict']));
    }

    public function test_resolve_points_hold_state_uses_dictionary_labels(): void
    {
        $svc = new MallDictionaryService;

        $out = $svc->resolve(['points_hold_state']);

        $this->assertArrayHasKey('points_hold_state', $out);
        $rows = $out['points_hold_state'];
        $this->assertNotEmpty($rows);
        $first = $rows[0];
        $this->assertSame(['k', 'v'], array_keys($first));
        $this->assertSame('10', $first['v']);
        $this->assertSame('try pending', $first['k']);
    }

    public function test_resolve_returns_only_requested_known_codes(): void
    {
        $svc = new MallDictionaryService;

        $out = $svc->resolve(['game_status', 'unknown', 'market_status']);

        $this->assertArrayHasKey('game_status', $out);
        $this->assertArrayHasKey('market_status', $out);
        $this->assertArrayNotHasKey('unknown', $out);
    }
}
