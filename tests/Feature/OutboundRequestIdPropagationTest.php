<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Client\OutboundRequestIdMiddleware;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Paganini\Constants\ResponseConstant;
use Tests\TestCase;

/**
 * Asserts the X-Request-Id header is forwarded from the inbound HTTP request to
 * outbound Foundation/CMS calls so all log lines for one user action share a
 * single correlation id.
 */
final class OutboundRequestIdPropagationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbound_request_id_is_attached_to_outbound_foundation_call(): void
    {
        Http::fake([
            'http://foundation.local/api/user/me' => Http::response([
                'errorCode' => ResponseConstant::RET_OK,
                'data' => ['id' => 42, 'username' => 'agent'],
                'message' => '',
            ], 200),
        ]);

        config()->set('api_gw.base_url', 'http://foundation.local');
        config()->set('bet_agg.foundation.base_url', 'http://foundation.local');
        config()->set('bet_agg.foundation.me_endpoint', '/api/user/me');

        $this->withHeaders([
            'X-User-Access-Token' => 'jwt.test.token',
            OutboundRequestIdMiddleware::HEADER => 'req-corr-abc-123',
        ])->getJson('/api/bet/points')->assertOk();

        Http::assertSent(static function ($request): bool {
            return $request->url() === 'http://foundation.local/api/user/me'
                && $request->hasHeader(OutboundRequestIdMiddleware::HEADER, 'req-corr-abc-123');
        });
    }
}
