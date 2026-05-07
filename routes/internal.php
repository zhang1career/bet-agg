<?php

declare(strict_types=1);

use App\Http\Controllers\XxlJobController;
use App\Http\Middleware\XxljobAuthentication;
use Illuminate\Support\Facades\Route;

/*
 * Internal-only routes — mounted under /internal by bootstrap/app.php (NOT
 * exposed in /api/openapi.json or other public docs). XXL-Job uses an HMAC-style
 * static token, NOT user JWTs, so it lives off the public surface.
 */
Route::prefix('xxl-job')->middleware([XxljobAuthentication::class])->group(function () {
    Route::get('beat', [XxlJobController::class, 'beat']);
    Route::post('run', [XxlJobController::class, 'run']);
    Route::post('kill', [XxlJobController::class, 'kill']);
});
