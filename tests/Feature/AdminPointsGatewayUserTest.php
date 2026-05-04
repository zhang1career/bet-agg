<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Paganini\Constants\ResponseConstant;
use Tests\TestCase;

class AdminPointsGatewayUserTest extends TestCase
{
    public function test_gateway_user_proxy_returns_payload(): void
    {
        Http::fake([
            'http://gw.test/api/user/users/7' => Http::response([
                'errorCode' => ResponseConstant::RET_OK,
                'data' => ['id' => 7, 'username' => 'u7'],
                'message' => '',
            ], 200),
        ]);

        $this->getJson(route('admin.users.show', ['user_id' => 7]))
            ->assertOk()
            ->assertJsonPath('user.id', 7)
            ->assertJsonPath('user.username', 'u7');
    }

    public function test_gateway_user_proxy_maps_404(): void
    {
        Http::fake([
            'http://gw.test/api/user/users/99' => Http::response([], 404),
        ]);

        $this->getJson(route('admin.users.show', ['user_id' => 99]))
            ->assertNotFound()
            ->assertJsonPath('message', 'User not found.');
    }

    public function test_gateway_users_batch_proxy_returns_payload(): void
    {
        Http::fake(function (Request $request) {
            $path = (string) parse_url((string) $request->url(), PHP_URL_PATH);

            if (! str_ends_with($path, '/api/user/users')) {
                return Http::response([], 404);
            }

            $query = [];
            parse_str((string) parse_url((string) $request->url(), PHP_URL_QUERY), $query);
            $this->assertSame('7,9', $query['user_ids'] ?? null);

            return Http::response([
                'errorCode' => ResponseConstant::RET_OK,
                'data' => [
                    ['id' => 7, 'username' => 'u7'],
                    ['id' => 9, 'username' => 'u9'],
                ],
                'message' => '',
            ], 200);
        });

        $this->getJson(route('admin.users.index').'?user_ids='.rawurlencode('7,9'))
            ->assertOk()
            ->assertJsonPath('users.0.id', 7)
            ->assertJsonPath('users.0.username', 'u7')
            ->assertJsonPath('users.1.username', 'u9');
    }

    public function test_gateway_users_batch_proxy_accepts_wrapped_users_key(): void
    {
        Http::fake([
            'http://gw.test/api/user/users*' => Http::response([
                'errorCode' => ResponseConstant::RET_OK,
                'data' => [
                    'users' => [
                        ['id' => 3, 'username' => 'three'],
                    ],
                ],
                'message' => '',
            ], 200),
        ]);

        $this->getJson(route('admin.users.index', ['user_ids' => '3']))
            ->assertOk()
            ->assertJsonPath('users.0.username', 'three');
    }

    public function test_gateway_users_batch_proxy_accepts_paginated_data_wrapper(): void
    {
        Http::fake([
            'http://gw.test/api/user/users*' => Http::response([
                'errorCode' => ResponseConstant::RET_OK,
                'data' => [
                    'data' => [
                        [
                            'id' => 10_000_000,
                            'username' => 'test',
                            'email' => 'test@example.com',
                        ],
                    ],
                    'next_offset' => null,
                    'total_num' => 1,
                ],
                'message' => '',
            ], 200),
        ]);

        $this->getJson(route('admin.users.index', ['user_ids' => '10000000']))
            ->assertOk()
            ->assertJsonPath('users.0.id', 10_000_000)
            ->assertJsonPath('users.0.username', 'test');
    }
}
