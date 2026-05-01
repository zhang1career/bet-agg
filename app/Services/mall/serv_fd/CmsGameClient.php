<?php

declare(strict_types=1);

namespace App\Services\mall\serv_fd;

use App\Services\api_gw\ResolvedApiGatewayBaseUrl;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Paganini\Aggregation\Exceptions\DownstreamServiceException;
use Paganini\Aggregation\Support\DownstreamPayload;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * CMS content API client for games (e.g. {@code /api/cms/game/}), analogous to mall {@see CmsProductClient}.
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

    private function listUrl(): string
    {
        return $this->baseUrl.$this->contentRoute;
    }

    private function itemUrl(int $id): string
    {
        return $this->baseUrl.$this->contentRoute.'/'.$id;
    }
}
