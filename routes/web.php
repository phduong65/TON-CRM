<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ThemeController;
use App\Http\Controllers\EmployeesController;
use App\Http\Controllers\PenaltiesController;
use App\Http\Controllers\RankingsController;
use App\Http\Controllers\RedzoneController;
use App\Http\Controllers\RegulationsController;
use App\Http\Controllers\ViolationsController;
use App\Http\Controllers\BranchesController;
use App\Http\Controllers\TeamsController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\RolesController;

/*
|--------------------------------------------------------------------------
| Web Routes — P-CRM Staff Discipline Management System
|--------------------------------------------------------------------------
| Giao diện tiếng Việt — Blade thuần + Tailwind CSS
*/

// Root: redirect to dashboard if authenticated, else to login
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// Authentication (Laravel built-in)
Route::middleware('auth')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    // Theme toggle
    Route::post('/theme/toggle', [ThemeController::class, 'toggle'])
        ->name('theme.toggle');

    // Employees — Full CRUD (create/edit via modal)
    Route::prefix('employees')->name('employees.')->group(function () {
        Route::get('/', [EmployeesController::class, 'index'])->name('index');
        Route::post('/', [EmployeesController::class, 'store'])->name('store');
        Route::get('/{employee}', [EmployeesController::class, 'show'])->name('show');
        Route::put('/{employee}', [EmployeesController::class, 'update'])->name('update');
        Route::delete('/{employee}', [EmployeesController::class, 'destroy'])->name('destroy');
        Route::get('/{employee}/penalties', [EmployeesController::class, 'penalties'])->name('penalties');
    });

    // Teams — Full CRUD (create/edit via modal)
    Route::resource('teams', TeamsController::class)->except(['create', 'edit', 'show']);

    // Branches — Full CRUD (create/edit via modal)
    Route::resource('branches', BranchesController::class)->except(['create', 'edit', 'show']);

    // Violations
    Route::resource('violations', ViolationsController::class)->only(['index', 'show']);

    // Penalties
    Route::prefix('penalties')->name('penalties.')->group(function () {
        Route::get('/', [PenaltiesController::class, 'index'])->name('index');
        Route::get('/{penalty}', [PenaltiesController::class, 'show'])->name('show');
        Route::post('/{penalty}/approve', [PenaltiesController::class, 'approve'])->name('approve');
        Route::post('/{penalty}/reject', [PenaltiesController::class, 'reject'])->name('reject');
    });

    // Regulations (create/edit via modal)
    Route::resource('regulations', RegulationsController::class)->except(['create', 'edit']);

    // Rankings
    Route::get('/rankings', [RankingsController::class, 'index'])
        ->name('rankings.index');

    // Redzone
    Route::get('/redzone', [RedzoneController::class, 'index'])
        ->name('redzone.index');

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])
        ->name('settings.index');
    Route::post('/settings', [SettingsController::class, 'update'])
        ->name('settings.update');

    // Activity Log
    Route::get('/activity-log', [ActivityLogController::class, 'index'])
        ->name('activity.log')
        ->middleware('can:view-activity-log');

    // User Management (admin: manage-users) — create/edit via modal
    Route::middleware('can:manage-users')->group(function () {
        Route::resource('users', UsersController::class)->except(['create', 'edit', 'show']);
    });

    // Role & Permission Management (admin: manage-roles) — create/edit via modal
    Route::middleware('can:manage-roles')->group(function () {
        Route::resource('roles', RolesController::class)->except(['create', 'edit', 'show']);
    });
});

// Redirect /home to /dashboard
Route::redirect('/home', '/dashboard');

require __DIR__.'/auth.php';
