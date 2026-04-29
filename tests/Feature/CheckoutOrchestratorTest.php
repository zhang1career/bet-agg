<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\mall\CheckoutOrchestrator;
use App\Services\mall\OrderCommandService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Support\SportSeeder;
use Tests\TestCase;

/**
 * @group checkout-orchestrator
 */
final class CheckoutOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_calls_saga_start_and_merges_coordinator_fields(): void
    {
        config()->set('api_gw.base_url', 'http://foundation.local');
        config()->set('bet_agg.saga.flow_id', 7);
        config()->set('bet_agg.saga.access_key', 'ak');
        config()->set('bet_agg.tcc.flow_id', 501);

        $sid = SportSeeder::openSelection(2000);
        $order = app(OrderCommandService::class)->createDraftPendingOrder(33, [['selection_id' => $sid, 'stake_points' => 1000]]);

        Http::fake([
            'http://foundation.local/api/saga/instances' => Http::response([
                'errorCode' => 0,
                'data' => [
                    'saga_instance_id' => '1',
                    'idem_key' => 88_001,
                    'flow_id' => 7,
                    'status' => 40,
                    'current_step_index' => 0,
                    'retry_count' => 0,
                    'last_error' => '',
                    'context' => [
                        'global_tx_id' => 'gtx-m',
                        'idem_key' => 55_055,
                        'branches' => [
                            [
                                'branch_code' => 'try_points',
                                'idem_key' => 'ord:33:x',
                            ],
                        ],
                    ],
                    'need_confirm' => [
                        [
                            'response' => [
                                'branches' => [
                                    [
                                        'branch_code' => 'prepay',
                                        'data' => [
                                            'errorCode' => 0,
                                            'data' => [
                                                'prepay' => ['stub' => true, 'amount_minor' => 700],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'step_runs' => [],
                ],
                'message' => '',
            ], 200),
        ]);

        $result = app(CheckoutOrchestrator::class)->checkoutExistingOrder(33, $order, 300, '0');

        $this->assertSame(700, (int) $result['prepay']['amount_minor']);
        $this->assertSame('1', (string) $result['prepay']['schema_version']);
        $this->assertSame('gtx-m', $result['tid']);
        $this->assertSame('ord:33:x', $result['points_tcc_idem_key']);

        $fresh = $result['order']->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame('gtx-m', $fresh->tid);
        $this->assertSame(55_055, (int) $fresh->tcc_idem_key);
    }

    public function test_checkout_throws_when_saga_omits_prepay(): void
    {
        config()->set('api_gw.base_url', 'http://foundation.local');
        config()->set('bet_agg.saga.flow_id', 7);
        config()->set('bet_agg.saga.access_key', 'ak');
        config()->set('bet_agg.tcc.flow_id', 1);

        $sid = SportSeeder::openSelection(2000);
        $order = app(OrderCommandService::class)->createDraftPendingOrder(1, [['selection_id' => $sid, 'stake_points' => 10]]);

        Http::fake([
            'http://foundation.local/api/saga/instances' => Http::response([
                'errorCode' => 0,
                'data' => [
                    'saga_instance_id' => '1',
                    'idem_key' => 1,
                    'flow_id' => 1,
                    'status' => 40,
                    'context' => [
                        'global_tx_id' => 'x',
                        'idem_key' => 1,
                    ],
                    'step_runs' => [],
                ],
                'message' => '',
            ], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('need_confirm');

        app(CheckoutOrchestrator::class)->checkoutExistingOrder(1, $order, 0);
    }
}
