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

    public function test_resolve_order_item_result_uses_dictionary_labels(): void
    {
        $svc = new MallDictionaryService;

        $out = $svc->resolve(['order_item_result']);

        $this->assertArrayHasKey('order_item_result', $out);
        $rows = $out['order_item_result'];
        $this->assertNotEmpty($rows);
        $first = $rows[0];
        $this->assertSame(['k', 'v'], array_keys($first));
        $this->assertSame('0', $first['v']);
        $this->assertSame('pending', $first['k']);
    }

    public function test_resolve_market_type_uses_dictionary_labels(): void
    {
        $svc = new MallDictionaryService;

        $out = $svc->resolve(['market_type']);

        $this->assertArrayHasKey('market_type', $out);
        $rows = $out['market_type'];
        $this->assertNotEmpty($rows);
        $first = $rows[0];
        $this->assertSame('0', $first['v']);
        $this->assertSame('胜平负', $first['k']);
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
