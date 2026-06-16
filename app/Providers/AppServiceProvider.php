<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.tailwind');
        Paginator::defaultSimpleView('vendor.pagination.simple-tailwind');

        // Log Viewer access — chỉ user có permission 'view-log-viewer'
        Gate::define('viewLogViewer', function ($user) {
            return $user->hasPermissionTo('view-log-viewer');
        });
    }
}
