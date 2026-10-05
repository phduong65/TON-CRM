<?php

namespace App\Providers;

use App\Models\AttendanceLog;
use App\Observers\AttendanceLogObserver;
use Illuminate\Pagination\Paginator;
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

        // Reset trạng thái "Đã xác nhận công" khi chấm công của nhân viên bị thay đổi —
        // xem AttendanceLogObserver.
        AttendanceLog::observe(AttendanceLogObserver::class);

        // Theme / Event Engine view composer
        \Illuminate\Support\Facades\View::composer(
            ['layouts.auth', 'layouts.admin', 'auth.login', 'dashboard.index', 'themes.partials.preview-modal'],
            \App\Http\View\Composers\ThemeViewComposer::class
        );
    }
}
