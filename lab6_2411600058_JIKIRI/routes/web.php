<?php

use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryTransactionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StockAdjustmentController;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/products-export', [ProductController::class, 'export'])->name('products.export');
    Route::post('/products/{product}/adjust-stock', [ProductController::class, 'adjustStock'])->name('products.adjust-stock');
    Route::resource('products', ProductController::class);

    Route::resource('transactions', InventoryTransactionController::class)
        ->only(['index', 'create', 'store', 'show']);

    Route::resource('categories', CategoryController::class)->except('show');
    Route::resource('brands', BrandController::class)->except('show');

    // admin only
    Route::middleware('can:manage-inventory')->group(function () {
        Route::get('/adjustments/create', [StockAdjustmentController::class, 'create'])->name('adjustments.create');
        Route::post('/adjustments', [StockAdjustmentController::class, 'store'])->name('adjustments.store');
        Route::resource('users', UserController::class)->except('show');
        Route::delete('/transactions/{transaction}', [InventoryTransactionController::class, 'destroy'])
            ->name('transactions.destroy');
    });

    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';