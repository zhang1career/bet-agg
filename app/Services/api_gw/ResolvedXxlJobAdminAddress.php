<?php

declare(strict_types=1);

namespace App\Services\api_gw;

use Illuminate\Contracts\Container\BindingResolutionException;

/**
 * Resolves `xxl.admin_address` (from env `XXL_JOB_ADMIN_ADDRESS`) with the same
 * `://{{service_key}}` Redis service-discovery rules as {@see ResolvedApiGatewayBaseUrl}.
 */
final readonly class ResolvedXxlJobAdminAddress
{
    public function __construct(
        private MemoizedServiceDiscoveryUrl $serviceDiscoveryUrl,
    ) {}

    /**
     * @throws BindingResolutionException
     */
    public function resolve(): string
    {
        return $this->serviceDiscoveryUrl->resolveRtrimmed(
            (string) config('xxl.admin_address')
        );
    }
}
