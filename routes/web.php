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
use App\Http\Controllers\PositionsController;
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
use App\Http\Controllers\EmployeeReportsController;
use App\Http\Controllers\Dev\TestRunnerController;
use App\Http\Controllers\GoogleSheetsController;
use App\Http\Controllers\LogViewerController;
use App\Http\Controllers\AppealsController;
use App\Http\Controllers\ShiftsController;
use App\Http\Controllers\HolidaysController;
use App\Http\Controllers\AttendanceLocationsController;
use App\Http\Controllers\ShiftSchedulesController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceLogsController;
use App\Http\Controllers\TimesheetConfirmationController;
use App\Http\Controllers\TimesheetConfirmationsController;
use App\Http\Controllers\MyAttendanceLogsController;
use App\Http\Controllers\LeaveRequestsController;
use App\Http\Controllers\ShiftSwapRequestsController;
use App\Http\Controllers\StaffRequestsController;
use App\Http\Controllers\AnnualLeaveController;
use App\Http\Controllers\MyScheduleController;
use App\Http\Controllers\FcmTokensController;
use App\Http\Controllers\ImpersonateController;
use App\Http\Controllers\PolicyController;
use App\Http\Controllers\ThemesAdminController;
use App\Http\Controllers\ShiftCoverageRequirementsController;
use App\Http\Controllers\OperationalScheduleController;
use App\Http\Controllers\AttendanceAlertsController;

/*
|--------------------------------------------------------------------------
| Web Routes — TON-HR Staff Discipline Management System
|--------------------------------------------------------------------------
| Giao diện tiếng Việt — Blade thuần + Tailwind CSS
*/

// Public Theme resolve API (dành cho mobile apps hoặc external clients)
Route::get('/api/v1/theme/resolve', [ThemesAdminController::class, 'resolveApi'])->name('api.theme.resolve');

// Dev-only: Test Runner (local environment only)
if (app()->isLocal()) {
    Route::middleware('auth')->prefix('dev')->name('dev.')->group(function () {
        Route::get('/test-runner', [TestRunnerController::class, 'index'])->name('test-runner.index');
        Route::post('/test-runner/run', [TestRunnerController::class, 'run'])->name('test-runner.run');
    });
}

// Root: redirect to dashboard if authenticated, else to login
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// Service worker Firebase Messaging — phải nằm ngoài middleware auth (fetch service worker
// không kèm session cookie theo cách thông thường) và ở URL gốc để scope phủ toàn bộ site.
Route::get('/firebase-messaging-sw.js', [FcmTokensController::class, 'serviceWorker'])->name('firebase-messaging-sw');

// Trang tra cứu "Nội quy Thưởng & Phạt" — công khai, không cần đăng nhập (nhân viên floor có
// thể chưa có tài khoản CRM). Thay thế file HTML tĩnh cũ (public/html/Luat_Thuong_Phat_NhanVien.html,
// vẫn giữ nguyên không xoá) — dữ liệu lấy trực tiếp từ regulations/violations/reward_categories/
// reward_types nên tự động cập nhật khi admin chỉnh sửa trong CRM.
Route::get('/noi-quy-thuong-phat', [PolicyController::class, 'index'])->name('policy.index');

