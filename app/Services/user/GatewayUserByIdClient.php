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
     * GET `{gateway}/api/user/users/{id}`; {@see DownstreamPayload::extractData} on success body.
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
        $url = rtrim($baseUrl, '/').'/api/user/users/'.$userId;

        $response = Http::timeout($timeout)->acceptJson()->get($url);

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw new DownstreamServiceException(
                'Gateway user lookup failed with HTTP '.$response->status().'.'
            );
        }

        return DownstreamPayload::extractData($response->json(), 'gateway GET /api/user/users/{id}');
    }

    /**
     * GET `{gateway}/api/user/users` with query {@code user_ids} (comma-separated). {@see DownstreamPayload::extractData} on success body.
     *
     * @param  list<int>  $userIds
     * @return list<array<string, mixed>>
     *
     * @throws ConfigurationMissingException
     * @throws DownstreamServiceException
     */
    public function fetchMany(array $userIds): array
    {
        $normalized = [];
        foreach ($userIds as $id) {
            if ($id >= 1) {
                $normalized[$id] = true;
            }
        }
        $ids = array_keys($normalized);
        sort($ids);
        if ($ids === []) {
            return [];
        }

        $baseUrl = $this->resolvedGatewayBaseUrl->resolve();
        if ($baseUrl === '') {
            throw new ConfigurationMissingException('Missing API gateway base URL.');
        }

        $timeout = (int) config('bet_agg.foundation.timeout_seconds', 3);
        $url = rtrim($baseUrl, '/').'/api/user/users';

        $response = Http::timeout($timeout)->acceptJson()->get($url, [
            'user_ids' => implode(',', $ids),
        ]);

        if (! $response->successful()) {
            throw new DownstreamServiceException(
                'Gateway users lookup failed with HTTP '.$response->status().'.'
            );
        }

        $data = DownstreamPayload::extractData($response->json(), 'gateway GET /api/user/users');

        return $this->normalizeUsersListPayload($data);
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws DownstreamServiceException
     */
    private function normalizeUsersListPayload(mixed $data): array
    {
        if (! is_array($data)) {
            throw new DownstreamServiceException('Invalid gateway users list payload.');
        }

        if (isset($data['users']) && is_array($data['users'])) {
            $list = $data['users'];
        } elseif (isset($data['data']) && is_array($data['data']) && array_is_list($data['data'])) {
            // e.g. gateway wraps rows as { "data": [...], "next_offset", "total_num" }
            $list = $data['data'];
        } elseif (array_is_list($data)) {
            $list = $data;
        } else {
            throw new DownstreamServiceException('Invalid gateway users list payload.');
        }

        $out = [];
        foreach ($list as $row) {
            if (is_array($row)) {
                $out[] = $row;
            }
        }

        return $out;
    }
}
