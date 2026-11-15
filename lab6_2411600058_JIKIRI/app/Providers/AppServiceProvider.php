<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Gate::define('manage-inventory', fn (User $user) => $user->isAdmin());

        // feeds the bell on every page that uses the main layout
        View::composer('layouts.app', function ($view) {
            $user = auth()->user();

            $view->with([
                'alertCount' => $user ? $user->unreadNotifications()->count() : 0,
                'recentAlerts' => $user ? $user->notifications()->limit(6)->get() : collect(),
            ]);
        });
    }
}