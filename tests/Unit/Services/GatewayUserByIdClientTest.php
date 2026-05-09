<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\ConfigurationMissingException;
use App\Services\user\GatewayUserByIdClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Paganini\Aggregation\Exceptions\DownstreamServiceException;
use Tests\TestCase;

final class GatewayUserByIdClientTest extends TestCase
{
    public function test_fetch_returns_null_on_404(): void
    {
        config(['api_gw.base_url' => 'http://gw.test']);

        Http::fake([
            'http://gw.test/api/user/users/99' => Http::response([], 404),
        ]);

        $user = $this->app->make(GatewayUserByIdClient::class)->fetch(99);

        $this->assertNull($user);
    }

    public function test_fetch_returns_data_on_success(): void
    {
        config(['api_gw.base_url' => 'http://gw.test']);

        Http::fake([
            'http://gw.test/api/user/users/7' => Http::response([
                'errorCode' => 0,
                'message' => '',
                'data' => ['id' => 7, 'username' => 'seven'],
            ], 200),
        ]);

        $user = $this->app->make(GatewayUserByIdClient::class)->fetch(7);

        $this->assertSame(['id' => 7, 'username' => 'seven'], $user);
    }

    public function test_fetch_throws_when_gateway_base_missing(): void
    {
        config(['api_gw.base_url' => '']);

        $this->expectException(ConfigurationMissingException::class);
        $this->expectExceptionMessage('Missing API gateway base URL.');

        $this->app->make(GatewayUserByIdClient::class)->fetch(1);
    }

    public function test_fetch_throws_on_http_error(): void
    {
        config(['api_gw.base_url' => 'http://gw.test']);

        Http::fake([
            'http://gw.test/api/user/users/3' => Http::response([], 502),
        ]);

        $this->expectException(DownstreamServiceException::class);
        $this->expectExceptionMessage('HTTP 502');

        $this->app->make(GatewayUserByIdClient::class)->fetch(3);
    }

    public function test_fetch_many_returns_empty_without_positive_ids(): void
    {
        config(['api_gw.base_url' => 'http://gw.test']);

        $rows = $this->app->make(GatewayUserByIdClient::class)->fetchMany([]);
        $this->assertSame([], $rows);

        $rows = $this->app->make(GatewayUserByIdClient::class)->fetchMany([0, -1]);
        $this->assertSame([], $rows);

        Http::assertNothingSent();
    }

    public function test_fetch_many_dedupes_sorts_ids_and_queries_gateway(): void
    {
        config(['api_gw.base_url' => 'http://gw.test']);

        Http::fake([
            'http://gw.test/api/user/users*' => Http::response([
                'errorCode' => 0,
                'message' => '',
                'data' => ['users' => [['id' => 3]]],
            ], 200),
        ]);

        $this->app->make(GatewayUserByIdClient::class)->fetchMany([8, 3, 8]);

        Http::assertSent(static function (Request $request): bool {
            if ($request->method() !== 'GET' || ! str_starts_with($request->url(), 'http://gw.test/api/user/users')) {
                return false;
            }
            $q = parse_url($request->url(), PHP_URL_QUERY);
            parse_str(is_string($q) ? $q : '', $params);

            return ($params['user_ids'] ?? '') === '3,8';
        });
    }

    public function test_fetch_many_accepts_users_wrapped_payload(): void
    {
        config(['api_gw.base_url' => 'http://gw.test']);

        Http::fake([
            'http://gw.test/api/user/users*' => Http::response([
                'errorCode' => 0,
                'message' => '',
                'data' => ['users' => [['id' => 1, 'name' => 'a']]],
            ], 200),
        ]);

        $rows = $this->app->make(GatewayUserByIdClient::class)->fetchMany([1]);

        $this->assertSame([['id' => 1, 'name' => 'a']], $rows);
    }

    public function test_fetch_many_accepts_nested_data_list_payload(): void
    {
        config(['api_gw.base_url' => 'http://gw.test']);

        Http::fake([
            'http://gw.test/api/user/users*' => Http::response([
                'errorCode' => 0,
                'message' => '',
                'data' => [
                    'data' => [['id' => 2]],
                    'total_num' => 1,
                ],
            ], 200),
        ]);

        $rows = $this->app->make(GatewayUserByIdClient::class)->fetchMany([2]);

        $this->assertSame([['id' => 2]], $rows);
    }

    public function test_fetch_many_accepts_plain_list_payload(): void
    {
        config(['api_gw.base_url' => 'http://gw.test']);

        Http::fake([
            'http://gw.test/api/user/users*' => Http::response([
                'errorCode' => 0,
                'message' => '',
                'data' => [['id' => 5]],
            ], 200),
        ]);

        $rows = $this->app->make(GatewayUserByIdClient::class)->fetchMany([5]);

        $this->assertSame([['id' => 5]], $rows);
    }

    public function test_fetch_many_throws_when_gateway_base_missing(): void
    {
        config(['api_gw.base_url' => '']);

        $this->expectException(ConfigurationMissingException::class);

        $this->app->make(GatewayUserByIdClient::class)->fetchMany([1]);
    }

    public function test_fetch_many_throws_on_invalid_list_shape(): void
    {
        config(['api_gw.base_url' => 'http://gw.test']);

        Http::fake([
            'http://gw.test/api/user/users*' => Http::response([
                'errorCode' => 0,
                'message' => '',
                'data' => ['users' => 'not-a-list'],
            ], 200),
        ]);

        $this->expectException(DownstreamServiceException::class);
        $this->expectExceptionMessage('Invalid gateway users list payload.');

        $this->app->make(GatewayUserByIdClient::class)->fetchMany([1]);
    }
}
