<?php

use App\Http\Controllers\api\BetAdminPointsController;
use App\Http\Controllers\api\BetCheckoutController;
use App\Http\Controllers\api\BetDictController;
use App\Http\Controllers\api\BetGameController;
use App\Http\Controllers\api\BetMarketController;
use App\Http\Controllers\api\BetOrderController;
use App\Http\Controllers\api\BetPointsController;
use App\Http\Controllers\api\PaymentCallbackController;
use App\Http\Controllers\api\SportSelectionController;
use App\Http\Controllers\XxlJobController;
use App\Http\Middleware\XxljobAuthentication;
use Illuminate\Support\Facades\Route;

Route::prefix('xxl-job')->middleware([XxljobAuthentication::class])->group(function () {
    Route::get('beat', [XxlJobController::class, 'beat']);
    Route::post('run', [XxlJobController::class, 'run']);
    Route::post('kill', [XxlJobController::class, 'kill']);
});

Route::prefix('')->middleware([])->group(function () {
    Route::prefix('bet')->group(function () {
        Route::get('dict', BetDictController::class);
        Route::get('games', [BetGameController::class, 'index']);
        Route::get('games/{game_id}', [BetGameController::class, 'show'])->whereNumber('game_id');
        Route::get('markets', [BetMarketController::class, 'index']);
        Route::get('markets/{market_id}', [BetMarketController::class, 'show'])->whereNumber('market_id');
        Route::get('selections', [SportSelectionController::class, 'index']);
        Route::get('selections/{id}', [SportSelectionController::class, 'show'])->whereNumber('id');
        Route::post('orders', [BetOrderController::class, 'store']);
        Route::patch('orders/{id}', [BetOrderController::class, 'update'])->whereNumber('id');
        Route::get('orders', [BetOrderController::class, 'index']);
        Route::get('orders/{id}', [BetOrderController::class, 'show'])->whereNumber('id');
        Route::get('points', [BetPointsController::class, 'show']);
        Route::post('checkout', [BetCheckoutController::class, 'store']);
        Route::post('payment/callback', PaymentCallbackController::class);

        Route::prefix('admin')->middleware(['admin.api'])->group(function () {
            Route::post('points', [BetAdminPointsController::class, 'storeAccount']);
            Route::post('points/{balance_id}', [BetAdminPointsController::class, 'adjust'])->whereNumber('balance_id');
        });
    });
});
