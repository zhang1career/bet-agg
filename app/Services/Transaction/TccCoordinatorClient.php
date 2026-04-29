<?php

declare(strict_types=1);

namespace App\Services\Transaction;

use App\Enums\TccCancelReason;
use App\Services\api_gw\ResolvedApiGatewayBaseUrl;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final readonly class TccCoordinatorClient
{
    public function __construct(
        private ResolvedApiGatewayBaseUrl $gateway,
    ) {}

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function begin(array $body): array
    {
        $bizId = (int) config('bet_agg.tcc.flow_id', 0);
        if ($bizId < 1) {
            throw new RuntimeException('bet_agg.tcc.flow_id (BET_TCC_FLOW_ID) is not configured.');
        }
        $body['biz_id'] = $bizId;
        $url = $this->gateway->resolvePathSuffix('/api/tcc/tx');
        if ($url === '') {
            throw new RuntimeException('API gateway base URL is not configured.');
        }
        $timeout = (int) config('bet_agg.tcc.timeout_seconds', 15);
        $response = Http::timeout($timeout)->acceptJson()->asJson()->post($url, $body);
        if (! $response->successful()) {
            throw new RuntimeException('TCC begin HTTP '.$response->status());
        }

        return CoordinatorEnvelope::dataOrFail($response->json(), 'tcc begin');
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(string $idemKey): array
    {
        $key = trim($idemKey);
        if ($key === '' || ! ctype_digit($key)) {
            throw new RuntimeException('idem_key must be a non-empty decimal string.');
        }
        $url = $this->gateway->resolvePathSuffix('/api/tcc/tx/'.rawurlencode($key));
        if ($url === '') {
            throw new RuntimeException('API gateway base URL is not configured.');
        }
        $timeout = (int) config('bet_agg.tcc.timeout_seconds', 15);
        $response = Http::timeout($timeout)->acceptJson()->get($url);
        if (! $response->successful()) {
            throw new RuntimeException('TCC detail HTTP '.$response->status());
        }

        return CoordinatorEnvelope::dataOrFail($response->json(), 'tcc detail');
    }

    /**
     * @return array<string, mixed>
     */
    public function confirm(string $idemKey): array
    {
        $key = trim($idemKey);
        if ($key === '' || ! ctype_digit($key)) {
            throw new RuntimeException('idem_key must be a non-empty decimal string.');
        }
        $url = $this->gateway->resolvePathSuffix('/api/tcc/tx/'.rawurlencode($key).'/confirm');
        if ($url === '') {
            throw new RuntimeException('API gateway base URL is not configured.');
        }
        $timeout = (int) config('bet_agg.tcc.timeout_seconds', 15);
        $response = Http::timeout($timeout)->acceptJson()->asJson()->post($url, []);
        if (! $response->successful()) {
            throw new RuntimeException('TCC confirm HTTP '.$response->status());
        }

        return CoordinatorEnvelope::dataOrFail($response->json(), 'tcc confirm');
    }

    /**
     * @return array<string, mixed>
     */
    public function cancel(string $idemKey, TccCancelReason $reason): array
    {
        $key = trim($idemKey);
        if ($key === '' || ! ctype_digit($key)) {
            throw new RuntimeException('idem_key must be a non-empty decimal string.');
        }
        $url = $this->gateway->resolvePathSuffix('/api/tcc/tx/'.rawurlencode($key).'/cancel');
        if ($url === '') {
            throw new RuntimeException('API gateway base URL is not configured.');
        }
        $timeout = (int) config('bet_agg.tcc.timeout_seconds', 15);
        $response = Http::timeout($timeout)->acceptJson()->asJson()->post($url, [
            'cancel_reason' => $reason->value,
        ]);
        if (! $response->successful()) {
            throw new RuntimeException('TCC cancel HTTP '.$response->status());
        }

        return CoordinatorEnvelope::dataOrFail($response->json(), 'tcc cancel');
    }
}
