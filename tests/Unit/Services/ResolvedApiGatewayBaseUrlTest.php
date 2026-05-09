<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\api_gw\ResolvedApiGatewayBaseUrl;
use Tests\TestCase;

final class ResolvedApiGatewayBaseUrlTest extends TestCase
{
    public function test_resolve_trims_via_memoized_discovery(): void
    {
        config(['api_gw.base_url' => 'http://gw.test///']);

        $sut = $this->app->make(ResolvedApiGatewayBaseUrl::class);

        $this->assertSame('http://gw.test', $sut->resolve());
    }

    public function test_resolve_path_suffix_appends_normalized_path(): void
    {
        config(['api_gw.base_url' => 'http://gw.test']);

        $sut = $this->app->make(ResolvedApiGatewayBaseUrl::class);

        $this->assertSame('http://gw.test/api/foo', $sut->resolvePathSuffix('/api/foo'));
        $this->assertSame('http://gw.test/api/foo', $sut->resolvePathSuffix('api/foo'));
    }

    public function test_resolve_path_suffix_empty_when_base_unset(): void
    {
        config(['api_gw.base_url' => '']);

        $sut = $this->app->make(ResolvedApiGatewayBaseUrl::class);

        $this->assertSame('', $sut->resolvePathSuffix('/api/foo'));
    }
}
