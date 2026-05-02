<?php

declare(strict_types=1);

/**
 * Admin OSS uploads: HTTP PUT via gateway API_GATEWAY_BASE_URL + /api/oss/{bucket}/{key}.
 *
 * BET_UPLOAD_PREFIX — object-key prefix (leading/trailing slashes trimmed by config layer).
 * BET_OSS_BUCKET    — bucket segment in that path.
 */

return [
    'prefix' => trim((string) env('BET_UPLOAD_PREFIX'), '/'),
    'oss_bucket' => trim((string) env('BET_OSS_BUCKET', ''), '/'),
];
