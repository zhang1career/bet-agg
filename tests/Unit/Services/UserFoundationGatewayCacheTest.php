<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\FoundationAuthRequiredException;
use App\Services\user\UserFoundationGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Paganini\Constants\ResponseConstant;
use Tests\TestCase;

class UserFoundationGatewayCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // PHPUnit without phpunit.xml may leave env unset; gateway uses Cache facade for TTL layer.
        config(['cache.default' => 'array']);
    }

    public function test_repeat_call_within_request_uses_memo_no_extra_http(): void
    {
        $this->fakeMeOk(['id' => 7, 'username' => 'u7']);

        $gateway = $this->app->make(UserFoundationGateway::class);
        $req = $this->makeRequestWithToken('jwt-abc');

        $first = $gateway->fetchCurrentUser($req);
        $second = $gateway->fetchCurrentUser($req);

        $this->assertSame(7, $first['id']);
        $this->assertSame($first, $second);
        Http::assertSentCount(1);
    }

    public function test_separate_request_scopes_share_through_ttl_cache(): void
    {
        $this->fakeMeOk(['id' => 9]);

        $req = $this->makeRequestWithToken('jwt-shared');

        $first = $this->app->make(UserFoundationGateway::class)->fetchCurrentUser($req);
        // Drop the request-scoped instance so the second call bypasses memo and falls back to the shared cache.
        $this->app->forgetScopedInstances();
        $second = $this->app->make(UserFoundationGateway::class)->fetchCurrentUser($req);

        $this->assertSame(9, $first['id']);
        $this->assertSame($first, $second);
        Http::assertSentCount(1);
    }

    public function test_401_response_is_not_cached_and_throws_auth_required(): void
    {
        Http::fake([
            'http://gw.test/api/user/me' => Http::response(['message' => 'expired token'], 401),
        ]);

        $req = $this->makeRequestWithToken('jwt-poison');

        try {
            $this->app->make(UserFoundationGateway::class)->fetchCurrentUser($req);
            $this->fail('Expected FoundationAuthRequiredException');
        } catch (FoundationAuthRequiredException $e) {
            $this->assertStringContainsString('expired token', $e->getMessage());
        }

        $this->assertNull($this->cachedUser('jwt-poison'));
    }

    public function test_403_response_is_not_cached_and_throws_auth_required(): void
    {
        Http::fake([
            'http://gw.test/api/user/me' => Http::response(['message' => 'forbidden'], 403),
        ]);

        $req = $this->makeRequestWithToken('jwt-revoked');

        try {
            $this->app->make(UserFoundationGateway::class)->fetchCurrentUser($req);
            $this->fail('Expected FoundationAuthRequiredException');
        } catch (FoundationAuthRequiredException $e) {
            $this->assertStringContainsString('forbidden', $e->getMessage());
        }

        $this->assertNull($this->cachedUser('jwt-revoked'));
    }

    public function test_401_on_upstream_roundtrip_throws_auth_required(): void
    {
        // Cache empty / miss so the gateway calls upstream (same outcome as after TTL expiry).
        $req = $this->makeRequestWithToken('jwt-was-valid');

        Http::fake([
            'http://gw.test/api/user/me' => Http::response(['message' => 'expired token'], 401),
        ]);

        $this->expectException(FoundationAuthRequiredException::class);
        $this->app->make(UserFoundationGateway::class)->fetchCurrentUser($req);
    }

    public function test_missing_token_short_circuits_to_auth_required(): void
    {
        $this->expectException(FoundationAuthRequiredException::class);
        $this->app->make(UserFoundationGateway::class)
            ->fetchCurrentUser(Request::create('/', 'GET'));
    }

    /** @param array<string, mixed> $userPayload */
    private function fakeMeOk(array $userPayload): void
    {
        Http::fake([
            'http://gw.test/api/user/me' => Http::response([
                'errorCode' => ResponseConstant::RET_OK,
                'message' => '',
                'data' => $userPayload,
            ], 200),
        ]);
    }

    private function makeRequestWithToken(string $token): Request
    {
        $req = Request::create('/', 'GET');
        $req->headers->set('X-User-Access-Token', $token);

        return $req;
    }

    private function cachedUser(string $token): ?array
    {
        $value = Cache::get('bet-agg:auth:user:'.hash('sha256', $token));

        return is_array($value) ? $value : null;
    }
}
