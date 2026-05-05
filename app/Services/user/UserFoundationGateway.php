<?php

declare(strict_types=1);

namespace App\Services\user;

use App\Exceptions\ConfigurationMissingException;
use App\Exceptions\FoundationAuthRequiredException;
use App\Providers\AppServiceProvider;
use App\Services\api_gw\ResolvedApiGatewayBaseUrl;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Paganini\Aggregation\Exceptions\DownstreamServiceException;
use Paganini\Aggregation\Support\DownstreamPayload;

/**
 * Resolves the current user from the foundation user service.
 *
 * Performance:
 *  - **Request-scope memoization**: repeated calls within the same HTTP request reuse the
 *    in-memory result keyed by token (e.g. controller + middleware + downstream service all
 *    asking "who is this user?"). Requires the binding to be {@code scoped} (see
 *    {@see AppServiceProvider}).
 *  - **Cross-request short TTL cache**: the user payload is cached by {@code sha256(token)}
 *    in the configured cache store for {@see UserFoundationGateway::cacheTtlSeconds()}.
 *    The token itself is never persisted; only its hash and the response body.
 *  - **Invalidation**: a 401/403 from the upstream {@code GET /api/user/me} immediately
 *    drops both layers so the next call hits the upstream and returns a fresh
 *    {@see FoundationAuthRequiredException} when the credential is truly invalid.
 */
class UserFoundationGateway
{
    private const CACHE_KEY_PREFIX = 'bet-agg:auth:user:';

    /** @var array<string, array<string, mixed>>  in-memory request-scope memo, keyed by token hash */
    private array $memo = [];

    public function __construct(
        private readonly ResolvedApiGatewayBaseUrl $resolvedFoundationBaseUrl,
        private readonly CacheRepository $cache,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws ConfigurationMissingException
     * @throws DownstreamServiceException
     * @throws FoundationAuthRequiredException
     */
    public function fetchCurrentUser(Request $request): array
    {
        $token = trim((string) $request->header('X-User-Access-Token', ''));
        if ($token === '') {
            throw new FoundationAuthRequiredException(
                'Authentication required. Send header: X-User-Access-Token: <access_token> (raw JWT, no Bearer prefix).'
            );
        }

        $tokenHash = hash('sha256', $token);
        if (isset($this->memo[$tokenHash])) {
            return $this->memo[$tokenHash];
        }

        $cacheKey = self::CACHE_KEY_PREFIX.$tokenHash;
        $cached = $this->cache->get($cacheKey);
        if (is_array($cached)) {
            $this->memo[$tokenHash] = $cached;

            return $cached;
        }

        $user = $this->callFoundationService($token, $tokenHash, $cacheKey);
        $this->cache->put($cacheKey, $user, $this->cacheTtlSeconds());
        $this->memo[$tokenHash] = $user;

        return $user;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ConfigurationMissingException
     * @throws DownstreamServiceException
     * @throws FoundationAuthRequiredException
     */
    private function callFoundationService(string $token, string $tokenHash, string $cacheKey): array
    {
        $baseUrl = $this->resolvedFoundationBaseUrl->resolve();
        if ($baseUrl === '') {
            throw new ConfigurationMissingException('Missing user foundation base_url configuration.');
        }

        $timeout = (int) config('bet_agg.foundation.timeout_seconds', 3);
        $endpoint = (string) config('bet_agg.foundation.me_endpoint', '/api/user/me');

        $response = Http::timeout($timeout)
            ->withHeaders(['X-User-Access-Token' => $token])
            ->acceptJson()
            ->get($baseUrl.$endpoint);

        if (! $response->successful()) {
            $status = $response->status();
            if ($status === 401 || $status === 403) {
                $this->forgetCachedUser($tokenHash, $cacheKey);

                throw $this->authRequiredFromHttpResponse($response);
            }

            throw new DownstreamServiceException('Failed to fetch base user info from foundation service.');
        }

        try {
            return DownstreamPayload::extractData($response->json(), 'foundation user service');
        } catch (DownstreamServiceException $e) {
            if (str_contains(strtolower($e->getMessage()), 'login required')) {
                $this->forgetCachedUser($tokenHash, $cacheKey);

                throw new FoundationAuthRequiredException($e->getMessage(), 0, $e);
            }
            throw $e;
        }
    }

    private function forgetCachedUser(string $tokenHash, string $cacheKey): void
    {
        unset($this->memo[$tokenHash]);
        $this->cache->forget($cacheKey);
    }

    private function cacheTtlSeconds(): int
    {
        $ttl = (int) config('bet_agg.foundation.cache_ttl_seconds', 60);

        return $ttl < 1 ? 60 : $ttl;
    }

    private function authRequiredFromHttpResponse(ClientResponse $response): FoundationAuthRequiredException
    {
        $json = $response->json();
        $detail = 'login required';
        if (is_array($json) && isset($json['message']) && is_string($json['message']) && $json['message'] !== '') {
            $detail = $json['message'];
        }

        return new FoundationAuthRequiredException(
            'Downstream error from foundation user service: '.$detail
        );
    }
}
