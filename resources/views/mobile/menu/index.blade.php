@extends('layouts.admin')

@section('title', 'Tất cả chức năng')
@section('breadcrumb', 'Menu')

@section('content')
<div class="relative pb-16">

    <div class="relative space-y-4 z-10">
        <!-- Thẻ hồ sơ -->
        @php
            $menuUser = auth()->user();
            $menuRole = $menuUser->hasRole('admin') ? 'Quản trị' : ($menuUser->hasRole('director') ? 'Giám đốc' : ($menuUser->hasRole('manager') ? 'Quản lý' : ($menuUser->hasRole('team-leader') ? 'Trưởng nhóm' : 'Nhân viên')));
            $menuBranch = $menuUser->employee?->branch?->name;
            $menuPosition = $menuUser->employee?->position?->name ?? $menuRole;
        @endphp
        <a href="{{ route('profile.show') }}" class="flex items-center gap-4 bg-white dark:bg-slate-900 rounded-2xl p-4 border border-blue-100 dark:border-slate-800 shadow-[0_4px_20px_rgba(37,99,235,0.08)] active:scale-[0.99] transition" aria-label="Xem hồ sơ {{ $menuUser->name }}">
            @if($menuUser->avatar)
                <img src="{{ asset($menuUser->avatar) }}" alt="" class="w-14 h-14 rounded-full object-cover shrink-0">
            @else
                <span class="w-16 h-16 rounded-full bg-blue-100 dark:bg-blue-950 text-blue-600 flex items-center justify-center shrink-0"><i class="bi bi-person-fill text-[34px]"></i></span>
            @endif
            <span class="min-w-0 flex-1">
                <span class="block text-lg font-extrabold text-[#0B1F5C] dark:text-white truncate">{{ $menuUser->name }}</span>
                <span class="block text-sm text-[#475569] dark:text-slate-400 truncate">{{ $menuPosition }}</span>
                @if ($menuBranch)<span class="block text-sm text-slate-500 dark:text-slate-400 truncate">{{ $menuBranch }}</span>@endif
            </span>
            <i class="bi bi-chevron-right text-slate-400"></i>
        </a>

        <h1 class="text-[24px] font-extrabold text-[#0B1F5C] dark:text-white tracking-tight px-1 pt-1">Tất cả chức năng</h1>

        {{-- Group 1: TỔNG QUAN --}}
        <div>
            <div class="px-1 pb-2">
                <span class="text-[13px] font-extrabold text-[#475569] dark:text-slate-400 uppercase tracking-wide">Tổng quan</span>
            </div>
            <div class="grid grid-cols-2 gap-2.5">
                <a href="{{ route('dashboard') }}" class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                            <i class="bi bi-speedometer2 text-[24px]"></i>
                        </div>
                        <span class="text-[13px] font-semibold text-[#0B1F5C] dark:text-slate-200 leading-tight">Bảng điều khiển</span>
                    </div>
                    <i class="bi bi-chevron-right text-xs text-slate-400 dark:text-slate-500"></i>
                </a>

                <a href="{{ route('notifications.index') }}" class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center relative">
                            <i class="bi bi-bell text-[24px]"></i>
                            @if ($unreadNotifCount > 0)
                                <span class="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-red-500"></span>
                            @endif
                        </div>
                        <span class="text-[13px] font-semibold text-[#0B1F5C] dark:text-slate-200 leading-tight">Thông báo</span>
                    </div>
                    <div class="flex items-center gap-2">
                        @if ($unreadNotifCount > 0)
                            <span class="inline-flex items-center justify-center px-1.5 py-0.5 text-[9px] font-bold rounded-full bg-red-500 text-white leading-none">
                                {{ $unreadNotifCount > 99 ? '99+' : $unreadNotifCount }}
                            </span>
                        @endif
                        <i class="bi bi-chevron-right text-xs text-slate-400 dark:text-slate-500"></i>
                    </div>
                </a>

                <a href="{{ route('policy.index') }}" target="_blank" rel="noopener" class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                            <i class="bi bi-file-earmark-text text-[24px]"></i>
                        </div>
                        <span class="text-[13px] font-semibold text-[#0B1F5C] dark:text-slate-200 leading-tight">Nội quy công ty</span>
                    </div>
                    <i class="bi bi-box-arrow-up-right text-xs text-slate-400 dark:text-slate-550"></i>
                </a>
            </div>
        </div>

        {{-- Group 3: CA LÀM VIỆC --}}
        @canany(['view-own-schedule', 'view-shifts', 'view-shift-schedules', 'view-holidays'])
        <div>
            <div class="px-1 pb-2">
                <span class="text-[13px] font-extrabold text-[#475569] dark:text-slate-400 uppercase tracking-wide">Ca làm việc</span>
            </div>
            <div class="grid grid-cols-2 gap-2.5">
                @can('view-own-schedule')
                    <a href="{{ route('my-schedule.index') }}" class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                <i class="bi bi-calendar3 text-[24px]"></i>
                            </div>
                            <span class="text-[13px] font-semibold text-[#0B1F5C] dark:text-slate-200 leading-tight">Lịch làm việc</span>
                        </div>
                        <i class="bi bi-chevron-right text-xs text-slate-400 dark:text-slate-500"></i>
                    </a>
                @endcan

                @can('view-shifts')
                    <a href="{{ route('shifts.index') }}" class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                <i class="bi bi-clock-history text-[24px]"></i>
                            </div>
                            <span class="text-[13px] font-semibold text-[#0B1F5C] dark:text-slate-200 leading-tight">Ca làm việc</span>
                        </div>
                        <i class="bi bi-chevron-right text-xs text-slate-400 dark:text-slate-500"></i>
                    </a>
                @endcan

                @can('view-shift-schedules')
                    <a href="{{ route('shift-schedules.index') }}" class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                <i class="bi bi-calendar-week text-[24px]"></i>
                            </div>
                            <span class="text-[13px] font-semibold text-[#0B1F5C] dark:text-slate-200 leading-tight">Xếp ca</span>
                        </div>
                        <i class="bi bi-chevron-right text-xs text-slate-400 dark:text-slate-500"></i>
                    </a>
                @endcan

                @can('view-holidays')
                    <a href="{{ route('holidays.index') }}" class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                <i class="bi bi-calendar-event text-[24px]"></i>
                            </div>
                            <span class="text-[13px] font-semibold text-[#0B1F5C] dark:text-slate-200 leading-tight">Ngày nghỉ lễ</span>
                        </div>
                        <i class="bi bi-chevron-right text-xs text-slate-400 dark:text-slate-500"></i>
                    </a>
                @endcan
            </div>
        </div>
        @endcanany

        {{-- Group 4: CHẤM CÔNG --}}
        @canany(['checkin-attendance', 'view-own-attendance', 'view-attendance-locations', 'view-attendance', 'import-attendance', 'view-own-timesheet-confirmation', 'view-timesheet-confirmations', 'view-staff-requests', 'view-leave-requests', 'view-shift-swaps', 'view-annual-leave'])
        <div>
            <div class="px-1 pb-2">
                <span class="text-[13px] font-extrabold text-[#475569] dark:text-slate-400 uppercase tracking-wide">Chấm công</span>
            </div>
            <div class="grid grid-cols-2 gap-2.5">
                @can('checkin-attendance')
                    @if (auth()->user()->canSeeSelfAttendance())
                        <a href="{{ route('attendance.index') }}" class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                    <i class="bi bi-fingerprint text-[24px]"></i>
                                </div>
                                <span class="text-[13px] font-semibold text-[#0B1F5C] dark:text-slate-200 leading-tight">Chấm công</span>
                            </div>
                            <i class="bi bi-chevron-right text-xs text-slate-400 dark:text-slate-500"></i>
                        </a>
                    @endif
                @endcan

                @can('view-own-attendance')
                    @if (auth()->user()->canSeeSelfAttendance())
                        <a href="{{ route('my-attendance-logs.index') }}" class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                    <i class="bi bi-list-check text-[24px]"></i>
                                </div>
                                <span class="text-[13px] font-semibold text-[#0B1F5C] dark:text-slate-200 leading-tight">Lịch sử chấm công</span>
                            </div>
                            <i class="bi bi-chevron-right text-xs text-slate-400 dark:text-slate-500"></i>
                        </a>
                    @endif
                @endcan

                @can('view-attendance-locations')
                    <a href="{{ route('attendance-locations.index') }}" class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                <i class="bi bi-geo-alt text-[24px]"></i>
                            </div>
                            <span class="text-[13px] font-semibold text-[#0B1F5C] dark:text-slate-200 leading-tight">Điểm chấm công</span>
                        </div>
                        <i class="bi bi-chevron-right text-xs text-slate-400 dark:text-slate-500"></i>
                    </a>
                @endcan

                @can('view-attendance')
                    <a href="{{ route('attendance-logs.index') }}" class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                <i class="bi bi-clipboard-check text-[24px]"></i>
                            </div>
                            <span class="text-[13px] font-semibold text-[#0B1F5C] dark:text-slate-200 leading-tight">Báo cáo chấm công</span>
                        </div>
                        <i class="bi bi-chevron-right text-xs text-slate-400 dark:text-slate-500"></i>
                    </a>
                @endcan

                @can('import-attendance')
                    <a href="{{ route('attendance-import.index') }}" class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                <i class="bi bi-file-earmark-arrow-up text-[24px]"></i>
                            </div>
                            <span class="text-[13px] font-semibold text-[#0B1F5C] dark:text-slate-200 leading-tight">Import Chấm Công</span>
                        </div>
                        <i class="bi bi-chevron-right text-xs text-slate-400 dark:text-slate-550"></i>
                    </a>
                @endcan

                @if(\App\Models\Setting::getValue('timesheet_confirmation_enabled', '0') === '1')
                    @can('view-own-timesheet-confirmation')
                        @if (auth()->user()->canSeeSelfAttendance())
                            <a href="{{ route('timesheet-confirmation.index') }}" class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                                <div class="flex items-center gap-2 min-w-0">
                                    <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                        <i class="bi bi-check2-square text-[24px]"></i>
                                    </div>
                                    <span class="text-[13px] font-semibold text-[#0B1F5C] dark:text-slate-200 leading-tight">Xác nhận công</span>
                                </div>
                                <i class="bi bi-chevron-right text-xs text-slate-400 dark:text-slate-500"></i>
                            </a>
                        @endif
                    @endcan

                    @can('view-timesheet-confirmations')
                        <a href="{{ route('timesheet-confirmations.index') }}" class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                    <i class="bi bi-clipboard2-check-fill text-[24px]"></i>
                                </div>
                                <span class="text-[13px] font-semibold text-[#0B1F5C] dark:text-slate-200 leading-tight">Xác nhận công (HR)</span>
                            </div>
                            <i class="bi bi-chevron-right text-xs text-slate-400 dark:text-slate-500"></i>
                        </a>
                    @endcan
                @endif

                @canany(['view-staff-requests', 'view-leave-requests', 'view-shift-swaps'])
                    <a href="{{ route('staff-requests.index') }}" class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                <i class="bi bi-clipboard2-check text-[24px]"></i>
                            </div>
                            <span class="text-[13px] font-semibold text-[#0B1F5C] dark:text-slate-200 leading-tight">Yêu cầu & Phê duyệt</span>
                        </div>
                        <i class="bi bi-chevron-right text-xs text-slate-400 dark:text-slate-500"></i>
                    </a>
                @endcanany

                @can('view-annual-leave')
                    <a href="{{ route('annual-leave.index') }}" class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                <i class="bi bi-calendar2-check text-[24px]"></i>
                            </div>
                            <span class="text-[13px] font-semibold text-[#0B1F5C] dark:text-slate-200 leading-tight">Phép năm</span>
                        </div>
                        <i class="bi bi-chevron-right text-xs text-slate-400 dark:text-slate-500"></i>
                    </a>
                @endcan
            </div>
        </div>
        @endcanany

        {{-- Group 2: NHÂN SỰ --}}
        @canany(['view-employees', 'view-teams', 'view-branches'])
        <div>
            <div class="px-1 pb-2">
                <span class="text-[13px] font-extrabold text-[#475569] dark:text-slate-400 uppercase tracking-wide">Nhân sự</span>
            </div>
            <div class="grid grid-cols-2 gap-2.5">
                @can('view-employees')
                    <a href="{{ route('employees.index') }}" class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                <i class="bi bi-people text-[24px]"></i>
                            </div>
                            <span class="text-[13px] font-semibold text-[#0B1F5C] dark:text-slate-200 leading-tight">Nhân viên</span>
                        </div>
                        <i class="bi bi-chevron-right text-xs text-slate-400 dark:text-slate-550"></i>
                    </a>
                @endcan

                @can('view-teams')
                    <a href="{{ route('teams.index') }}" class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                <i class="bi bi-diagram-3 text-[24px]"></i>
                            </div>
                            <span class="text-[13px] font-semibold text-[#0B1F5C] dark:text-slate-200 leading-tight">Đội nhóm</span>
                        </div>
                        <i class="bi bi-chevron-right text-xs text-slate-400 dark:text-slate-500"></i>
                    </a>
                @endcan

                @can('view-branches')
                    <a href="{{ route('branches.index') }}" class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                <i class="bi bi-building text-[24px]"></i>
                            </div>
                            <span class="text-[13px] font-semibold text-[#0B1F5C] dark:text-slate-200 leading-tight">Chi nhánh</span>
                        </div>
                        <i class="bi bi-chevron-right text-xs text-slate-400 dark:text-slate-500"></i>
                    </a>
                @endcan
            </div>
        </div>
        @endcanany

        {{-- Group 5: THƯỞNG PHẠT --}}
        <div>
            <div class="px-1 pb-2">
                <span class="text-[13px] font-extrabold text-[#475569] dark:text-slate-400 uppercase tracking-wide">Thưởng phạt</span>
            </div>
            <div class="grid grid-cols-2 gap-2.5">
                @can('view-penalties')
                    <a href="{{ route('penalties.index') }}" class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                <i class="bi bi-hammer text-[24px]"></i>
                            </div>
                            <span class="text-[13px] font-semibold text-[#0B1F5C] dark:text-slate-200 leading-tight">Xử phạt</span>
                        </div>
                        <i class="bi bi-chevron-right text-xs text-slate-400 dark:text-slate-500"></i>
                    </a>
                @endcan

                @can('view-rewards')
                    <a href="{{ route('rewards.index') }}" class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                <i class="bi bi-gift text-[24px]"></i>
                            </div>
                            <span class="text-[13px] font-semibold text-[#0B1F5C] dark:text-slate-200 leading-tight">Thưởng điểm</span>
                        </div>
                        <i class="bi bi-chevron-right text-xs text-slate-400 dark:text-slate-500"></i>
                    </a>
                @endcan

                @can('view-appeals')
                    <a href="{{ route('appeals.index') }}" class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                <i class="bi bi-chat-left-text text-[24px]"></i>
                            </div>
                            <span class="text-[13px] font-semibold text-[#0B1F5C] dark:text-slate-200 leading-tight">Khiếu nại</span>
                        </div>
                        <i class="bi bi-chevron-right text-xs text-slate-400 dark:text-slate-500"></i>
                    </a>
                @endcan

                @can('view-reports')
                    <a href="{{ route('reports.index') }}" class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                <i class="bi bi-flag text-[24px]"></i>
                            </div>
                            <span class="text-[13px] font-semibold text-[#0B1F5C] dark:text-slate-200 leading-tight">Báo cáo vi phạm</span>
                        </div>
                        <i class="bi bi-chevron-right text-xs text-slate-400 dark:text-slate-500"></i>
                    </a>
                @endcan

                <a href="{{ route('rankings.index') }}" class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                            <i class="bi bi-trophy text-[24px]"></i>
                        </div>
                        <span class="text-[13px] font-semibold text-[#0B1F5C] dark:text-slate-200 leading-tight">Bảng xếp hạng</span>
                    </div>
                    <i class="bi bi-chevron-right text-xs text-slate-400 dark:text-slate-550"></i>
                </a>

                @can('view-redzone')
                <a href="{{ route('redzone.index') }}" class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                            <i class="bi bi-exclamation-octagon text-[24px]"></i>
                        </div>
                        <span class="text-[13px] font-semibold text-[#0B1F5C] dark:text-slate-200 leading-tight">Redzone</span>
                    </div>
                    <div class="flex items-center gap-2">
                        @if ($redzoneCount > 0)
                            <span class="inline-flex items-center justify-center px-1.5 py-0.5 text-[9px] font-bold rounded-full bg-red-500 text-white leading-none">
                                {{ $redzoneCount }}
                            </span>
                        @endif
                        <i class="bi bi-chevron-right text-xs text-slate-400 dark:text-slate-500"></i>
                    </div>
                </a>
                @endcan
            </div>
        </div>

        {{-- Group 6: DANH MỤC --}}
        @canany(['view-violations', 'view-regulations', 'view-reward-categories', 'view-reward-types'])
        <div>
            <div class="px-1 pb-2">
                <span class="text-[13px] font-extrabold text-[#475569] dark:text-slate-400 uppercase tracking-wide">Danh mục</span>
            </div>
            <div class="grid grid-cols-2 gap-2.5">
                @can('view-violations')
                    <a href="{{ route('violations.index') }}" class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                <i class="bi bi-book text-[24px]"></i>
                            </div>
                            <span class="text-[13px] font-semibold text-[#0B1F5C] dark:text-slate-200 leading-tight">Vi phạm</span>
                        </div>
                        <i class="bi bi-chevron-right text-xs text-slate-400 dark:text-slate-500"></i>
                    </a>
                @endcan

                @can('view-regulations')
                    <a href="{{ route('regulations.index') }}" class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                <i class="bi bi-journal-check text-[24px]"></i>
                            </div>
                            <span class="text-[13px] font-semibold text-[#0B1F5C] dark:text-slate-200 leading-tight">Quy chế</span>
                        </div>
                        <i class="bi bi-chevron-right text-xs text-slate-400 dark:text-slate-500"></i>
                    </a>
                @endcan

                @can('view-reward-categories')
                    <a href="{{ route('reward-categories.index') }}" class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                <i class="bi bi-folder-check text-[24px]"></i>
                            </div>
                            <span class="text-[13px] font-semibold text-[#0B1F5C] dark:text-slate-200 leading-tight">Danh mục thưởng</span>
                        </div>
                        <i class="bi bi-chevron-right text-xs text-slate-400 dark:text-slate-500"></i>
                    </a>
                @endcan

                @can('view-reward-types')
                    <a href="{{ route('reward-types.index') }}" class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                <i class="bi bi-star text-[24px]"></i>
                            </div>
                            <span class="text-[13px] font-semibold text-[#0B1F5C] dark:text-slate-200 leading-tight">Loại thưởng</span>
                        </div>
                        <i class="bi bi-chevron-right text-xs text-slate-400 dark:text-slate-500"></i>
                    </a>
                @endcan
            </div>
        </div>
        @endcanany

        {{-- Group 7: HỆ THỐNG --}}
        @canany(['manage-settings', 'view-activity-log', 'view-log-viewer'])
        <div>
            <div class="px-1 pb-2">
                <span class="text-[10px] font-extrabold text-slate-400 dark:text-slate-550 uppercase tracking-wider">Hệ thống</span>
            </div>
            <div class="grid grid-cols-2 gap-2.5">
                @can('manage-settings')
                    <a href="{{ route('settings.index') }}" class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                <i class="bi bi-gear text-[24px]"></i>
                            </div>
                            <span class="text-[13px] font-semibold text-[#0B1F5C] dark:text-slate-200 leading-tight">Cài đặt hệ thống</span>
                        </div>
                        <i class="bi bi-chevron-right text-xs text-slate-400 dark:text-slate-500"></i>
                    </a>

                    <a href="{{ route('google-sheets.index') }}" class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                <i class="bi bi-file-earmark-spreadsheet text-[24px]"></i>
                            </div>
                            <span class="text-[13px] font-semibold text-[#0B1F5C] dark:text-slate-200 leading-tight">Google Sheets</span>
                        </div>
                        <i class="bi bi-chevron-right text-xs text-slate-400 dark:text-slate-500"></i>
                    </a>
                @endcan

                @can('view-activity-log')
                    <a href="{{ route('activity.log') }}" class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                <i class="bi bi-clipboard-data text-[24px]"></i>
                            </div>
                            <span class="text-[13px] font-semibold text-[#0B1F5C] dark:text-slate-200 leading-tight">Nhật ký hoạt động</span>
                        </div>
                        <i class="bi bi-chevron-right text-xs text-slate-400 dark:text-slate-500"></i>
                    </a>
                @endcan

                @can('view-log-viewer')
                    <a href="/log-viewer" class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                <i class="bi bi-terminal text-[24px]"></i>
                            </div>
                            <span class="text-[13px] font-semibold text-[#0B1F5C] dark:text-slate-200 leading-tight">System Logs</span>
                        </div>
                        <i class="bi bi-chevron-right text-xs text-slate-400 dark:text-slate-500"></i>
                    </a>
                @endcan
            </div>
        </div>
        @endcanany

        {{-- Group 8: NGƯỜI DÙNG --}}
        @canany(['manage-users', 'manage-roles'])
        <div>
            <div class="px-1 pb-2">
                <span class="text-[13px] font-extrabold text-[#475569] dark:text-slate-400 uppercase tracking-wide">Người dùng</span>
            </div>
            <div class="grid grid-cols-2 gap-2.5">
                @can('manage-users')
                    <a href="{{ route('users.index') }}" class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                <i class="bi bi-people text-[24px]"></i>
                            </div>
                            <span class="text-[13px] font-semibold text-[#0B1F5C] dark:text-slate-200 leading-tight">Tài khoản</span>
                        </div>
                        <i class="bi bi-chevron-right text-xs text-slate-400 dark:text-slate-500"></i>
                    </a>
                @endcan

                @can('manage-roles')
                    <a href="{{ route('roles.index') }}" class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                <i class="bi bi-shield-lock text-[24px]"></i>
                            </div>
                            <span class="text-[13px] font-semibold text-[#0B1F5C] dark:text-slate-200 leading-tight">Vai trò & Quyền hạn</span>
                        </div>
                        <i class="bi bi-chevron-right text-xs text-slate-400 dark:text-slate-500"></i>
                    </a>
                @endcan
            </div>
        </div>
        @endcanany
        
        {{-- Group: QUYỀN TRUY CẬP THIẾT BỊ --}}
        <div>
            <div class="px-1 pb-2">
                <span class="text-[13px] font-extrabold text-[#475569] dark:text-slate-400 uppercase tracking-wide">Quyền truy cập</span>
            </div>
            <div class="grid grid-cols-1 gap-2.5">
                <div class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                            <i class="bi bi-bell text-[24px]"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="text-sm font-semibold text-slate-700 dark:text-slate-200 block truncate">Thông báo đẩy</span>
                            <span id="pushPermissionStatus" class="text-[11px] text-slate-400 dark:text-slate-500">Đang kiểm tra...</span>
                        </div>
                    </div>
                    <button type="button" id="pushPermissionToggle" onclick="togglePushPermission()"
                        class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors bg-slate-300 dark:bg-slate-700"
                        role="switch" aria-checked="false" title="Bật thông báo đẩy trình duyệt">
                        <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform translate-x-1"></span>
                    </button>
                </div>

                <div class="flex items-center justify-between gap-2 px-3 py-3 min-h-[60px] bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_2px_12px_rgba(37,99,235,0.06)] active:scale-[0.98] transition">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-8 h-8 shrink-0 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                            <i class="bi bi-geo-alt text-[24px]"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="text-sm font-semibold text-slate-700 dark:text-slate-200 block truncate">Định vị GPS</span>
                            <span id="geoPermissionStatus" class="text-[11px] text-slate-400 dark:text-slate-500">Đang kiểm tra...</span>
                        </div>
                    </div>
                    <button type="button" id="geoPermissionToggle" onclick="toggleGeoPermission()"
                        class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors bg-slate-300 dark:bg-slate-700"
                        role="switch" aria-checked="false" title="Bật định vị GPS trình duyệt">
                        <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform translate-x-1"></span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Impersonate leave option --}}
        @if(session('impersonator_id'))
            <form action="{{ route('impersonate.leave') }}" method="POST" class="mt-4 px-2">
                @csrf @method('DELETE')
                <button type="submit" class="w-full flex items-center justify-center gap-2 py-3 px-4 rounded-2xl bg-amber-500 text-white font-bold text-sm shadow-md hover:bg-amber-600 active:scale-[0.98] transition">
                    <i class="bi bi-box-arrow-left text-lg"></i>
                    <span>Thoát đăng nhập hộ</span>
                </button>
            </form>
        @endif

        {{-- Logout Section --}}
        <div class="pt-2 px-1">
            <button type="button" onclick="openModal('logoutConfirmModal')" 
                    class="w-full flex items-center justify-center gap-2 py-3.5 px-4 rounded-2xl bg-red-600 hover:bg-red-700 text-white font-extrabold text-sm border-0 active:scale-[0.98] transition shadow-sm">
                <i class="bi bi-box-arrow-right text-lg"></i>
                <span>Đăng xuất</span>
            </button>
        </div>
    </div>