// Authentication (Laravel built-in)
Route::middleware('auth')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    // Mobile Menu
    Route::get('/menu', [DashboardController::class, 'mobileMenu'])
        ->name('menu.index');

    // Theme toggle
    Route::post('/theme/toggle', [ThemeController::class, 'toggle'])
        ->name('theme.toggle');

    // Keep-alive: JS gọi định kỳ khi tab đang mở để "chạm" vào session, tránh session bị
    // dọn/hết hạn giữa lúc nhân viên đang điền form/modal lâu → tránh lỗi 419 khi submit.
    // Không làm gì khác ngoài trả về 200 — bản thân middleware session sẽ tự lưu lại session
    // (gia hạn) ở cuối mỗi request đã qua middleware 'auth'.
    Route::get('/keep-alive', function () {
        return response()->json(['ok' => true]);
    })->name('keep-alive');

    // Profile — xem & chỉnh sửa hồ sơ cá nhân
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');

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

    // Positions — Full CRUD (create/edit via modal)
    Route::get('/positions', [PositionsController::class, 'index'])->name('positions.index')->middleware('can:view-positions');
    Route::post('/positions', [PositionsController::class, 'store'])->name('positions.store')->middleware('can:create-positions');
    Route::put('/positions/{position}', [PositionsController::class, 'update'])->name('positions.update')->middleware('can:edit-positions');
    Route::delete('/positions/{position}', [PositionsController::class, 'destroy'])->name('positions.destroy')->middleware('can:delete-positions');

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
        Route::delete('/{penalty}', [PenaltiesController::class, 'destroy'])->name('destroy')->middleware('can:delete-penalties');
        Route::post('/{penalty}/approve', [PenaltiesController::class, 'approve'])->name('approve')->middleware('can:approve-penalties');
        Route::post('/{penalty}/reject', [PenaltiesController::class, 'reject'])->name('reject')->middleware('can:approve-penalties');
        Route::post('/{penalty}/revoke', [PenaltiesController::class, 'revoke'])->name('revoke')->middleware('can:revoke-penalties');
        Route::post('/{penalty}/appeal', [AppealsController::class, 'store'])->name('appeal')->middleware('can:create-appeals');
    });

    // Appeals — khiếu nại phiếu phạt
    Route::prefix('appeals')->name('appeals.')->group(function () {
        Route::get('/', [AppealsController::class, 'index'])->name('index')->middleware('can:view-appeals');
        Route::post('/{appeal}/accept', [AppealsController::class, 'accept'])->name('accept')->middleware('can:review-appeals');
        Route::post('/{appeal}/reject', [AppealsController::class, 'reject'])->name('reject')->middleware('can:review-appeals');
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

    // Shifts — mẫu ca làm việc (create/edit via modal)
    Route::prefix('shifts')->name('shifts.')->group(function () {
        Route::get('/', [ShiftsController::class, 'index'])->name('index')->middleware('can:view-shifts');
        Route::post('/', [ShiftsController::class, 'store'])->name('store')->middleware('can:create-shifts');
        Route::put('/{shift}', [ShiftsController::class, 'update'])->name('update')->middleware('can:edit-shifts');
        Route::delete('/{shift}', [ShiftsController::class, 'destroy'])->name('destroy')->middleware('can:delete-shifts');
    });

    // Holidays — ngày nghỉ lễ có lương + thưởng (nếu có), dùng để tính công (create/edit via modal)
    Route::prefix('holidays')->name('holidays.')->group(function () {
        Route::get('/', [HolidaysController::class, 'index'])->name('index')->middleware('can:view-holidays');
        Route::post('/', [HolidaysController::class, 'store'])->name('store')->middleware('can:create-holidays');
        Route::put('/{holiday}', [HolidaysController::class, 'update'])->name('update')->middleware('can:edit-holidays');
        Route::delete('/{holiday}', [HolidaysController::class, 'destroy'])->name('destroy')->middleware('can:delete-holidays');
    });

    // Attendance Locations — điểm chấm công GPS/IP theo chi nhánh (create/edit via modal)
    Route::prefix('attendance-locations')->name('attendance-locations.')->group(function () {
        Route::get('/', [AttendanceLocationsController::class, 'index'])->name('index')->middleware('can:view-attendance-locations');
        Route::get('/detect-ip', [AttendanceLocationsController::class, 'detectIp'])->name('detect-ip')->middleware('can:view-attendance-locations');
        Route::post('/', [AttendanceLocationsController::class, 'store'])->name('store')->middleware('can:create-attendance-locations');
        Route::put('/{attendanceLocation}', [AttendanceLocationsController::class, 'update'])->name('update')->middleware('can:edit-attendance-locations');
        Route::delete('/{attendanceLocation}', [AttendanceLocationsController::class, 'destroy'])->name('destroy')->middleware('can:delete-attendance-locations');
    });

    // Shift Schedules — xếp ca cố định & đa ca
    Route::prefix('shift-schedules')->name('shift-schedules.')->group(function () {
        Route::get('/', [ShiftSchedulesController::class, 'index'])->name('index')->middleware('can:view-shift-schedules');
        Route::get('/export', [ShiftSchedulesController::class, 'export'])->name('export')->middleware('can:export-shift-schedules');
        Route::get('/on-shift', [ShiftSchedulesController::class, 'onShiftJson'])->name('on-shift')->middleware('can:view-attendance');
        Route::post('/', [ShiftSchedulesController::class, 'store'])->name('store')->middleware('can:create-shift-schedules');
        Route::post('/bulk', [ShiftSchedulesController::class, 'bulkStore'])->name('bulk-store')->middleware('can:create-shift-schedules');
        Route::put('/{shiftSchedule}', [ShiftSchedulesController::class, 'update'])->name('update')->middleware('can:edit-shift-schedules');
        Route::delete('/bulk', [ShiftSchedulesController::class, 'destroyBulk'])->name('bulk-destroy')->middleware('can:delete-shift-schedules');
        Route::delete('/destroy-all', [ShiftSchedulesController::class, 'destroyAll'])->name('destroy-all')->middleware('can:delete-shift-schedules');
        Route::delete('/destroy-filtered', [ShiftSchedulesController::class, 'destroyFiltered'])->name('destroy-filtered')->middleware('can:delete-shift-schedules');
        Route::delete('/{shiftSchedule}', [ShiftSchedulesController::class, 'destroy'])->name('destroy')->middleware('can:delete-shift-schedules');
    });

    // Lịch làm việc cá nhân — dạng lịch tháng (kiểu Google Calendar)
    Route::get('/my-schedule', [MyScheduleController::class, 'index'])->name('my-schedule.index')->middleware('can:view-own-schedule');
    Route::get('/my-schedule/events', [MyScheduleController::class, 'events'])->name('my-schedule.events')->middleware('can:view-own-schedule');
    Route::get('/my-schedule/attendance-summary', [MyScheduleController::class, 'attendanceSummary'])->name('my-schedule.attendance-summary')->middleware('can:view-own-attendance');
    Route::get('/my-schedule/export', [MyScheduleController::class, 'export'])->name('my-schedule.export')->middleware('can:export-own-schedule');

    // Attendance — chấm công cá nhân (check-in/out qua GPS + IP văn phòng)
    Route::prefix('attendance')->name('attendance.')->middleware('can:checkin-attendance')->group(function () {
        Route::get('/', [AttendanceController::class, 'index'])->name('index');
        Route::post('/check-in', [AttendanceController::class, 'checkIn'])->name('check-in');
        Route::post('/check-out', [AttendanceController::class, 'checkOut'])->name('check-out');
    });

    // My Attendance Logs — lịch sử chấm công dạng bảng của CHÍNH nhân viên đang đăng nhập
    Route::get('/my-attendance-logs', [MyAttendanceLogsController::class, 'index'])->name('my-attendance-logs.index')->middleware('can:view-own-attendance');

    // Attendance Logs — báo cáo chấm công cho HR/Manager
    Route::get('/attendance-logs', [AttendanceLogsController::class, 'index'])->name('attendance-logs.index')->middleware('can:view-attendance');
    Route::get('/attendance-logs/export', [AttendanceLogsController::class, 'export'])->name('attendance-logs.export')->middleware('can:export-attendance');
    Route::get('/attendance-logs/export-timesheet', [AttendanceLogsController::class, 'exportTimesheet'])->name('attendance-logs.export-timesheet')->middleware('can:export-attendance');
    Route::get('/attendance-logs/employee-shifts', [AttendanceLogsController::class, 'employeeShifts'])->name('attendance-logs.employee-shifts')->middleware('can:create-attendance-logs');
    Route::post('/attendance-logs', [AttendanceLogsController::class, 'store'])->name('attendance-logs.store')->middleware('can:create-attendance-logs');
    Route::put('/attendance-logs/{attendanceLog}', [AttendanceLogsController::class, 'update'])->name('attendance-logs.update')->middleware('can:edit-attendance-logs');
    Route::delete('/attendance-logs/{attendanceLog}', [AttendanceLogsController::class, 'destroy'])->name('attendance-logs.destroy')->middleware('can:delete-attendance-logs');

    // Operational Schedule — Lịch vận hành & định biên tuần theo chi nhánh / bộ phận
    Route::get('/operational-schedule', [OperationalScheduleController::class, 'index'])
        ->name('operational-schedule.index')
        ->middleware('can:view-shift-coverage');

    // Shift Coverage Requirements — Quy tắc định biên nhân sự theo khung giờ (Modal-First)
    Route::prefix('shift-coverage-requirements')->name('shift-coverage-requirements.')->group(function () {
        Route::get('/', [ShiftCoverageRequirementsController::class, 'index'])->name('index')->middleware('can:view-shift-coverage');
        Route::post('/', [ShiftCoverageRequirementsController::class, 'store'])->name('store')->middleware('can:manage-shift-coverage');
        Route::put('/{shiftCoverageRequirement}', [ShiftCoverageRequirementsController::class, 'update'])->name('update')->middleware('can:manage-shift-coverage');
        Route::delete('/{shiftCoverageRequirement}', [ShiftCoverageRequirementsController::class, 'destroy'])->name('destroy')->middleware('can:manage-shift-coverage');
    });

    // Attendance Alerts — Cảnh báo thiếu check-in / check-out
    Route::prefix('attendance-alerts')->name('attendance-alerts.')->group(function () {
        Route::get('/', [AttendanceAlertsController::class, 'index'])->name('index')->middleware('can:view-attendance-alerts');
        Route::post('/{attendanceAlert}/excuse', [AttendanceAlertsController::class, 'excuse'])->name('excuse')->middleware('can:manage-attendance-alerts');
        Route::post('/{attendanceAlert}/dismiss', [AttendanceAlertsController::class, 'dismiss'])->name('dismiss');
        Route::post('/dismiss-all', [AttendanceAlertsController::class, 'dismissAll'])->name('dismiss-all');
        Route::get('/my-alerts', [AttendanceAlertsController::class, 'myAlerts'])->name('my-alerts');
    });

    // Xác nhận công — nhân viên tự xem/xác nhận bảng công tháng của mình
    Route::prefix('timesheet-confirmation')->name('timesheet-confirmation.')->middleware('can:view-own-timesheet-confirmation')->group(function () {
        Route::get('/', [TimesheetConfirmationController::class, 'index'])->name('index');
        Route::post('/confirm', [TimesheetConfirmationController::class, 'confirm'])->name('confirm');
        Route::post('/unconfirm', [TimesheetConfirmationController::class, 'unconfirm'])->name('unconfirm');
    });

    // Xác nhận công (HR/Admin) — xem trạng thái xác nhận của tất cả nhân viên + xác nhận hộ
    Route::prefix('timesheet-confirmations')->name('timesheet-confirmations.')->group(function () {
        Route::get('/', [TimesheetConfirmationsController::class, 'index'])->name('index')->middleware('can:view-timesheet-confirmations');
        Route::get('/{employee}', [TimesheetConfirmationsController::class, 'show'])->name('show')->middleware('can:view-timesheet-confirmations');
        Route::post('/{employee}/confirm', [TimesheetConfirmationsController::class, 'confirm'])->name('confirm')->middleware('can:confirm-timesheet-on-behalf');
    });

    // Leave Requests — nhân viên xin nghỉ phép
    Route::prefix('leave-requests')->name('leave-requests.')->group(function () {
        Route::get('/', [LeaveRequestsController::class, 'index'])->name('index')->middleware('can:view-leave-requests');
        Route::get('/shifts-for-range', [LeaveRequestsController::class, 'shiftsForRange'])->name('shifts-for-range')->middleware('can:create-leave-requests');
        Route::post('/', [LeaveRequestsController::class, 'store'])->name('store')->middleware('can:create-leave-requests');
        Route::post('/{leaveRequest}/approve', [LeaveRequestsController::class, 'approve'])->name('approve')->middleware('can:approve-leave-requests');
        Route::post('/{leaveRequest}/reject', [LeaveRequestsController::class, 'reject'])->name('reject')->middleware('can:approve-leave-requests');
        Route::delete('/{leaveRequest}', [LeaveRequestsController::class, 'destroy'])->name('destroy')->middleware('can:create-leave-requests');
    });

    // Shift Swap Requests — nhân viên xin đổi ca với nhau
    Route::prefix('shift-swap-requests')->name('shift-swap-requests.')->group(function () {
        Route::get('/', [ShiftSwapRequestsController::class, 'index'])->name('index')->middleware('can:view-shift-swaps');
        Route::post('/', [ShiftSwapRequestsController::class, 'store'])->name('store')->middleware('can:create-shift-swaps');
        Route::post('/{shiftSwapRequest}/approve', [ShiftSwapRequestsController::class, 'approve'])->name('approve')->middleware('can:approve-shift-swaps');
        Route::post('/{shiftSwapRequest}/reject', [ShiftSwapRequestsController::class, 'reject'])->name('reject')->middleware('can:approve-shift-swaps');
        Route::delete('/{shiftSwapRequest}', [ShiftSwapRequestsController::class, 'destroy'])->name('destroy')->middleware('can:create-shift-swaps');
    });

    // Staff Requests — hub "Yêu cầu và Phê duyệt": gộp hiển thị Lượt chấm công, Công tác/Ra ngoài,
    // Đi muộn về sớm, Nghỉ phép, Thay đổi giờ vào/ra, Đổi ca làm. 4 loại đầu (không phải Nghỉ phép/Đổi ca)
    // dùng bảng staff_requests riêng — xem StaffRequestsController.
    Route::prefix('staff-requests')->name('staff-requests.')->group(function () {
        Route::get('/', [StaffRequestsController::class, 'index'])->name('index')->middleware('can:view-staff-requests');
        Route::get('/{type}/{id}/details', [StaffRequestsController::class, 'details'])->name('details');
        Route::post('/', [StaffRequestsController::class, 'store'])->name('store')->middleware('can:create-staff-requests');
        Route::put('/{staffRequest}', [StaffRequestsController::class, 'update'])->name('update')->middleware('can:create-staff-requests');
        Route::post('/{staffRequest}/approve', [StaffRequestsController::class, 'approve'])->name('approve')->middleware('can:approve-staff-requests');
        Route::post('/{staffRequest}/reject', [StaffRequestsController::class, 'reject'])->name('reject')->middleware('can:approve-staff-requests');
        Route::delete('/{staffRequest}', [StaffRequestsController::class, 'destroy'])->name('destroy')->middleware('can:create-staff-requests');
    });

    // Phép năm — báo cáo tổng hợp số ngày phép năm (được hưởng/đã dùng/còn lại) của tất cả
    // nhân viên văn phòng đủ điều kiện cùng lúc, thay vì xem từng người trên trang chi tiết NV.
    Route::get('/annual-leave', [AnnualLeaveController::class, 'index'])->name('annual-leave.index')->middleware('can:view-annual-leave');

    // Regulations (create/edit via modal)
    Route::get('/regulations', [RegulationsController::class, 'index'])->name('regulations.index')->middleware('can:view-regulations');
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
        Route::post('/{reward}/revoke', [RewardsController::class, 'revoke'])->name('revoke')->middleware('can:revoke-rewards');
        Route::get('/{reward}/detail-json', [RewardsController::class, 'detailJson'])->name('detail-json')->middleware('can:view-rewards');
    });

    // Employee Reports — báo cáo vi phạm nhân viên
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [EmployeeReportsController::class, 'index'])->name('index')->middleware('can:view-reports');
        Route::post('/', [EmployeeReportsController::class, 'store'])->name('store')->middleware('can:create-reports');
        Route::get('/{report}', [EmployeeReportsController::class, 'show'])->name('show')->middleware('can:view-reports');
        Route::post('/{report}/approve', [EmployeeReportsController::class, 'approve'])->name('approve')->middleware('can:approve-reports');
        Route::post('/{report}/reject', [EmployeeReportsController::class, 'reject'])->name('reject')->middleware('can:approve-reports');
        Route::delete('/{report}', [EmployeeReportsController::class, 'destroy'])->name('destroy')->middleware('can:create-reports');
    });

    // Rankings — xem được bởi mọi người đã đăng nhập
    Route::get('/rankings', [RankingsController::class, 'index'])
        ->name('rankings.index');

    // Vùng điểm & Redzone (worklist) — quyền view-redzone (admin/manager/director)
    Route::get('/redzone', [RedzoneController::class, 'index'])
        ->name('redzone.index')
        ->middleware('can:view-redzone');

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])
        ->name('settings.index')
        ->middleware('can:manage-settings');
    Route::post('/settings', [SettingsController::class, 'update'])
        ->name('settings.update')
        ->middleware('can:manage-settings');
    Route::post('/settings/test-firebase', [SettingsController::class, 'testFirebase'])
        ->name('settings.test-firebase')
        ->middleware('can:manage-settings');
    Route::post('/settings/check-scheduler', [SettingsController::class, 'checkScheduler'])
        ->name('settings.check-scheduler')
        ->middleware('can:manage-settings');

    // Google Sheets Sync
    Route::prefix('google-sheets')->name('google-sheets.')->middleware('can:manage-settings')->group(function () {
        Route::get('/', [GoogleSheetsController::class, 'index'])->name('index');
        Route::post('/push', [GoogleSheetsController::class, 'push'])->name('push');
        Route::post('/import', [GoogleSheetsController::class, 'import'])->name('import');
    });

    // Theme / Event Engine Management
    Route::prefix('themes')->name('themes.')->middleware('can:manage-settings')->group(function () {
        Route::get('/', [ThemesAdminController::class, 'index'])->name('index');
        Route::post('/', [ThemesAdminController::class, 'store'])->name('store');
        Route::put('/{theme}', [ThemesAdminController::class, 'update'])->name('update');
        Route::delete('/{theme}', [ThemesAdminController::class, 'destroy'])->name('destroy');
        Route::post('/{theme}/toggle', [ThemesAdminController::class, 'toggleStatus'])->name('toggle');
        Route::post('/{theme}/rollback', [ThemesAdminController::class, 'rollback'])->name('rollback');
        Route::post('/{theme}/duplicate', [ThemesAdminController::class, 'duplicate'])->name('duplicate');
        Route::post('/preview-render', [ThemesAdminController::class, 'previewRender'])->name('preview.render');
    });

    // Activity Log
    Route::get('/activity-log', [ActivityLogController::class, 'index'])
        ->name('activity.log')
        ->middleware('can:view-activity-log');

    // System Log Viewer (custom)
    Route::get('/log-viewer', [LogViewerController::class, 'index'])
        ->name('log-viewer.index')
        ->middleware('can:view-log-viewer');

    // User Management (admin: manage-users) — create/edit via modal
    Route::middleware('can:manage-users')->group(function () {
        Route::resource('users', UsersController::class)->except(['create', 'edit', 'show']);
        Route::post('/users/{user}/toggle-status', [UsersController::class, 'toggleStatus'])->name('users.toggleStatus');
        Route::post('/users/{user}/reject', [UsersController::class, 'reject'])->name('users.reject');
    });

    // Đăng nhập hộ (impersonate) — leave route KHÔNG gate qua permission vì trong lúc đang đăng
    // nhập hộ, danh tính hiện tại là tài khoản nhân viên (không có quyền impersonate-users).
    Route::post('/users/{user}/impersonate', [ImpersonateController::class, 'store'])
        ->name('users.impersonate')->middleware('can:impersonate-users');
    Route::delete('/impersonate', [ImpersonateController::class, 'destroy'])->name('impersonate.leave');

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

    // FCM tokens — đăng ký/huỷ đăng ký nhận thông báo đẩy Chrome trên trình duyệt hiện tại.
    // Hành động cá nhân (chỉ tác động tới token của chính user đang đăng nhập) — không cần permission riêng.
    Route::post('/fcm-tokens', [FcmTokensController::class, 'store'])->name('fcm-tokens.store');
    Route::delete('/fcm-tokens', [FcmTokensController::class, 'destroy'])->name('fcm-tokens.destroy');
});

// Redirect /home to /dashboard
Route::redirect('/home', '/dashboard');

require __DIR__ . '/auth.php';
