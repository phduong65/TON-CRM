@php
    $routeName = request()->route()?->getName() ?? '';
    $isActive = fn($prefixes) => collect((array) $prefixes)->contains(fn($p) => str_starts_with($routeName, $p));

    try {
        $redzoneCount = \App\Models\Employee::whereHas('scores', function ($q) {
            $q->selectRaw('SUM(points) as total')->having(
                'total',
                '<',
                \App\Models\Setting::getValue('redzone_threshold', 50),
            );
        })->count();
    } catch (\Exception $e) {
        $redzoneCount = 0;
    }

    try {
        $unreadNotifCount = \App\Models\Notification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->count();
    } catch (\Exception $e) {
        $unreadNotifCount = 0;
    }

    try {
        $alertCount = \App\Models\AttendanceAlert::whereIn('status', ['open', 'seen'])->count();
    } catch (\Exception $e) {
        $alertCount = 0;
    }
@endphp

<!-- Left sidebar (desktop) -->
<aside id="left-panel"
    class="hidden lg:flex relative w-[260px] shrink-0 bg-white dark:bg-slate-800 border-r border-slate-200 dark:border-slate-700 flex-col overflow-visible">

    <!-- Thu nhỏ / mở rộng sidebar (chỉ desktop) -->
    <button type="button" onclick="toggleSidebar()" title="Thu nhỏ/mở rộng menu"
        class="sidebar-toggle-btn hidden lg:flex absolute -right-3 top-6 z-10 w-6 h-6 items-center justify-center rounded-full bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-500 dark:text-slate-300 shadow-sm hover:bg-slate-50 dark:hover:bg-slate-600">
        <i class="bi bi-chevron-left text-xs"></i>
    </button>

    <!-- User Profile -->
    {{-- <div class="px-4 pt-5 pb-4 border-b border-slate-100 dark:border-slate-700 shrink-0">
        <div class="flex flex-col items-center text-center gap-1.5">
            <div
                class="w-14 h-14 rounded-xl bg-pcrm-100 dark:bg-pcrm-900/50 flex items-center justify-center text-pcrm-700 dark:text-pcrm-400 font-bold text-xl">
                {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
            </div>
            <p class="font-semibold text-slate-900 dark:text-white text-sm leading-snug">{{ auth()->user()->name }}</p>
            <p class="text-xs text-slate-400 dark:text-slate-500">
                @if (auth()->user()->hasRole('admin'))
                    Quản trị viên
                @elseif(auth()->user()->hasRole('director'))
                    Giám đốc
                @elseif(auth()->user()->hasRole('manager'))
                    Quản lý
                @elseif(auth()->user()->hasRole('team-leader'))
                    Trưởng nhóm
                @else
                    Nhân viên
                @endif
            </p>
        </div>
    </div> --}}

    <!-- Full navigation -->
    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">

        {{-- ── TỔNG QUAN ─────────────────────────────── --}}
        <p
            class="px-3 pb-1.5 pt-1 text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
            Tổng quan</p>

        <a href="{{ route('dashboard') }}"
            class="sidebar-link {{ $isActive(['dashboard']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
            <i class="bi bi-speedometer2 text-base"></i>
            <span>Bảng điều khiển</span>
        </a>

        <a href="{{ route('notifications.index') }}"
            class="sidebar-link {{ $isActive(['notifications']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
            <i class="bi bi-bell text-base"></i>
            <span class="flex-1">Thông báo</span>
            @if ($unreadNotifCount > 0)
                <span
                    class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold rounded-full bg-red-500 text-white leading-none">
                    {{ $unreadNotifCount > 99 ? '99+' : $unreadNotifCount }}
                </span>
            @endif
        </a>
        <a href="{{ route('policy.index') }}" target="_blank" rel="noopener"
            class="sidebar-link {{ $isActive(['policy']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
            <i class="bi bi-file-earmark-text text-base"></i>
            <span>Nội Quy Công Ty</span>
        </a>
        {{-- ── NHÂN SỰ ──────────────────────────────── --}}
        <p
            class="px-3 pb-1.5 pt-4 text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
            Nhân sự</p>

        @can('view-employees')
            <a href="{{ route('employees.index') }}"
                class="sidebar-link {{ $isActive(['employees']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-people text-base"></i>
                <span>Nhân viên</span>
            </a>
        @endcan

        @can('view-teams')
            <a href="{{ route('teams.index') }}"
                class="sidebar-link {{ $isActive(['teams']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-diagram-3 text-base"></i>
                <span>Đội nhóm</span>
            </a>
        @endcan

        @can('view-branches')
            <a href="{{ route('branches.index') }}"
                class="sidebar-link {{ $isActive(['branches']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-building text-base"></i>
                <span>Chi nhánh</span>
            </a>
        @endcan

        @can('view-positions')
            <a href="{{ route('positions.index') }}"
                class="sidebar-link {{ $isActive(['positions']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-person-badge text-base"></i>
                <span>Chức danh</span>
            </a>
        @endcan

        {{-- ── CA LÀM VIỆC ───────────────────────────── --}}
        @canany(['view-own-schedule', 'view-shifts', 'view-shift-schedules', 'view-holidays'])
        <p
            class="px-3 pb-1.5 pt-4 text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
            Ca làm việc</p>

        @can('view-own-schedule')
            <a href="{{ route('my-schedule.index') }}"
                class="sidebar-link {{ $isActive(['my-schedule']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-calendar3 text-base"></i>
                <span>Lịch làm việc</span>
            </a>
        @endcan

        @can('view-shifts')
            <a href="{{ route('shifts.index') }}"
                class="sidebar-link {{ $isActive(['shifts']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-clock-history text-base"></i>
                <span>Ca làm việc</span>
            </a>
        @endcan

        @can('view-shift-schedules')
            <a href="{{ route('shift-schedules.index') }}"
                class="sidebar-link {{ $isActive(['shift-schedules']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-calendar-week text-base"></i>
                <span>Xếp ca</span>
            </a>
        @endcan

        @can('view-shift-coverage')
            <a href="{{ route('operational-schedule.index') }}"
                class="sidebar-link {{ $isActive(['operational-schedule']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-calendar2-range text-base"></i>
                <span>Lịch vận hành</span>
            </a>

            <a href="{{ route('shift-coverage-requirements.index') }}"
                class="sidebar-link {{ $isActive(['shift-coverage-requirements']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-people-fill text-base"></i>
                <span>Định biên ca</span>
            </a>
        @endcan

        @can('view-holidays')
            <a href="{{ route('holidays.index') }}"
                class="sidebar-link {{ $isActive(['holidays']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-calendar-event text-base"></i>
                <span>Ngày nghỉ lễ</span>
            </a>
        @endcan
        @endcanany

        {{-- ── CHẤM CÔNG ──────────────────────────────── --}}
        @canany(['checkin-attendance', 'view-own-attendance', 'view-attendance-locations', 'view-attendance', 'import-attendance', 'view-staff-requests', 'view-leave-requests', 'view-shift-swaps', 'view-own-timesheet-confirmation', 'view-timesheet-confirmations'])
        <p
            class="px-3 pb-1.5 pt-4 text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
            Chấm công</p>

        @can('checkin-attendance')
            @if (auth()->user()->canSeeSelfAttendance())
            <a href="{{ route('attendance.index') }}"
                class="sidebar-link {{ $isActive(['attendance.']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-fingerprint text-base"></i>
                <span>Chấm công</span>
            </a>
            @endif
        @endcan

        @can('view-own-attendance')
            @if (auth()->user()->canSeeSelfAttendance())
            <a href="{{ route('my-attendance-logs.index') }}"
                class="sidebar-link {{ $isActive(['my-attendance-logs']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-list-check text-base"></i>
                <span>Lịch sử chấm công</span>
            </a>
            @endif
        @endcan

        @can('view-attendance-locations')
            <a href="{{ route('attendance-locations.index') }}"
                class="sidebar-link {{ $isActive(['attendance-locations']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-geo-alt text-base"></i>
                <span>Điểm chấm công</span>
            </a>
        @endcan

        @can('view-attendance')
            <a href="{{ route('attendance-logs.index') }}"
                class="sidebar-link {{ $isActive(['attendance-logs']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-clipboard-check text-base"></i>
                <span>Báo cáo chấm công</span>
            </a>
        @endcan

        @can('view-attendance-alerts')
            <a href="{{ route('attendance-alerts.index') }}"
                class="sidebar-link {{ $isActive(['attendance-alerts']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-exclamation-triangle text-base"></i>
                <span class="flex-1">Cảnh báo Ca</span>
                @if ($alertCount > 0)
                    <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold rounded-full bg-amber-500 text-white leading-none">
                        {{ $alertCount > 99 ? '99+' : $alertCount }}
                    </span>
                @endif
            </a>
        @endcan

        @can('import-attendance')
            <a href="{{ route('attendance-import.index') }}"
                class="sidebar-link {{ $isActive(['attendance-import']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-file-earmark-arrow-up text-base"></i>
                <span>Import Chấm Công</span>
            </a>
        @endcan

        @if(\App\Models\Setting::getValue('timesheet_confirmation_enabled', '0') === '1')
            @can('view-own-timesheet-confirmation')
                @if (auth()->user()->canSeeSelfAttendance())
                <a href="{{ route('timesheet-confirmation.index') }}"
                    class="sidebar-link {{ $isActive(['timesheet-confirmation.']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                    <i class="bi bi-check2-square text-base"></i>
                    <span>Xác nhận công</span>
                </a>
                @endif
            @endcan
            @can('view-timesheet-confirmations')
                <a href="{{ route('timesheet-confirmations.index') }}"
                    class="sidebar-link {{ $isActive(['timesheet-confirmations.']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                    <i class="bi bi-clipboard2-check-fill text-base"></i>
                    <span>Xác nhận công (HR)</span>
                </a>
            @endcan
        @endif

        @canany(['view-staff-requests', 'view-leave-requests', 'view-shift-swaps'])
            <a href="{{ route('staff-requests.index') }}"
                class="sidebar-link {{ $isActive(['staff-requests', 'leave-requests', 'shift-swap-requests']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-clipboard2-check text-base"></i>
                <span>Đơn & Phê duyệt</span>
            </a>
        @endcanany

        @can('view-annual-leave')
            <a href="{{ route('annual-leave.index') }}"
                class="sidebar-link {{ $isActive(['annual-leave']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-calendar2-check text-base"></i>
                <span>Phép năm</span>
            </a>
        @endcan
        @endcanany

        {{-- ── THƯỞNG PHẠT ──────────────────────────── --}}
        <p
            class="px-3 pb-1.5 pt-4 text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
            Thưởng phạt</p>

        @can('view-penalties')
            <a href="{{ route('penalties.index') }}"
                class="sidebar-link {{ $isActive(['penalties']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-hammer text-base"></i>
                <span>Xử phạt</span>
            </a>
        @endcan

        @can('view-rewards')
            <a href="{{ route('rewards.index') }}"
                class="sidebar-link {{ $isActive(['rewards']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-gift text-base"></i>
                <span>Thưởng điểm</span>
            </a>
        @endcan

        @can('view-appeals')
            <a href="{{ route('appeals.index') }}"
                class="sidebar-link {{ $isActive(['appeals']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-chat-left-text text-base"></i>
                <span>Khiếu nại</span>
            </a>
        @endcan

        @can('view-reports')
            <a href="{{ route('reports.index') }}"
                class="sidebar-link {{ $isActive(['reports']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-flag text-base"></i>
                <span>Báo cáo vi phạm</span>
            </a>
        @endcan

        <a href="{{ route('rankings.index') }}"
            class="sidebar-link {{ $isActive(['rankings']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
            <i class="bi bi-trophy text-base"></i>
            <span>Bảng xếp hạng</span>
        </a>

        @can('view-redzone')
        <a href="{{ route('redzone.index') }}"
            class="sidebar-link {{ $isActive(['redzone']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
            <i class="bi bi-exclamation-octagon text-base"></i>
            <span class="flex-1">Redzone</span>
            @if ($redzoneCount > 0)
                <span
                    class="inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-red-500 text-white">{{ $redzoneCount }}</span>
            @endif
        </a>
        @endcan
        @canany(['view-violations', 'view-regulations', 'view-reward-types', 'view-reward-categories'])
            <p class="px-3 pb-1.5 pt-3 text-[10px] font-medium text-slate-300 dark:text-slate-600 uppercase tracking-wider">
                Danh mục</p>
        @endcanany
        @can('view-violations')
            <a href="{{ route('violations.index') }}"
                class="sidebar-link {{ $isActive(['violations']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-book text-base"></i>
                <span>Vi phạm</span>
            </a>
        @endcan
        @can('view-reward-types')
            <a href="{{ route('reward-types.index') }}"
                class="sidebar-link {{ $isActive(['reward-types']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-star text-base"></i>
                <span>Loại thưởng</span>
            </a>
        @endcan
        @can('view-regulations')
            <a href="{{ route('regulations.index') }}"
                class="sidebar-link {{ $isActive(['regulations']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-journal-check text-base"></i>
                <span>Danh sách quy chế</span>
            </a>
        @endcan
        @can('view-reward-categories')
            <a href="{{ route('reward-categories.index') }}"
                class="sidebar-link {{ $isActive(['reward-categories']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-folder-check text-base"></i>
                <span>Danh mục thưởng</span>
            </a>
        @endcan

        {{-- ── HỆ THỐNG ──────────────────────────────── --}}
        @canany(['manage-settings', 'view-activity-log'])
            <p
                class="px-3 pb-1.5 pt-4 text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                Hệ thống</p>
            @can('manage-settings')
                <a href="{{ route('settings.index') }}"
                    class="sidebar-link {{ $isActive(['settings']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                    <i class="bi bi-gear text-base"></i>
                    <span>Cài đặt</span>
                </a>
                <a href="{{ route('google-sheets.index') }}"
                    class="sidebar-link {{ $isActive(['google-sheets']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                    <i class="bi bi-file-earmark-spreadsheet text-base"></i>
                    <span>Google Sheets</span>
                </a>
                <a href="{{ route('themes.index') }}"
                    class="sidebar-link {{ $isActive(['themes']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                    <i class="bi bi-palette text-base"></i>
                    <span>Quản lý chủ đề</span>
                </a>
            @endcan

            @can('view-activity-log')
                <a href="{{ route('activity.log') }}"
                    class="sidebar-link {{ $isActive(['activity']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                    <i class="bi bi-clipboard-data text-base"></i>
                    <span>Nhật ký hoạt động</span>
                </a>
            @endcan
            @can('view-log-viewer')
                <a href="{{ route('log-viewer.index') }}"
                    class="sidebar-link {{ str_starts_with(request()->path(), 'log-viewer') ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                    <i class="bi bi-terminal text-base"></i>
                    <span>System Logs</span>
                </a>
            @endcan
        @endcanany

        {{-- ── NGƯỜI DÙNG ────────────────────────────── --}}
        @canany(['manage-users', 'manage-roles'])
            <p
                class="px-3 pb-1.5 pt-4 text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                Người dùng</p>

            @can('manage-users')
                <a href="{{ route('users.index') }}"
                    class="sidebar-link {{ $isActive(['users']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                    <i class="bi bi-people text-base"></i>
                    <span>Tài khoản</span>
                </a>
            @endcan

            @can('manage-roles')
                <a href="{{ route('roles.index') }}"
                    class="sidebar-link {{ $isActive(['roles']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                    <i class="bi bi-shield-lock text-base"></i>
                    <span>Vai trò & Quyền hạn</span>
                </a>
            @endcan
        @endcanany

        @if(session('impersonator_id'))
            <form action="{{ route('impersonate.leave') }}" method="POST" class="mt-2">
                @csrf @method('DELETE')
                <button type="submit"
                    class="sidebar-link sidebar-link-inactive w-full text-amber-600 dark:text-amber-400">
                    <i class="bi bi-box-arrow-left text-base"></i>
                    <span>Thoát đăng nhập hộ</span>
                </button>
            </form>
        @endif

    </nav>
</aside>

<!-- Mobile overlay -->
<div id="mobile-panel-overlay" class="hidden fixed inset-0 bg-black/40 z-40 lg:hidden" onclick="closeMobilePanel()">
</div>

<!-- Mobile slide-over panel -->
<aside id="mobile-panel"
    class="hidden fixed inset-y-0 left-0 w-64 bg-white dark:bg-slate-800 border-r border-slate-200 dark:border-slate-700 flex-col overflow-y-auto z-50 lg:hidden">

    <div class="px-4 pt-4 pb-3 border-b border-slate-100 dark:border-slate-700 shrink-0">
        <div class="flex items-center gap-3">
            <a href="{{ route('profile.show') }}" class="flex items-center gap-3 min-w-0 flex-1 group hover:opacity-80 transition-all" onclick="closeMobilePanel()">
                @if(auth()->user()->avatar)
                    <img src="{{ asset(auth()->user()->avatar) }}" alt="{{ auth()->user()->name }}"
                        class="w-9 h-9 rounded-xl object-cover shrink-0">
                @else
                    <div
                        class="w-9 h-9 rounded-xl bg-pcrm-100 dark:bg-pcrm-900/50 flex items-center justify-center text-pcrm-700 dark:text-pcrm-400 font-bold text-sm shrink-0">
                        {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                    </div>
                @endif
                <div class="min-w-0">
                    <p class="font-semibold text-slate-900 dark:text-white text-sm truncate group-hover:text-pcrm-600 transition-colors">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-slate-400 truncate">{{ auth()->user()->email }}</p>
                </div>
            </a>
            <button onclick="closeMobilePanel()"
                class="shrink-0 w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x text-base"></i>
            </button>
        </div>
    </div>

    <nav class="flex-1 px-3 py-3 space-y-0.5 overflow-y-auto">

        <p class="px-3 pb-1.5 pt-1 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Tổng quan</p>
        <a href="{{ route('dashboard') }}"
            class="sidebar-link {{ $isActive(['dashboard']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
            <i class="bi bi-speedometer2 text-base"></i><span>Bảng điều khiển</span>
        </a>

        <a href="{{ route('notifications.index') }}"
            class="sidebar-link {{ $isActive(['notifications']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
            <i class="bi bi-bell text-base"></i>
            <span class="flex-1">Thông báo</span>
            @if ($unreadNotifCount > 0)
                <span
                    class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold rounded-full bg-red-500 text-white leading-none">
                    {{ $unreadNotifCount > 99 ? '99+' : $unreadNotifCount }}
                </span>
            @endif
        </a>
         <a href="{{ route('policy.index') }}" target="_blank" rel="noopener"
            class="sidebar-link {{ $isActive(['policy']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
            <i class="bi bi-file-earmark-text text-base"></i>
            <span>Nội Quy Công Ty</span>
        </a>
        <p class="px-3 pb-1.5 pt-4 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Nhân sự</p>
        @can('view-employees')
            <a href="{{ route('employees.index') }}"
                class="sidebar-link {{ $isActive(['employees']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-people text-base"></i><span>Nhân viên</span>
            </a>
        @endcan
        @can('view-teams')
            <a href="{{ route('teams.index') }}"
                class="sidebar-link {{ $isActive(['teams']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-diagram-3 text-base"></i><span>Đội nhóm</span>
            </a>
        @endcan
        @can('view-branches')
            <a href="{{ route('branches.index') }}"
                class="sidebar-link {{ $isActive(['branches']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-building text-base"></i><span>Chi nhánh</span>
            </a>
        @endcan
        @can('view-positions')
            <a href="{{ route('positions.index') }}"
                class="sidebar-link {{ $isActive(['positions']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-person-badge text-base"></i><span>Chức danh</span>
            </a>
        @endcan

        @canany(['view-own-schedule', 'view-shifts', 'view-shift-schedules', 'view-holidays'])
        <p class="px-3 pb-1.5 pt-4 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Ca làm việc</p>
        @can('view-own-schedule')
            <a href="{{ route('my-schedule.index') }}"
                class="sidebar-link {{ $isActive(['my-schedule']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-calendar3 text-base"></i><span>Lịch làm việc</span>
            </a>
        @endcan
        @can('view-shifts')
            <a href="{{ route('shifts.index') }}"
                class="sidebar-link {{ $isActive(['shifts']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-clock-history text-base"></i><span>Mẫu ca làm việc</span>
            </a>
        @endcan
        @can('view-shift-schedules')
            <a href="{{ route('shift-schedules.index') }}"
                class="sidebar-link {{ $isActive(['shift-schedules']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-calendar-week text-base"></i><span>Xếp ca</span>
            </a>
        @endcan
        @can('view-shift-coverage')
            <a href="{{ route('operational-schedule.index') }}"
                class="sidebar-link {{ $isActive(['operational-schedule']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-calendar2-range text-base"></i><span>Lịch vận hành</span>
            </a>
            <a href="{{ route('shift-coverage-requirements.index') }}"
                class="sidebar-link {{ $isActive(['shift-coverage-requirements']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-people-fill text-base"></i><span>Định biên ca</span>
            </a>
        @endcan
        @can('view-holidays')
            <a href="{{ route('holidays.index') }}"
                class="sidebar-link {{ $isActive(['holidays']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-calendar-event text-base"></i><span>Ngày nghỉ lễ</span>
            </a>
        @endcan
        @endcanany

        @canany(['checkin-attendance', 'view-own-attendance', 'view-attendance-locations', 'view-attendance', 'import-attendance', 'view-staff-requests', 'view-leave-requests', 'view-shift-swaps', 'view-own-timesheet-confirmation', 'view-timesheet-confirmations'])
        <p class="px-3 pb-1.5 pt-4 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Chấm công</p>
        @can('checkin-attendance')
            @if (auth()->user()->canSeeSelfAttendance())
            <a href="{{ route('attendance.index') }}"
                class="sidebar-link {{ $isActive(['attendance.']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-fingerprint text-base"></i><span>Chấm công</span>
            </a>
            @endif
        @endcan
        @can('view-own-attendance')
            @if (auth()->user()->canSeeSelfAttendance())
            <a href="{{ route('my-attendance-logs.index') }}"
                class="sidebar-link {{ $isActive(['my-attendance-logs']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-list-check text-base"></i><span>Lịch sử chấm công</span>
            </a>
            @endif
        @endcan
        @can('view-attendance-locations')
            <a href="{{ route('attendance-locations.index') }}"
                class="sidebar-link {{ $isActive(['attendance-locations']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-geo-alt text-base"></i><span>Điểm chấm công</span>
            </a>
        @endcan
        @can('view-attendance')
            <a href="{{ route('attendance-logs.index') }}"
                class="sidebar-link {{ $isActive(['attendance-logs']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-clipboard-check text-base"></i><span>Báo cáo chấm công</span>
            </a>
        @endcan
        @can('view-attendance-alerts')
            <a href="{{ route('attendance-alerts.index') }}"
                class="sidebar-link {{ $isActive(['attendance-alerts']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-exclamation-triangle text-base"></i>
                <span class="flex-1">Cảnh báo Ca</span>
                @if ($alertCount > 0)
                    <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold rounded-full bg-amber-500 text-white leading-none">
                        {{ $alertCount > 99 ? '99+' : $alertCount }}
                    </span>
                @endif
            </a>
        @endcan
        @can('import-attendance')
            <a href="{{ route('attendance-import.index') }}"
                class="sidebar-link {{ $isActive(['attendance-import']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-file-earmark-arrow-up text-base"></i><span>Import Chấm Công</span>
            </a>
        @endcan
        @if(\App\Models\Setting::getValue('timesheet_confirmation_enabled', '0') === '1')
            @can('view-own-timesheet-confirmation')
                @if (auth()->user()->canSeeSelfAttendance())
                <a href="{{ route('timesheet-confirmation.index') }}"
                    class="sidebar-link {{ $isActive(['timesheet-confirmation.']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                    <i class="bi bi-check2-square text-base"></i><span>Xác nhận công</span>
                </a>
                @endif
            @endcan
            @can('view-timesheet-confirmations')
                <a href="{{ route('timesheet-confirmations.index') }}"
                    class="sidebar-link {{ $isActive(['timesheet-confirmations.']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                    <i class="bi bi-clipboard2-check-fill text-base"></i><span>Xác nhận công (HR)</span>
                </a>
            @endcan
        @endif
        @canany(['view-staff-requests', 'view-leave-requests', 'view-shift-swaps'])
            <a href="{{ route('staff-requests.index') }}"
                class="sidebar-link {{ $isActive(['staff-requests', 'leave-requests', 'shift-swap-requests']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-clipboard2-check text-base"></i><span>Đơn & Phê duyệt</span>
            </a>
        @endcanany
        @can('view-annual-leave')
            <a href="{{ route('annual-leave.index') }}"
                class="sidebar-link {{ $isActive(['annual-leave']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-calendar2-check text-base"></i><span>Phép năm</span>
            </a>
        @endcan
        @endcanany

        <p class="px-3 pb-1.5 pt-4 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Thưởng phạt</p>
        @can('view-penalties')
            <a href="{{ route('penalties.index') }}"
                class="sidebar-link {{ $isActive(['penalties']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-hammer text-base"></i><span>Xử phạt</span>
            </a>
        @endcan
        @can('view-rewards')
            <a href="{{ route('rewards.index') }}"
                class="sidebar-link {{ $isActive(['rewards']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-gift text-base"></i><span>Thưởng điểm</span>
            </a>
        @endcan
        @can('view-appeals')
            <a href="{{ route('appeals.index') }}"
                class="sidebar-link {{ $isActive(['appeals']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-chat-left-text text-base"></i><span>Khiếu nại</span>
            </a>
        @endcan
        @can('view-reports')
            <a href="{{ route('reports.index') }}"
                class="sidebar-link {{ $isActive(['reports']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-flag text-base"></i><span>Báo cáo vi phạm</span>
            </a>
        @endcan
        <a href="{{ route('rankings.index') }}"
            class="sidebar-link {{ $isActive(['rankings']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
            <i class="bi bi-trophy text-base"></i><span>Bảng xếp hạng</span>
        </a>
        @can('view-redzone')
        <a href="{{ route('redzone.index') }}"
            class="sidebar-link {{ $isActive(['redzone']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
            <i class="bi bi-exclamation-octagon text-base"></i>
            <span class="flex-1">Redzone</span>
            @if ($redzoneCount > 0)
                <span
                    class="inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-red-500 text-white">{{ $redzoneCount }}</span>
            @endif
        </a>
        @endcan
        @can('view-violations')
            <a href="{{ route('violations.index') }}"
                class="sidebar-link {{ $isActive(['violations']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-book text-base"></i><span>Vi phạm</span>
            </a>
        @endcan
        @can('view-regulations')
        <a href="{{ route('regulations.index') }}"
            class="sidebar-link {{ $isActive(['regulations']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
            <i class="bi bi-journal-check text-base"></i><span>Quy chế</span>
        </a>
        @endcan
        @can('view-reward-categories')
            <a href="{{ route('reward-categories.index') }}"
                class="sidebar-link {{ $isActive(['reward-categories']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-folder-check text-base"></i><span>Danh mục thưởng</span>
            </a>
        @endcan
        @can('view-reward-types')
            <a href="{{ route('reward-types.index') }}"
                class="sidebar-link {{ $isActive(['reward-types']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                <i class="bi bi-star text-base"></i><span>Loại thưởng</span>
            </a>
        @endcan

        @canany(['manage-settings'])
            <p class="px-3 pb-1.5 pt-4 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Hệ thống</p>
            @can('manage-settings')
                <a href="{{ route('settings.index') }}"
                    class="sidebar-link {{ $isActive(['settings']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                    <i class="bi bi-gear text-base"></i><span>Cài đặt</span>
                </a>
                <a href="{{ route('google-sheets.index') }}"
                    class="sidebar-link {{ $isActive(['google-sheets']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                    <i class="bi bi-file-earmark-spreadsheet text-base"></i><span>Google Sheets</span>
                </a>
                <a href="{{ route('themes.index') }}"
                    class="sidebar-link {{ $isActive(['themes']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                    <i class="bi bi-palette text-base"></i><span>Quản lý chủ đề</span>
                </a>
            @endcan
            @can('view-activity-log')
                <a href="{{ route('activity.log') }}"
                    class="sidebar-link {{ $isActive(['activity']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                    <i class="bi bi-clipboard-data text-base"></i><span>Nhật ký</span>
                </a>
            @endcan
            @can('view-log-viewer')
                <a href="/log-viewer"
                    class="sidebar-link {{ str_starts_with(request()->path(), 'log-viewer') ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                    <i class="bi bi-terminal text-base"></i><span>System Logs</span>
                </a>
            @endcan
        @endcanany
        @canany(['manage-users', 'manage-roles'])
            <p class="px-3 pb-1.5 pt-4 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Người dùng</p>
            @can('manage-users')
                <a href="{{ route('users.index') }}"
                    class="sidebar-link {{ $isActive(['users']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                    <i class="bi bi-people text-base"></i><span>Tài khoản</span>
                </a>
            @endcan
            @can('manage-roles')
                <a href="{{ route('roles.index') }}"
                    class="sidebar-link {{ $isActive(['roles']) ? 'sidebar-link-active' : 'sidebar-link-inactive' }}">
                    <i class="bi bi-shield-lock text-base"></i><span>Vai trò</span>
                </a>
            @endcan
        @endcanany

        @if(session('impersonator_id'))
            <form action="{{ route('impersonate.leave') }}" method="POST" class="mt-2">
                @csrf @method('DELETE')
                <button type="submit"
                    class="sidebar-link sidebar-link-inactive w-full text-amber-600 dark:text-amber-400">
                    <i class="bi bi-box-arrow-left text-base"></i><span>Thoát đăng nhập hộ</span>
                </button>
            </form>
        @endif

    </nav>
</aside>

<script>
    function toggleMobilePanel(e) {
        const panel = document.getElementById('mobile-panel');
        const overlay = document.getElementById('mobile-panel-overlay');
        const isHidden = panel.classList.contains('hidden');
        panel.classList.toggle('hidden', !isHidden);
        panel.classList.toggle('flex', isHidden);
        overlay.classList.toggle('hidden', !isHidden);
        const trigger = e && e.currentTarget;
        if (trigger) trigger.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
        document.body.classList.toggle('overflow-hidden', isHidden);
    }

    function closeMobilePanel() {
        const panel = document.getElementById('mobile-panel');
        const overlay = document.getElementById('mobile-panel-overlay');
        panel.classList.add('hidden');
        panel.classList.remove('flex');
        overlay.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    // ── Thu nhỏ sidebar (desktop only — state lưu ở localStorage) ──────────
    function toggleSidebar() {
        const collapsed = document.documentElement.classList.toggle('sidebar-collapsed');
        localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0');
    }

    // ── Tooltip nổi cao cấp khi thu nhỏ sidebar ─────────────────────────
    (function () {
        const tip = document.createElement('div');
        tip.id = 'sidebar-rail-tooltip';
        tip.className = 'fixed hidden z-[9999] pointer-events-none px-2.5 py-1.5 text-xs font-semibold text-white bg-slate-900 dark:bg-slate-700 rounded-lg shadow-xl border border-slate-700/60 dark:border-slate-600 transition-opacity duration-150 whitespace-nowrap';
        document.body.appendChild(tip);

        document.querySelectorAll('#left-panel .sidebar-link').forEach(function (link) {
            const labelEl = link.querySelector('span:not(.rounded-full)');
            const text = (labelEl ? labelEl.textContent : link.textContent).trim();
            if (text) {
                link.removeAttribute('title');
                link.addEventListener('mouseenter', function () {
                    if (!document.documentElement.classList.contains('sidebar-collapsed')) return;
                    const rect = link.getBoundingClientRect();
                    tip.textContent = text;
                    tip.style.top = (rect.top + rect.height / 2) + 'px';
                    tip.style.left = (rect.right + 10) + 'px';
                    tip.style.transform = 'translateY(-50%)';
                    tip.classList.remove('hidden');
                });
                link.addEventListener('mouseleave', function () {
                    tip.classList.add('hidden');
                });
            }
        });
    })();
</script>
