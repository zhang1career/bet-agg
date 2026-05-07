<?php

use App\Http\Controllers\admin\AdminGameController;
use App\Http\Controllers\admin\AdminGameGroupController;
use App\Http\Controllers\admin\AdminGameSubjectController;
use App\Http\Controllers\admin\AdminMarketController;
use App\Http\Controllers\admin\AdminOrderController;
use App\Http\Controllers\admin\AdminPointsController;
use App\Http\Controllers\admin\AdminSettlementController;
use App\Http\Controllers\admin\AdminUploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', static function () {
    return redirect()->route('admin.games.index');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::post('uploads', [AdminUploadController::class, 'store'])->name('uploads.store');

    Route::resource('games', AdminGameController::class);

    Route::resource('game-groups', AdminGameGroupController::class);

    Route::resource('game-subjects', AdminGameSubjectController::class);

    Route::resource('markets', AdminMarketController::class);

    Route::get('settlement', [AdminSettlementController::class, 'create'])->name('settlement.create');
    Route::post('settlement', [AdminSettlementController::class, 'store'])->name('settlement.store');

    Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{id}', [AdminOrderController::class, 'show'])->name('orders.show');

    Route::get('points', [AdminPointsController::class, 'index'])->name('points.index');
    Route::get('users', [AdminPointsController::class, 'indexUsers'])->name('users.index');
    Route::get('users/{user_id}', [AdminPointsController::class, 'showUser'])
        ->whereNumber('user_id')
        ->name('users.show');
    Route::get('points/balances/{id}', [AdminPointsController::class, 'showBalance'])->name('points.balances.show');
    Route::delete('points/balances/{id}', [AdminPointsController::class, 'destroyBalance'])->name('points.balances.destroy');
    Route::get('points/flows/{id}', [AdminPointsController::class, 'showFlow'])->name('points.flows.show');
    Route::post('points/accounts', [AdminPointsController::class, 'storeAccount'])->name('points.accounts.store');
    Route::post('points/adjust', [AdminPointsController::class, 'adjust'])->name('points.adjust');
});
