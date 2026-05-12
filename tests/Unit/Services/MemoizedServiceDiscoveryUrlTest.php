<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\api_gw\MemoizedServiceDiscoveryUrl;
use Tests\TestCase;

final class MemoizedServiceDiscoveryUrlTest extends TestCase
{
    public function test_resolve_rtrimmed_empty_returns_empty(): void
    {
        $sut = $this->app->make(MemoizedServiceDiscoveryUrl::class);

        $this->assertSame('', $sut->resolveRtrimmed(''));
    }

    public function test_resolve_rtrimmed_plain_url_trims_trailing_slashes_without_redis(): void
    {
        $sut = $this->app->make(MemoizedServiceDiscoveryUrl::class);

        $this->assertSame('http://gw.test', $sut->resolveRtrimmed('http://gw.test///'));
        $this->assertSame('http://gw.test', $sut->resolveRtrimmed('http://gw.test'));
    }
}
