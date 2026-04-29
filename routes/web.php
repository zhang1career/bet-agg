<?php

use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminPointsController;
use App\Http\Controllers\Admin\AdminSettlementController;
use App\Http\Controllers\Admin\AdminSportBookController;
use App\Http\Controllers\Admin\AdminUploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', static function () {
    return redirect()->route('admin.sport-book.index');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::post('uploads', [AdminUploadController::class, 'store'])->name('uploads.store');

    Route::get('sport-book', [AdminSportBookController::class, 'index'])->name('sport-book.index');
    Route::get('sport-book/new', [AdminSportBookController::class, 'create'])->name('sport-book.create');
    Route::post('sport-book', [AdminSportBookController::class, 'store'])->name('sport-book.store');

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
