<?php

namespace App\Providers;

use App\Services\ProcurementWidgets;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    /**
     * View Composers: each widget view receives its data automatically
     * whenever it is rendered, so the dashboard controller stays untouched.
     */
    public function boot(): void
    {
        View::composer('widgets.pending-orders', function ($view) {
            $view->with('pending', app(ProcurementWidgets::class)->pendingOrders());
        });

        View::composer('widgets.recent-orders', function ($view) {
            $view->with('recentOrders', app(ProcurementWidgets::class)->recentOrders());
        });

        View::composer('widgets.supplier-performance', function ($view) {
            $view->with('performance', app(ProcurementWidgets::class)->supplierPerformance());
        });

        View::composer('widgets.reorder-alerts', function ($view) {
            $view->with('reorder', app(ProcurementWidgets::class)->reorderAlerts());
        });
    }
}