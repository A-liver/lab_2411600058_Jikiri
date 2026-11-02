<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InventoryTransactionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Api\InventoryApiController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\Api\ProcurementWidgetController;
use App\Http\Controllers\ExportController;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware(['auth'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');
    Route::get('purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receiveForm'])->name('purchase-orders.receive');
    Route::post('purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive.store');    
    Route::get('purchase-orders/{purchaseOrder}/pdf', [ExportController::class, 'purchaseOrder'])->name('purchase-orders.pdf');

    Route::get('/exports/{dataset}/{format}', [ExportController::class, 'download'])
        ->where('dataset', 'products|purchase-orders|transactions|supplier-performance')
        ->where('format', 'csv|pdf')
        ->name('exports.download');
    Route::resource('transactions', InventoryTransactionController::class)
    ->only(['index', 'create', 'store', 'show', 'destroy']);

    Route::get('/products-export', [ProductController::class, 'export'])->name('products.export');
    Route::resource('products', ProductController::class);
    
    Route::post('/dashboard/simulate', [DashboardController::class, 'simulate'])->name('dashboard.simulate');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.readAll');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
    
    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/dashboard/stats', [InventoryApiController::class, 'stats'])->name('dashboard.stats');
        Route::get('/products/low-stock', [InventoryApiController::class, 'lowStock'])->name('products.lowStock');
        Route::get('/products/{product}', [InventoryApiController::class, 'product'])->name('products.show');
        Route::get('/transactions/recent', [InventoryApiController::class, 'recent'])->name('transactions.recent');
    });
    
    Route::middleware('can:viewAny,' . \App\Models\PurchaseOrder::class)
    ->prefix('api/dashboard')
    ->name('api.dashboard.')
    ->group(function () {
        Route::get('/pending-orders', [ProcurementWidgetController::class, 'pendingOrders'])->name('pending-orders');
        Route::get('/recent-orders', [ProcurementWidgetController::class, 'recentOrders'])->name('recent-orders');
        Route::get('/supplier-performance', [ProcurementWidgetController::class, 'supplierPerformance'])->name('supplier-performance');
        Route::get('/reorder-alerts', [ProcurementWidgetController::class, 'reorderAlerts'])->name('reorder-alerts');
    });
    Route::middleware('can:manage-stock')->group(function () {
        Route::post('/suppliers/{id}/restore', [SupplierController::class, 'restore'])->name('suppliers.restore');
        Route::post('/suppliers/{supplier}/products', [SupplierController::class, 'attachProduct'])->name('suppliers.products.attach');
        Route::delete('/suppliers/{supplier}/products/{product}', [SupplierController::class, 'detachProduct'])->name('suppliers.products.detach');
        Route::resource('suppliers', SupplierController::class);
    });
    Route::post('purchase-orders/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit'])->name('purchase-orders.submit');
    Route::post('purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->name('purchase-orders.approve');
    Route::post('purchase-orders/{purchaseOrder}/reject', [PurchaseOrderController::class, 'reject'])->name('purchase-orders.reject');
    Route::post('purchase-orders/{purchaseOrder}/send', [PurchaseOrderController::class, 'send'])->name('purchase-orders.send');
    Route::post('purchase-orders/{purchaseOrder}/reopen', [PurchaseOrderController::class, 'reopen'])->name('purchase-orders.reopen');
    Route::post('purchase-orders/reorder-check', [PurchaseOrderController::class, 'reorderCheck'])->name('purchase-orders.reorder-check');
    Route::resource('purchase-orders', PurchaseOrderController::class);

    Route::middleware('can:manage-stock')->group(function () {
        Route::get('/adjustments/create', [StockAdjustmentController::class, 'create'])->name('adjustments.create');
        Route::post('/adjustments', [StockAdjustmentController::class, 'store'])->name('adjustments.store');
    });
    Route::middleware('can:manage-stock')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/movement', [ReportController::class, 'movement'])->name('movement');
        Route::get('/low-stock', [ReportController::class, 'lowStock'])->name('lowStock');
        Route::get('/valuation', [ReportController::class, 'valuation'])->name('valuation');
        Route::get('/suppliers', [ReportController::class, 'suppliers'])->name('suppliers');
    });
});

require __DIR__ . '/auth.php';