</div>

<!-- Logout Confirmation Modal -->
<div id="logoutConfirmModal" class="hidden fixed inset-0 bg-black/50 z-[130] flex items-center justify-center p-4"
     onclick="if(event.target===this) closeModal('logoutConfirmModal')">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-sm overflow-hidden p-6 space-y-4 pcrm-animate-in">
        <div class="text-center space-y-2">
            <div class="w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 flex items-center justify-center mx-auto text-xl mb-1">
                <i class="bi bi-box-arrow-right"></i>
            </div>
            <h3 class="text-base font-extrabold text-slate-900 dark:text-white">Xác nhận đăng xuất?</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Bạn có chắc chắn muốn đăng xuất khỏi tài khoản hiện tại không?</p>
        </div>
        <div class="grid grid-cols-2 gap-3 pt-2">
            <button type="button" onclick="closeModal('logoutConfirmModal')" 
                    class="btn-secondary py-2.5 text-xs font-bold rounded-xl justify-center">
                Huỷ bỏ
            </button>
            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <button type="submit" 
                        class="w-full bg-red-600 hover:bg-red-700 text-white py-2.5 text-xs font-bold rounded-xl flex items-center justify-center gap-1.5 shadow-sm active:scale-95 transition">
                    Đăng xuất
                </button>
            </form>
        </div>
    </div>
