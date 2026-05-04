<?php

declare(strict_types=1);

namespace Tests\Feature;

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
}
