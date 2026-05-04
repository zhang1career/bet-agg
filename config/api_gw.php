<?php

return [
    'base_url' => env('API_GATEWAY_BASE_URL', ''),
    'timeout_seconds' => (int) env('API_GATEWAY_TIMEOUT_SECONDS', 3),

    'cms' => [
        'cms_url' => env('API_GATEWAY_CMS_URL', '/api/cms/'),
        /** Path segment after cms_url for game list/detail, e.g. "game" → {@code GET /api/cms/game}. */
        'game_route' => env('BET_CMS_GAME_ROUTE', 'game'),
    ],
];
