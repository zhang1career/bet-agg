<?php

declare(strict_types=1);

namespace App\Services\mall\serv_fd;

use App\Services\api_gw\ResolvedApiGatewayBaseUrl;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Paganini\Aggregation\Exceptions\DownstreamServiceException;
use Paganini\Aggregation\Support\DownstreamPayload;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * CMS content API client for games (e.g. {@code POST/PUT /api/cms/game}), analogous to mall {@see CmsProductClient}.
 *
 * @see https://github.com/... api_cms.json — dynamic columns per content type; write keys must match CMS {@code fields.columns}.
 */
final readonly class CmsGameClient
{
    public function __construct(
        private string $baseUrl,
        private string $contentRoute,
        private int $timeoutSeconds,
    ) {}

    public static function fromConfig(): self
    {
        /** @var ResolvedApiGatewayBaseUrl $foundationBase */
        $foundationBase = app(ResolvedApiGatewayBaseUrl::class);
        $base = $foundationBase->resolve();
        $cmsUrl = (string) config('api_gw.cms.cms_url', '');
        $route = (string) config('api_gw.cms.game_route', 'game');
        $timeout = (int) config('api_gw.timeout_seconds', 3);
        if ($base === '') {
            throw new RuntimeException('Missing API gateway base URL (API_GATEWAY_BASE_URL).');
        }
        if ($cmsUrl === '') {
            throw new RuntimeException('Missing API gateway CMS URL (API_GATEWAY_CMS_URL).');
        }

        return new self($base.$cmsUrl, $route, $timeout);
    }

    /**
     * @return array{items: list<array<string, mixed>>, pagination: array<string, mixed>}
     *
     * @throws ConnectionException
     */
    public function paginate(int $page = 1, int $perPage = 15): array
    {
        $response = Http::timeout($this->timeoutSeconds)
            ->acceptJson()
            ->get($this->listUrl(), [
                'page' => $page,
                'per_page' => $perPage,
            ]);

        if ($response->status() === 404) {
            throw new NotFoundHttpException('CMS game route not found: '.$this->contentRoute);
        }

        if (! $response->successful()) {
            throw new DownstreamServiceException(
                sprintf('CMS game list failed with HTTP %d.', $response->status())
            );
        }

        $data = DownstreamPayload::extractData($response->json(), 'cms game list');
        if (! isset($data['items'], $data['pagination']) || ! is_array($data['items']) || ! is_array($data['pagination'])) {
            throw new DownstreamServiceException('Invalid CMS game list payload: missing items or pagination.');
        }

        return [
            'items' => $data['items'],
            'pagination' => $data['pagination'],
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    public function find(int $id): array
    {
        $response = Http::timeout($this->timeoutSeconds)
            ->acceptJson()
            ->get($this->itemUrl($id));

        if ($response->status() === 404) {
            throw new NotFoundHttpException('Game not found.');
        }

        if (! $response->successful()) {
            throw new DownstreamServiceException(
                sprintf('CMS game detail failed with HTTP %d.', $response->status())
            );
        }

        return DownstreamPayload::extractData($response->json(), 'cms game detail');
    }

    /**
     * Batch detail: {@code GET .../{game_route}/batch-detail?ids=1,2,3}.
     *
     * @param  list<int>  $ids  Positive integers; duplicates are sent once (server-defined order of results).
     * @return array<int, array<string, mixed>> Map of CMS record id → detail payload (same shape as {@see find}).
     *
     * @throws ConnectionException
     */
    public function findManyById(array $ids): array
    {
        $normalized = [];
        foreach ($ids as $x) {
            if (! is_int($x) || $x < 1) {
                continue;
            }
            $normalized[$x] = true;
        }
        $unique = array_keys($normalized);
        if ($unique === []) {
            return [];
        }

        $response = Http::timeout($this->timeoutSeconds)
            ->acceptJson()
            ->get($this->batchDetailUrl(), [
                'ids' => implode(',', array_map(static fn (int $i): string => (string) $i, $unique)),
            ]);

        if ($response->status() === 404) {
            throw new NotFoundHttpException('CMS game route not found: '.$this->contentRoute);
        }

        if (! $response->successful()) {
            throw new DownstreamServiceException(
                sprintf('CMS game batch detail failed with HTTP %d.', $response->status())
            );
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new DownstreamServiceException('Invalid CMS game batch detail JSON.');
        }
        if ((int) ($json['errorCode'] ?? -1) === 100) {
            $message = (string) ($json['message'] ?? 'validation failed');

            throw new DownstreamServiceException('CMS game batch detail: '.$message);
        }

        $data = DownstreamPayload::extractData($json, 'cms game batch detail');
        if (! isset($data['items']) || ! is_array($data['items'])) {
            throw new DownstreamServiceException('Invalid CMS game batch detail payload: missing items.');
        }

        $out = [];
        foreach ($data['items'] as $row) {
            if (! is_array($row) || ! isset($row['id'])) {
                continue;
            }
            $out[(int) $row['id']] = $row;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $fields  Keys must match CMS game columns (e.g. title, starts_at, banner, main_media).
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    public function create(array $fields): array
    {
        $response = Http::timeout($this->timeoutSeconds)
            ->acceptJson()
            ->asJson()
            ->post($this->listUrl(), $this->filterGamePayload($fields));

        return $this->unwrapWriteResponse($response, 'cms game create', [200, 201]);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    public function update(int $id, array $fields): array
    {
        $response = Http::timeout($this->timeoutSeconds)
            ->acceptJson()
            ->asJson()
            ->put($this->itemUrl($id), $this->filterGamePayload($fields));

        return $this->unwrapWriteResponse($response, 'cms game update', [200]);
    }

    /**
     * @throws ConnectionException
     */
    public function delete(int $id): void
    {
        $response = Http::timeout($this->timeoutSeconds)
            ->acceptJson()
            ->delete($this->itemUrl($id));

        if ($response->status() === 404) {
            throw new DownstreamServiceException('CMS game not found: '.$id);
        }

        if (! $response->successful()) {
            throw new DownstreamServiceException(
                sprintf('CMS game delete failed with HTTP %d.', $response->status())
            );
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new DownstreamServiceException('Invalid CMS game delete payload.');
        }
        if ((int) ($json['errorCode'] ?? -1) !== 0) {
            $message = (string) ($json['message'] ?? 'downstream error');

            throw new DownstreamServiceException('CMS game delete error: '.$message);
        }
    }

    private function listUrl(): string
    {
        return $this->baseUrl.$this->contentRoute;
    }

    private function itemUrl(int $id): string
    {
        return $this->baseUrl.$this->contentRoute.'/'.$id;
    }

    private function batchDetailUrl(): string
    {
        return $this->baseUrl.$this->contentRoute.'/batch-detail';
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function filterGamePayload(array $fields): array
    {
        $out = [];
        foreach (['title', 'banner', 'main_media'] as $key) {
            if (! array_key_exists($key, $fields)) {
                continue;
            }
            $value = $fields[$key];
            if ($value === null) {
                continue;
            }
            if (! is_string($value)) {
                throw new RuntimeException('Field '.$key.' must be a string.');
            }
            $out[$key] = $value;
        }
        if (array_key_exists('starts_at', $fields) && $fields['starts_at'] !== null) {
            $v = $fields['starts_at'];
            if (! is_int($v)) {
                throw new RuntimeException('Field starts_at must be an integer.');
            }
            $out['starts_at'] = $v;
        }

        return $out;
    }

    /**
     * @param  list<int>  $successStatuses
     * @return array<string, mixed>
     */
    private function unwrapWriteResponse(Response $response, string $label, array $successStatuses): array
    {
        $status = $response->status();
        if (! in_array($status, $successStatuses, true)) {
            throw new DownstreamServiceException(
                sprintf('%s failed with HTTP %d.', $label, $status)
            );
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new DownstreamServiceException('Invalid JSON from '.$label);
        }

        if ((int) ($json['errorCode'] ?? -1) === 100) {
            $message = (string) ($json['message'] ?? 'validation failed');
            $data = $json['data'] ?? null;
            if (is_array($data)) {
                $first = '';
                foreach ($data as $k => $v) {
                    if (is_string($v)) {
                        $first = $k.': '.$v;
                        break;
                    }
                }
                if ($first !== '') {
                    $message = $first;
                }
            }

            throw new DownstreamServiceException($label.' validation: '.$message);
        }

        return DownstreamPayload::extractData($json, $label);
    }
}
