<?php

return [
    'prefix' => trim((string) env('BET_UPLOAD_PREFIX', ''), '/'),

    /*
    | Bucket for foundation OSS (PUT /api/oss/{bucket}/{key...}).
    */
    'oss_bucket' => trim((string) env('BET_OSS_BUCKET', ''), '/'),
];
