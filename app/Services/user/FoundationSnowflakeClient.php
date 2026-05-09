<?php

declare(strict_types=1);

namespace App\Services\user;

use App\Exceptions\ConfigurationMissingException;
use App\Services\api_gw\ResolvedApiGatewayBaseUrl;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Paganini\Aggregation\Exceptions\DownstreamServiceException;
use Paganini\Aggregation\Support\DownstreamPayload;

/**
 * Mints a decimal snowflake via service_foundation {@code POST /api/snowflake/id} using the
 * server-side {@see SF_SNOWFLAKE_ACCESS_KEY} (never exposed to API clients).
 */
final readonly class FoundationSnowflakeClient
{
    public function __construct(private ResolvedApiGatewayBaseUrl $resolvedGatewayBaseUrl) {}

    /**
     * @throws BindingResolutionException
     * @throws ConfigurationMissingException
     * @throws ConnectionException
     * @throws DownstreamServiceException
     */
    public function mintNextId(): string
    {
        $accessKey = trim((string) config('bet_agg.snowflake.access_key', ''));
        if ($accessKey === '') {
            throw new ConfigurationMissingException(
                'Snowflake minting is not configured. Set SF_SNOWFLAKE_ACCESS_KEY in the environment.'
            );
        }

        $baseUrl = $this->resolvedGatewayBaseUrl->resolve();
        if ($baseUrl === '') {
            throw new ConfigurationMissingException('Missing API gateway base URL.');
        }

        $path = (string) config('bet_agg.snowflake.mint_endpoint', '/api/snowflake/id');
        $url = rtrim($baseUrl, '/').'/'.ltrim($path, '/');

        $timeout = (int) config('bet_agg.foundation.timeout_seconds', 3);

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->asJson()
            ->post($url, ['access_key' => $accessKey]);

        if (! $response->successful()) {
            throw new DownstreamServiceException(
                'Foundation snowflake mint failed with HTTP '.$response->status().'.'
            );
        }

        $data = DownstreamPayload::extractData($response->json(), 'foundation snowflake mint');

        return $this->parseSnowflakeFromData($data);
    }

    /**
     * @throws DownstreamServiceException
     */
    private function parseSnowflakeFromData(mixed $data): string
    {
        if (is_int($data)) {
            if ($data < 1) {
                throw new DownstreamServiceException('Foundation snowflake mint returned invalid id.');
            }

            return (string) $data;
        }

        if (is_string($data)) {
            $trimmed = trim($data);
            if ($trimmed !== '' && ctype_digit($trimmed) && (int) $trimmed >= 1) {
                return $trimmed;
            }

            throw new DownstreamServiceException('Foundation snowflake mint returned invalid id.');
        }

        if (is_array($data)) {
            foreach (['id', 'snowflake_id', 'snowflakeId'] as $key) {
                if (array_key_exists($key, $data)) {
                    return $this->parseSnowflakeFromData($data[$key]);
                }
            }
        }

        throw new DownstreamServiceException('Foundation snowflake mint response missing numeric id.');
    }
}
