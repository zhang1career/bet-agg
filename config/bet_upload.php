<?php

declare(strict_types=1);

/**
 * Admin OSS: HTTP PUT via API_GATEWAY_BASE_URL + /api/oss/{bucket}/{key}.
 *
 * BET_OSS_BUCKET — bucket name.
 * BET_OSS_GATEWAY_BEARER_TOKEN — optional {@code Authorization: Bearer} for the gateway PUT.
 */

return [
    'oss_bucket' => trim((string) env('BET_OSS_BUCKET', ''), '/'),
    'gateway_bearer_token' => (string) env('BET_OSS_GATEWAY_BEARER_TOKEN', ''),
];
