<?php

declare(strict_types=1);

use Paganini\Constants\ResponseConstant;

return [
    'api' => [
        'log_http_errors' => (bool) env('BET_AGG_API_LOG_HTTP_ERRORS', true),
        'normalize_5xx_json_body' => (bool) env('BET_AGG_API_NORMALIZE_5XX_JSON', false),
        'normalize_5xx_message' => env('BET_AGG_API_NORMALIZE_5XX_MESSAGE', '服务器内部错误'),
    ],

    /**
     * Decimal snowflake mint via service_foundation (server-side key; not sent by API clients).
     */
    'snowflake' => [
        'access_key' => env('SF_SNOWFLAKE_ACCESS_KEY', ''),
        'mint_endpoint' => env('BET_FOUNDATION_SNOWFLAKE_MINT_PATH', '/api/snowflake/id'),
    ],

    'foundation' => [
        'base_url' => env('API_GATEWAY_BASE_URL', ''),
        'service_discovery' => [
            'redis_connection' => env('SD_CACHE_CONN', 'default'),
            'redis_key_prefix' => env('SD_CACHE_KEY_PREFIX', ''),
        ],
        'me_endpoint' => '/api/user/me',
        'timeout_seconds' => 3,
        /** Cross-request TTL cache for {@code GET /api/user/me} responses, keyed by sha256(token). 401/403 invalidates immediately. */
        'cache_ttl_seconds' => (int) env('BET_FOUNDATION_USER_CACHE_TTL_SECONDS', 60),
        'unauthorized_code' => ResponseConstant::RET_UNAUTHORIZED,
    ],

    'points' => [
        /** Score delta on correct prediction (settlement win). */
        'delta_win' => (int) env('BET_POINTS_DELTA_WIN', 100),
        /** Magnitude subtracted on incorrect prediction (positive config; applied as negative delta). */
        'delta_lose' => (int) env('BET_POINTS_DELTA_LOSE', 50),
    ],
];
