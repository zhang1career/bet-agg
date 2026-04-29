<?php

return [
    'prefix' => trim((string) env('BET_UPLOAD_PREFIX', ''), '/'),
    'oss_bucket' => trim((string) env('BET_OSS_BUCKET', ''), '/'),
];
