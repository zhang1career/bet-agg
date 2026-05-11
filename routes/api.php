<?php

use App\Http\Controllers\api\OpenApiController;
use App\Http\Controllers\api\PredictionDictController;
use App\Http\Controllers\api\PredictionGameController;
use App\Http\Controllers\api\PredictionLeaderboardController;
use App\Http\Controllers\api\PredictionMarketController;
use App\Http\Controllers\api\PredictionOrderController;
use App\Http\Controllers\api\PredictionReputationController;
use App\Http\Controllers\api\PredictionSubmitController;
use App\Http\Controllers\api\SnowflakeIdController;
use Illuminate\Support\Facades\Route;

/*
 * Public agent-facing API surface. Everything mounted here is documented in
 * /api/openapi.json; non-public infrastructure (XXL-Job HTTP callbacks) lives
 * under /internal/* (see routes/internal.php).
 */

Route::get('openapi.json', OpenApiController::class);

Route::prefix('bet')->group(function () {
    Route::get('dict', PredictionDictController::class);
    Route::get('games', [PredictionGameController::class, 'index']);
    Route::get('games/{game_id}', [PredictionGameController::class, 'show'])->whereNumber('game_id');
    Route::get('markets', [PredictionMarketController::class, 'index']);
    Route::get('markets/{market_id}', [PredictionMarketController::class, 'show'])->whereNumber('market_id');
    Route::get('leaderboard', [PredictionLeaderboardController::class, 'index']);
    Route::post('submit', [PredictionSubmitController::class, 'store']);
    Route::get('orders', [PredictionOrderController::class, 'index']);
    Route::get('orders/{id}', [PredictionOrderController::class, 'show'])->whereNumber('id');
    Route::get('reputation', [PredictionReputationController::class, 'show']);
    Route::post('snowflake', SnowflakeIdController::class);
});
