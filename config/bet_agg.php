<?php

declare(strict_types=1);

use Paganini\Constants\ResponseConstant;

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
        'unauthorized_code' => ResponseConstant::RET_UNAUTHORIZED,
    ],

    'orders' => [
        'pending_payment_timeout_ms' => (int) env('BET_PENDING_PAYMENT_TIMEOUT_MS', 1_800_000),
    ],
];
