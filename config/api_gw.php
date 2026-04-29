<?php

return [
    'base_url' => env('API_GATEWAY_BASE_URL', ''),
    'timeout_seconds' => (int) env('API_GATEWAY_TIMEOUT_SECONDS', 3),
];