</div>
</div>

@push('scripts')
<script>
function setPermissionToggleUI(btnId, granted) {
    const btn = document.getElementById(btnId);
    if (!btn) return;
    const knob = btn.querySelector('span');
    btn.setAttribute('aria-checked', granted ? 'true' : 'false');
    btn.classList.toggle('bg-emerald-500', granted);
    btn.classList.toggle('bg-slate-300', !granted);
    btn.classList.toggle('dark:bg-slate-700', !granted);
    knob.classList.toggle('translate-x-5', granted);
    knob.classList.toggle('translate-x-1', !granted);
}

function refreshPushPermissionUI() {
    const statusEl = document.getElementById('pushPermissionStatus');
    if (!('Notification' in window)) {
        if (statusEl) statusEl.textContent = 'Trình duyệt không hỗ trợ';
        setPermissionToggleUI('pushPermissionToggle', false);
        return;
    }
    const granted = Notification.permission === 'granted';
    setPermissionToggleUI('pushPermissionToggle', granted);
    if (statusEl) {
        statusEl.textContent = granted ? 'Đã bật' : (Notification.permission === 'denied' ? 'Đã bị chặn' : 'Đang tắt');
    }
}

function togglePushPermission() {
    if (!('Notification' in window)) {
        alert('Trình duyệt của bạn không hỗ trợ thông báo đẩy.');
        return;
    }
    if (Notification.permission === 'granted') {
        alert('Thông báo đã được bật cho trình duyệt này. Để tắt, vào cài đặt trình duyệt (biểu tượng khoá cạnh URL) → Quyền → Thông báo.');
        return;
    }
    if (typeof window.enablePushNotifications === 'function') {
        Promise.resolve(window.enablePushNotifications()).finally(refreshPushPermissionUI);
    }
}

