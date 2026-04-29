<?php

use App\Services\mall\aggregation\LocalSportOddsProvider;

return [
    'api' => [
        'log_http_errors' => (bool) env('BET_AGG_API_LOG_HTTP_ERRORS', true),
        'normalize_5xx_json_body' => (bool) env('BET_AGG_API_NORMALIZE_5XX_JSON', false),
        'normalize_5xx_message' => env('BET_AGG_API_NORMALIZE_5XX_MESSAGE', '服务器内部错误'),
    ],

    'foundation' => [
        'base_url' => env('API_GATEWAY_BASE_URL', ''),
        'service_discovery' => [
            'redis_connection' => env('SD_CACHE_CONN', 'default'),
            'redis_key_prefix' => env('SD_CACHE_KEY_PREFIX', ''),
        ],
        'me_endpoint' => '/api/user/me',
        'timeout_seconds' => 3,
        'unauthorized_code' => 40101,
    ],

    /*
    | ProviderContract: resolves selection odds / open state when context contains bet_selection_ids.
    */
    'business_services' => [
        ['class' => LocalSportOddsProvider::class, 'enabled' => true],
    ],

    'execution' => [
        'mode' => env('SM_EXECUTION_MODE', 'serial'),
    ],

    'degrade' => [
        'strategy' => env('SM_DEGRADE_STRATEGY', 'mask_null'),
        'mask_error_message' => env('BET_AGG_DEGRADE_MASK_ERROR_MESSAGE', 'Service temporarily unavailable.'),
        'partial_failure_code' => (int) env('SM_PARTIAL_FAILURE_CODE', 20601),
        'partial_failure_message' => env('SM_PARTIAL_FAILURE_MESSAGE', 'Partially failed, degraded by aggregator.'),
    ],

    'payment' => [
        'callback_token' => env('BET_PAYMENT_CALLBACK_TOKEN', ''),
    ],

    'orders' => [
        'pending_payment_timeout_ms' => (int) env('BET_PENDING_PAYMENT_TIMEOUT_MS', 1_800_000),
    ],

    'admin' => [
        'api_token' => env('BET_ADMIN_API_TOKEN', ''),
    ],
];
