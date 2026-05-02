<?php

declare(strict_types=1);

/**
 * Admin OSS uploads: HTTP PUT via gateway API_GATEWAY_BASE_URL + /api/oss/{bucket}/{key}.
 *
 * BET_UPLOAD_PREFIX — object-key prefix (leading/trailing slashes trimmed by config layer).
 * BET_OSS_BUCKET    — bucket segment in that path.
 * BET_OSS_GATEWAY_BEARER_TOKEN — 若设置，MallOssUploadService 会在 PUT 上携带 Authorization: Bearer …。
 *   可选；未配置或留空则与改前一致。内网访问 service-foundation（OSS 等）时若网关暂不校验可省略；后续服务发现/治理层统一 Bearer 时可沿用此项。
 */

return [
    'prefix' => trim((string) env('BET_UPLOAD_PREFIX'), '/'),
    'oss_bucket' => trim((string) env('BET_OSS_BUCKET', ''), '/'),
    'gateway_bearer_token' => (string) env('BET_OSS_GATEWAY_BEARER_TOKEN', ''),
];