function refreshGeoPermissionUI() {
    const statusEl = document.getElementById('geoPermissionStatus');
    if (!('geolocation' in navigator)) {
        if (statusEl) statusEl.textContent = 'Trình duyệt không hỗ trợ';
        setPermissionToggleUI('geoPermissionToggle', false);
        return;
    }
    if (navigator.permissions && navigator.permissions.query) {
        navigator.permissions.query({ name: 'geolocation' }).then((status) => {
            const granted = status.state === 'granted';
            setPermissionToggleUI('geoPermissionToggle', granted);
            if (statusEl) {
                statusEl.textContent = granted ? 'Đã bật' : (status.state === 'denied' ? 'Đã bị chặn' : 'Đang tắt');
            }
            status.onchange = refreshGeoPermissionUI;
        }).catch(() => {
            if (statusEl) statusEl.textContent = 'Đang tắt';
        });
    } else if (statusEl) {
        statusEl.textContent = 'Đang tắt';
    }
}

function requestGeoPermission() {
    navigator.geolocation.getCurrentPosition(
        () => refreshGeoPermissionUI(),
        (err) => {
            refreshGeoPermissionUI();
            if (err.code === err.PERMISSION_DENIED) {
                alert('Bạn đã từ chối quyền định vị. Vào cài đặt trình duyệt (biểu tượng khoá cạnh URL) để cấp lại quyền, sau đó thử lại.');
            }
        },
        { enableHighAccuracy: true, timeout: 10000 }
    );
}

function toggleGeoPermission() {
    if (!('geolocation' in navigator)) {
        alert('Trình duyệt của bạn không hỗ trợ định vị GPS.');
        return;
    }
    if (navigator.permissions && navigator.permissions.query) {
        navigator.permissions.query({ name: 'geolocation' }).then((status) => {
            if (status.state === 'granted') {
                alert('Định vị đã được bật cho trình duyệt này. Để tắt, vào cài đặt trình duyệt (biểu tượng khoá cạnh URL) → Quyền → Vị trí.');
                return;
            }
            requestGeoPermission();
        }).catch(requestGeoPermission);
    } else {
        requestGeoPermission();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    refreshPushPermissionUI();
    refreshGeoPermissionUI();
});
</script>
@endpush
@endsection
