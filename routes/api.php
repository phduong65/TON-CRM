<?php

use App\Http\Controllers\Api\AttendanceController as ApiAttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FcmTokenController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PenaltyController;
use App\Http\Controllers\Api\RewardController;
use App\Http\Controllers\Api\ScoreController;
use App\Http\Controllers\Api\ShiftScheduleController;
use App\Http\Controllers\Api\ShiftSwapRequestController;
use App\Http\Controllers\Api\StaffRequestController;
use App\Http\Controllers\AttendanceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API cho app Flutter (nhân viên) — token qua Laravel Sanctum.
| checkIn/checkOut dùng chung App\Http\Controllers\AttendanceController với
| web vì logic xác thực GPS+IP là như nhau (guard-agnostic, đã trả JSON sẵn).
|--------------------------------------------------------------------------
*/

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::patch('/me/theme', [AuthController::class, 'updateTheme']);

    Route::prefix('attendance')->middleware('can:checkin-attendance')->group(function () {
        Route::get('/today', [ApiAttendanceController::class, 'today']);
        Route::post('/check-in', [AttendanceController::class, 'checkIn']);
        Route::post('/check-out', [AttendanceController::class, 'checkOut']);
    });
    Route::get('/attendance/history', [ApiAttendanceController::class, 'history'])->middleware('can:view-own-attendance');

    Route::get('/scores/current', [ScoreController::class, 'current']);
    Route::get('/scores/history', [ScoreController::class, 'history']);
    Route::get('/scores/ranking', [ScoreController::class, 'ranking']);

    Route::get('/shift-schedules', [ShiftScheduleController::class, 'index'])->middleware('can:view-own-schedule');
    Route::get('/shift-schedules/lookup', [ShiftScheduleController::class, 'lookup'])->middleware('can:view-shift-swaps');

    Route::get('/penalties', [PenaltyController::class, 'index'])->middleware('can:view-penalties');
    Route::get('/penalties/{penalty}', [PenaltyController::class, 'show'])->middleware('can:view-penalties');

    Route::get('/rewards', [RewardController::class, 'index'])->middleware('can:view-rewards');
    Route::get('/rewards/{reward}', [RewardController::class, 'show'])->middleware('can:view-rewards');

    Route::prefix('leave-requests')->group(function () {
        Route::get('/', [LeaveRequestController::class, 'index'])->middleware('can:view-leave-requests');
        Route::post('/', [LeaveRequestController::class, 'store'])->middleware('can:create-leave-requests');
        Route::delete('/{leaveRequest}', [LeaveRequestController::class, 'destroy'])->middleware('can:create-leave-requests');
    });

    Route::prefix('shift-swap-requests')->group(function () {
        Route::get('/', [ShiftSwapRequestController::class, 'index'])->middleware('can:view-shift-swaps');
        Route::post('/', [ShiftSwapRequestController::class, 'store'])->middleware('can:create-shift-swaps');
        Route::delete('/{shiftSwapRequest}', [ShiftSwapRequestController::class, 'destroy'])->middleware('can:create-shift-swaps');
    });

    Route::prefix('staff-requests')->group(function () {
        Route::get('/', [StaffRequestController::class, 'index'])->middleware('can:view-staff-requests');
        Route::post('/', [StaffRequestController::class, 'store'])->middleware('can:create-staff-requests');
        Route::delete('/{staffRequest}', [StaffRequestController::class, 'destroy'])->middleware('can:create-staff-requests');
    });

    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('/{notification}/read', [NotificationController::class, 'markRead']);
    });

    Route::post('/fcm-tokens', [FcmTokenController::class, 'store']);
    Route::delete('/fcm-tokens', [FcmTokenController::class, 'destroy']);
});
