<?php

declare(strict_types=1);

namespace App\Services\user;

use App\Exceptions\ConfigurationMissingException;
use App\Services\api_gw\ResolvedApiGatewayBaseUrl;
use Illuminate\Support\Facades\Http;
use Paganini\Aggregation\Exceptions\DownstreamServiceException;
use Paganini\Aggregation\Support\DownstreamPayload;

readonly class GatewayUserByIdClient
{
    public function __construct(private ResolvedApiGatewayBaseUrl $resolvedGatewayBaseUrl) {}

    /**
     * GET `{gateway}/api/users/{id}`; {@see DownstreamPayload::extractData} on success body.
     *
     * @return array<string, mixed>|null absent when HTTP 404
     *
     * @throws ConfigurationMissingException
     * @throws DownstreamServiceException
     */
    public function fetch(int $userId): ?array
    {
        $baseUrl = $this->resolvedGatewayBaseUrl->resolve();
        if ($baseUrl === '') {
            throw new ConfigurationMissingException('Missing API gateway base URL.');
        }

        $timeout = (int) config('bet_agg.foundation.timeout_seconds', 3);
        $url = rtrim($baseUrl, '/').'/api/users/'.$userId;

        $response = Http::timeout($timeout)->acceptJson()->get($url);

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw new DownstreamServiceException(
                'Gateway user lookup failed with HTTP '.$response->status().'.'
            );
        }

        return DownstreamPayload::extractData($response->json(), 'gateway GET /api/users/{id}');
    }
}
