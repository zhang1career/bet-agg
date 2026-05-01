<?php

use App\Http\Controllers\Admin\AdminGameController;
use App\Http\Controllers\Admin\AdminMarketController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminPointsController;
use App\Http\Controllers\Admin\AdminSettlementController;
use App\Http\Controllers\Admin\AdminUploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', static function () {
    return redirect()->route('admin.games.index');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::post('uploads', [AdminUploadController::class, 'store'])->name('uploads.store');

    Route::resource('games', AdminGameController::class);
    Route::post('markets/{market}/selections', [AdminMarketController::class, 'storeSelection'])
        ->name('markets.selections.store');
    Route::put('markets/{market}/selections/{selection}', [AdminMarketController::class, 'updateSelection'])
        ->name('markets.selections.update');
    Route::delete('markets/{market}/selections/{selection}', [AdminMarketController::class, 'destroySelection'])
        ->name('markets.selections.destroy');
    Route::resource('markets', AdminMarketController::class);

    Route::get('settlement', [AdminSettlementController::class, 'create'])->name('settlement.create');
    Route::post('settlement', [AdminSettlementController::class, 'store'])->name('settlement.store');

    Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{id}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::patch('orders/{id}', [AdminOrderController::class, 'update'])->name('orders.update');

    Route::get('points', [AdminPointsController::class, 'index'])->name('points.index');
    Route::get('points/balances/{id}', [AdminPointsController::class, 'showBalance'])->name('points.balances.show');
    Route::delete('points/balances/{id}', [AdminPointsController::class, 'destroyBalance'])->name('points.balances.destroy');
    Route::get('points/flows/{id}', [AdminPointsController::class, 'showFlow'])->name('points.flows.show');
    Route::post('points/accounts', [AdminPointsController::class, 'storeAccount'])->name('points.accounts.store');
    Route::post('points/adjust', [AdminPointsController::class, 'adjust'])->name('points.adjust');
});
