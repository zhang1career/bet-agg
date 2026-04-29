<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BetOrderStatus;
use App\Enums\CheckoutPhase;
use App\Models\BetOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\SportSeeder;
use Tests\TestCase;

class MallCheckoutControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('api_gw.base_url', 'http://foundation.local');
        config()->set('bet_agg.foundation.base_url', 'http://foundation.local');
        config()->set('bet_agg.foundation.me_endpoint', '/api/user/me');
        config()->set('bet_agg.saga.flow_id', 7);
        config()->set('bet_agg.saga.access_key', 'checkout-test-ak');
        config()->set('bet_agg.tcc.flow_id', 501);
    }

    /**
     * @param  array<string, mixed>  $contextOverrides
     */
    private function fakeUserMe(int $userId, array $contextOverrides = []): void
    {
        $ctx = array_merge([
            'global_tx_id' => 'gtx-fe',
            'idem_key' => 77_002,
            'branches' => [
                ['branch_code' => 'try_points', 'idem_key' => 'pts-default'],
            ],
        ], $contextOverrides);
        unset($ctx['prepay']);

        $prepayPartial = ['stub' => true, 'amount_minor' => 50, 'status' => 'stub_await_payment'];
        if (isset($contextOverrides['prepay']) && is_array($contextOverrides['prepay'])) {
            $prepayPartial = $contextOverrides['prepay'];
        }

        $sagaData = [
            'saga_instance_id' => '1',
            'idem_key' => 88_001,
            'flow_id' => 7,
            'status' => 40,
            'current_step_index' => 0,
            'retry_count' => 0,
            'last_error' => '',
            'context' => $ctx,
            'need_confirm' => [
                [
                    'response' => [
                        'branches' => [
                            [
                                'branch_code' => 'prepay',
                                'data' => [
                                    'errorCode' => 0,
                                    'data' => ['prepay' => $prepayPartial],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'step_runs' => [],
        ];

        Http::fake(array_merge(
            [
                'http://foundation.local/api/saga/instances' => Http::response([
                    'errorCode' => 0,
                    'data' => $sagaData,
                    'message' => '',
                ], 200),
            ],
            [
                'http://foundation.local/api/user/me' => Http::response([
                    'errorCode' => 0,
                    'data' => ['id' => $userId, 'username' => 'buyer'],
                    'message' => '',
                ], 200),
            ]
        ));
    }

    public function test_checkout_requires_auth(): void
    {
        $this->postJson('/api/bet/checkout', ['order_id' => 1])
            ->assertStatus(401)
            ->assertJsonPath('errorCode', 40101);
    }

    public function test_checkout_returns_prepay_from_saga_and_merges_tcc_fields(): void
    {
        $this->fakeUserMe(42, [
            'prepay' => ['stub' => true, 'amount_minor' => 50],
            'branches' => [
                ['branch_code' => 'try_points', 'idem_key' => 'ord:1:ab'],
            ],
        ]);

        $sid = SportSeeder::openSelection(2000);

        $create = $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/bet/orders', [
            'lines' => [['selection_id' => $sid, 'stake_points' => 100]],
        ]);
        $create->assertCreated();
        $orderId = (int) $create->json('data.id');

        $response = $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/bet/checkout', [
            'order_id' => $orderId,
            'points_minor' => 0,
        ]);

        $response->assertCreated()
            ->assertJsonPath('errorCode', 0)
            ->assertJsonPath('data.order.status', BetOrderStatus::Pending->value)
            ->assertJsonPath('data.prepay.amount_minor', 50)
            ->assertJsonPath('data.tid', 'gtx-fe');

        $order = BetOrder::query()->find($orderId);
        $this->assertNotNull($order);
        $this->assertSame('gtx-fe', $order->tid);
        $this->assertSame(77_002, (int) $order->tcc_idem_key);
    }

    public function test_checkout_rejects_when_tcc_not_configured(): void
    {
        config()->set('bet_agg.tcc.flow_id', 0);

        $this->fakeUserMe(1);

        $sid = SportSeeder::openSelection(2000);

        $orderId = (int) $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/bet/orders', [
            'lines' => [['selection_id' => $sid, 'stake_points' => 50]],
        ])->json('data.id');

        $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/bet/checkout', [
            'order_id' => $orderId,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errorCode', 40001);
    }

    public function test_checkout_rejects_when_order_not_draft(): void
    {
        $this->fakeUserMe(1);

        $sid = SportSeeder::openSelection(2000);

        $orderId = (int) $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/bet/orders', [
            'lines' => [['selection_id' => $sid, 'stake_points' => 50]],
        ])->json('data.id');

        BetOrder::query()->whereKey($orderId)->update(['checkout_phase' => CheckoutPhase::AwaitPayment->value]);

        $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/bet/checkout', [
            'order_id' => $orderId,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errorCode', 40001);
    }

    public function test_checkout_returns_422_when_order_id_invalid(): void
    {
        $this->fakeUserMe(1);

        $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/bet/checkout', [
            'order_id' => 0,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errorCode', 100);
    }

    public function test_checkout_returns_404_when_order_not_found(): void
    {
        $this->fakeUserMe(1);

        $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/bet/checkout', [
            'order_id' => 99999,
        ])
            ->assertStatus(404)
            ->assertJsonPath('errorCode', 40401);
    }

    public function test_checkout_maps_saga_envelope_error_to_422(): void
    {
        Http::fake([
            'http://foundation.local/api/user/me' => Http::response([
                'errorCode' => 0,
                'data' => ['id' => 99, 'username' => 'buyer'],
                'message' => '',
            ], 200),
            'http://foundation.local/api/saga/instances' => Http::response([
                'errorCode' => 100,
                'message' => 'insufficient points',
                'data' => null,
            ], 200),
        ]);

        $sid = SportSeeder::openSelection(2000);

        $orderId = (int) $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/bet/orders', [
            'lines' => [['selection_id' => $sid, 'stake_points' => 50]],
        ])->json('data.id');

        $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/bet/checkout', [
            'order_id' => $orderId,
            'points_minor' => 30,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errorCode', 40001);
    }

    public function test_checkout_requires_prepay_in_saga_response(): void
    {
        Http::fake([
            'http://foundation.local/api/user/me' => Http::response([
                'errorCode' => 0,
                'data' => ['id' => 5, 'username' => 'buyer'],
                'message' => '',
            ], 200),
            'http://foundation.local/api/saga/instances' => Http::response([
                'errorCode' => 0,
                'data' => [
                    'idem_key' => 1,
                    'saga_instance_id' => '1',
                    'flow_id' => 1,
                    'status' => 40,
                    'current_step_index' => 0,
                    'retry_count' => 0,
                    'last_error' => '',
                    'context' => [
                        'global_tx_id' => 'x',
                        'idem_key' => 2,
                    ],
                    'step_runs' => [],
                ],
                'message' => '',
            ], 200),
        ]);

        $sid = SportSeeder::openSelection(2000);

        $orderId = (int) $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/bet/orders', [
            'lines' => [['selection_id' => $sid, 'stake_points' => 10]],
        ])->json('data.id');

        $this->withHeader('X-User-Access-Token', 'tok')->postJson('/api/bet/checkout', [
            'order_id' => $orderId,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errorCode', 40001);
    }
}
