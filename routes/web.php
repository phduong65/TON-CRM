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
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\AttendanceImportController;
use App\Http\Controllers\AttendanceImportRulesController;
use App\Http\Controllers\RewardCategoriesController;
use App\Http\Controllers\RewardTypesController;
use App\Http\Controllers\RewardsController;

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

    // Profile — xem & chỉnh sửa hồ sơ cá nhân
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Employees — Full CRUD (create/edit via modal)
    Route::prefix('employees')->name('employees.')->group(function () {
        Route::get('/', [EmployeesController::class, 'index'])->name('index')->middleware('can:view-employees');
        Route::post('/', [EmployeesController::class, 'store'])->name('store')->middleware('can:create-employees');
        Route::get('/{employee}', [EmployeesController::class, 'show'])->name('show')->middleware('can:view-employees');
        Route::put('/{employee}', [EmployeesController::class, 'update'])->name('update')->middleware('can:edit-employees');
        Route::delete('/{employee}', [EmployeesController::class, 'destroy'])->name('destroy')->middleware('can:delete-employees');
        Route::get('/{employee}/penalties', [EmployeesController::class, 'penalties'])->name('penalties')->middleware('can:view-employees');
    });

    // Teams — Full CRUD (create/edit via modal)
    Route::middleware('can:view-teams')->group(function () {
        Route::get('/teams', [TeamsController::class, 'index'])->name('teams.index');
    });
    Route::post('/teams', [TeamsController::class, 'store'])->name('teams.store')->middleware('can:create-teams');
    Route::put('/teams/{team}', [TeamsController::class, 'update'])->name('teams.update')->middleware('can:edit-teams');
    Route::delete('/teams/{team}', [TeamsController::class, 'destroy'])->name('teams.destroy')->middleware('can:delete-teams');

    // Branches — Full CRUD (create/edit via modal)
    Route::get('/branches', [BranchesController::class, 'index'])->name('branches.index')->middleware('can:view-branches');
    Route::post('/branches', [BranchesController::class, 'store'])->name('branches.store')->middleware('can:create-branches');
    Route::put('/branches/{branch}', [BranchesController::class, 'update'])->name('branches.update')->middleware('can:edit-branches');
    Route::delete('/branches/{branch}', [BranchesController::class, 'destroy'])->name('branches.destroy')->middleware('can:delete-branches');

    // Violations — Full CRUD (create/edit via modal)
    Route::get('/violations', [ViolationsController::class, 'index'])->name('violations.index')->middleware('can:view-violations');
    Route::post('/violations', [ViolationsController::class, 'store'])->name('violations.store')->middleware('can:create-violations');
    Route::put('/violations/{violation}', [ViolationsController::class, 'update'])->name('violations.update')->middleware('can:edit-violations');
    Route::delete('/violations/{violation}', [ViolationsController::class, 'destroy'])->name('violations.destroy')->middleware('can:delete-violations');

    // Penalties
    Route::prefix('penalties')->name('penalties.')->group(function () {
        Route::get('/', [PenaltiesController::class, 'index'])->name('index')->middleware('can:view-penalties');
        Route::post('/', [PenaltiesController::class, 'store'])->name('store')->middleware('can:create-penalties');
        Route::get('/{penalty}', [PenaltiesController::class, 'show'])->name('show')->middleware('can:view-penalties');
        Route::get('/{penalty}/detail-json', [PenaltiesController::class, 'detailJson'])->name('detail-json')->middleware('can:view-penalties');
        Route::put('/{penalty}', [PenaltiesController::class, 'update'])->name('update')->middleware('can:create-penalties');
        Route::delete('/{penalty}', [PenaltiesController::class, 'destroy'])->name('destroy')->middleware('can:approve-penalties');
        Route::post('/{penalty}/approve', [PenaltiesController::class, 'approve'])->name('approve')->middleware('can:approve-penalties');
        Route::post('/{penalty}/reject', [PenaltiesController::class, 'reject'])->name('reject')->middleware('can:approve-penalties');
    });

    // Attendance Import — đi trễ / về sớm
    Route::prefix('attendance-import')->name('attendance-import.')->middleware('can:import-attendance')->group(function () {
        Route::get('/', [AttendanceImportController::class, 'index'])->name('index');
        Route::post('/preview', [AttendanceImportController::class, 'preview'])->name('preview');
        Route::post('/confirm', [AttendanceImportController::class, 'confirm'])->name('confirm');
        // Rules (ngưỡng phạt)
        Route::post('/rules', [AttendanceImportRulesController::class, 'store'])->name('rules.store');
        Route::put('/rules/{rule}', [AttendanceImportRulesController::class, 'update'])->name('rules.update');
        Route::delete('/rules/{rule}', [AttendanceImportRulesController::class, 'destroy'])->name('rules.destroy');
        Route::patch('/rules/{rule}/toggle', [AttendanceImportRulesController::class, 'toggleActive'])->name('rules.toggle');
    });

    // Regulations (create/edit via modal)
    Route::get('/regulations', [RegulationsController::class, 'index'])->name('regulations.index')->middleware('can:view-regulations');
    Route::get('/regulations/{regulation}', [RegulationsController::class, 'show'])->name('regulations.show')->middleware('can:view-regulations');
    Route::post('/regulations', [RegulationsController::class, 'store'])->name('regulations.store')->middleware('can:create-regulations');
    Route::put('/regulations/{regulation}', [RegulationsController::class, 'update'])->name('regulations.update')->middleware('can:edit-regulations');
    Route::delete('/regulations/{regulation}', [RegulationsController::class, 'destroy'])->name('regulations.destroy')->middleware('can:delete-regulations');

    // Reward Categories — danh mục nhóm thưởng
    Route::get('/reward-categories', [RewardCategoriesController::class, 'index'])->name('reward-categories.index')->middleware('can:view-reward-categories');
    Route::post('/reward-categories', [RewardCategoriesController::class, 'store'])->name('reward-categories.store')->middleware('can:create-reward-categories');
    Route::put('/reward-categories/{rewardCategory}', [RewardCategoriesController::class, 'update'])->name('reward-categories.update')->middleware('can:edit-reward-categories');
    Route::delete('/reward-categories/{rewardCategory}', [RewardCategoriesController::class, 'destroy'])->name('reward-categories.destroy')->middleware('can:delete-reward-categories');

    // Reward Types — loại thưởng
    Route::get('/reward-types', [RewardTypesController::class, 'index'])->name('reward-types.index')->middleware('can:view-reward-types');
    Route::post('/reward-types', [RewardTypesController::class, 'store'])->name('reward-types.store')->middleware('can:create-reward-types');
    Route::put('/reward-types/{rewardType}', [RewardTypesController::class, 'update'])->name('reward-types.update')->middleware('can:edit-reward-types');
    Route::delete('/reward-types/{rewardType}', [RewardTypesController::class, 'destroy'])->name('reward-types.destroy')->middleware('can:delete-reward-types');

    // Rewards — phiếu thưởng
    Route::prefix('rewards')->name('rewards.')->group(function () {
        Route::get('/', [RewardsController::class, 'index'])->name('index')->middleware('can:view-rewards');
        Route::post('/', [RewardsController::class, 'store'])->name('store')->middleware('can:create-rewards');
        Route::get('/{reward}', [RewardsController::class, 'show'])->name('show')->middleware('can:view-rewards');
        Route::put('/{reward}', [RewardsController::class, 'update'])->name('update')->middleware('can:create-rewards');
        Route::delete('/{reward}', [RewardsController::class, 'destroy'])->name('destroy')->middleware('can:delete-rewards');
        Route::post('/{reward}/approve', [RewardsController::class, 'approve'])->name('approve')->middleware('can:approve-rewards');
        Route::post('/{reward}/reject', [RewardsController::class, 'reject'])->name('reject')->middleware('can:approve-rewards');
    });

    // Rankings — xem được bởi mọi người đã đăng nhập
    Route::get('/rankings', [RankingsController::class, 'index'])
        ->name('rankings.index');

    // Zone monitor (green/yellow/orange/red) — xem được bởi mọi người đã đăng nhập
    Route::get('/redzone', [RedzoneController::class, 'index'])
        ->name('redzone.index');

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])
        ->name('settings.index')
        ->middleware('can:manage-settings');
    Route::post('/settings', [SettingsController::class, 'update'])
        ->name('settings.update')
        ->middleware('can:manage-settings');

    // Activity Log
    Route::get('/activity-log', [ActivityLogController::class, 'index'])
        ->name('activity.log')
        ->middleware('can:view-activity-log');

    // User Management (admin: manage-users) — create/edit via modal
    Route::middleware('can:manage-users')->group(function () {
        Route::resource('users', UsersController::class)->except(['create', 'edit', 'show']);
        Route::post('/users/{user}/toggle-status', [UsersController::class, 'toggleStatus'])->name('users.toggleStatus');
    });

    // Role & Permission Management (admin: manage-roles) — create/edit via modal
    Route::middleware('can:manage-roles')->group(function () {
        Route::resource('roles', RolesController::class)->except(['create', 'edit', 'show']);
    });

    // Notifications
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationsController::class, 'index'])->name('index');
        Route::post('/', [NotificationsController::class, 'store'])->name('store')->middleware('can:create-notifications');
        Route::get('/unread-count', [NotificationsController::class, 'unreadCount'])->name('unread-count');
        Route::post('/read-all', [NotificationsController::class, 'markAllRead'])->name('read-all');
        Route::get('/{notification}', [NotificationsController::class, 'show'])->name('show');
        Route::post('/{notification}/read', [NotificationsController::class, 'markRead'])->name('read');
        Route::delete('/{notification}', [NotificationsController::class, 'destroy'])->name('destroy');
    });
});

// Redirect /home to /dashboard
Route::redirect('/home', '/dashboard');

require __DIR__ . '/auth.php';
