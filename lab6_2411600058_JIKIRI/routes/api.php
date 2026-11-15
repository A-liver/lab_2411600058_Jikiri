<?php

use App\Http\Controllers\Api\DashboardApiController;
use Illuminate\Support\Facades\Route;

// Same-browser AJAX: these use the logged-in session ('web'), not API tokens.
Route::middleware(['web', 'auth', 'throttle:120,1'])->group(function () {
    Route::get('/dashboard/stats', [DashboardApiController::class, 'stats'])->name('api.dashboard.stats');
    Route::get('/products/low-stock', [DashboardApiController::class, 'lowStock'])->name('api.products.low-stock');
    Route::get('/products/{product}/quantity', [DashboardApiController::class, 'quantity'])->name('api.products.quantity');
    Route::get('/transactions/recent', [DashboardApiController::class, 'recentTransactions'])->name('api.transactions.recent');
});