<?php

use App\Http\Controllers\api\BetDictController;
use App\Http\Controllers\api\BetGameController;
use App\Http\Controllers\api\BetMarketController;
use App\Http\Controllers\api\BetOrderController;
use App\Http\Controllers\api\BetPlaceController;
use App\Http\Controllers\api\BetPointsController;
use App\Http\Controllers\api\OpenApiController;
use Illuminate\Support\Facades\Route;

/*
 * Public agent-facing API surface. Everything mounted here is documented in
 * /api/openapi.json; non-public infrastructure (XXL-Job HTTP callbacks) lives
 * under /internal/* (see routes/internal.php).
 */

Route::get('openapi.json', OpenApiController::class);

Route::prefix('bet')->group(function () {
    Route::get('dict', BetDictController::class);
    Route::get('games', [BetGameController::class, 'index']);
    Route::get('games/{game_id}', [BetGameController::class, 'show'])->whereNumber('game_id');
    Route::get('markets', [BetMarketController::class, 'index']);
    Route::get('markets/{market_id}', [BetMarketController::class, 'show'])->whereNumber('market_id');
    Route::post('place', [BetPlaceController::class, 'store']);
    Route::get('orders', [BetOrderController::class, 'index']);
    Route::get('orders/{id}', [BetOrderController::class, 'show'])->whereNumber('id');
    Route::get('points', [BetPointsController::class, 'show']);
});
