@extends('layouts.admin')

@section('title', 'Lịch làm việc')
@section('page-title', 'Lịch làm việc')
@section('page-subtitle', 'Lịch ca làm việc của bạn theo tuần')
@section('breadcrumb', 'Ca làm việc & Chấm công')

@push('styles')
<style>
    /* Desktop Calendar Grid styling */
    #workCalendar { --fc-border-color: var(--color-slate-200); --fc-page-bg-color: transparent; }
    .dark #workCalendar { --fc-border-color: var(--color-slate-800); }

    #workCalendar .fc-toolbar { margin-bottom: 1.25rem !important; }
    #workCalendar .fc-toolbar-title { font-size: 1rem; font-weight: 800; color: var(--color-slate-900); letter-spacing: -0.02em; text-transform: uppercase; }
    .dark #workCalendar .fc-toolbar-title { color: var(--color-white); }
    
    #workCalendar .fc-button {
        background: var(--color-white); border: 1px solid var(--color-slate-200); color: var(--color-slate-700);
        box-shadow: none !important; text-transform: none; font-weight: 700; padding: 0.3rem 0.9rem; font-size: 0.75rem;
        border-radius: 0.5rem !important; transition: all .2s ease;
    }
    .dark #workCalendar .fc-button { background: var(--color-slate-800); border-color: var(--color-slate-700); color: var(--color-slate-300); }
    #workCalendar .fc-button:hover { background: var(--color-slate-50); border-color: var(--color-slate-350); color: var(--color-slate-900); }
    .dark #workCalendar .fc-button:hover { background: var(--color-slate-700); border-color: var(--color-slate-600); color: var(--color-white); }
    #workCalendar .fc-button-active { background: var(--color-pcrm-600) !important; border-color: var(--color-pcrm-600) !important; color: var(--color-white) !important; }
    #workCalendar .fc-today-button:disabled { opacity: 0.5; }
    #workCalendar .fc-button-group { gap: 0.375rem; }
    #workCalendar .fc-button-group .fc-button { border-radius: 0.5rem !important; margin: 0 !important; }

    #workCalendar .fc-scrollgrid { border-radius: 1rem; overflow: hidden; border-color: var(--fc-border-color) !important; border: 1px solid var(--fc-border-color) !important; }
    #workCalendar .fc-col-header-cell { background: rgba(248, 250, 252, 0.8); padding: 0; }
    .dark #workCalendar .fc-col-header-cell { background: rgba(255, 255, 255, 0.02); }
    #workCalendar .fc-col-header-cell.fc-day-sat,
    #workCalendar .fc-col-header-cell.fc-day-sun { background: rgba(99, 102, 241, 0.04); }
    .dark #workCalendar .fc-col-header-cell.fc-day-sat,
    .dark #workCalendar .fc-col-header-cell.fc-day-sun { background: rgba(99, 102, 241, 0.08); }
    
    #workCalendar .fc-col-header-cell-cushion,
    #workCalendar .fc-daygrid-day-number { color: var(--color-slate-600); font-size: 0.75rem; text-decoration: none; font-weight: 700; }
    .dark #workCalendar .fc-col-header-cell-cushion,
    .dark #workCalendar .fc-daygrid-day-number { color: var(--color-slate-350); }
    
    #workCalendar .fc-daygrid-day-number { padding: 0rem 0.5rem; font-weight: 700; }
    #workCalendar .fc-day-sat .fc-daygrid-day-frame,
    #workCalendar .fc-day-sun .fc-daygrid-day-frame { background: rgba(241, 245, 249, 0.35); }
    .dark #workCalendar .fc-day-sat .fc-daygrid-day-frame,
    .dark #workCalendar .fc-day-sun .fc-daygrid-day-frame { background: rgba(255, 255, 255, 0.01); }
    #workCalendar .fc-day-today { background: rgba(99, 102, 241, 0.04) !important; }
    .dark #workCalendar .fc-day-today { background: rgba(99, 102, 241, 0.09) !important; }
    #workCalendar .fc-day-today .fc-daygrid-day-number {
        background: var(--color-pcrm-600); color: var(--color-white); border-radius: 9999px;
        width: 1.5rem; height: 1.5rem; display: inline-flex; align-items: center; justify-content: center;
        padding: 0; margin: 0.25rem 0.375rem 0 0; font-size: 0.7rem;
    }
    #workCalendar .fc-daygrid-day-frame { min-height: 120px; padding: 2px; }
    #workCalendar .fc-daygrid-day-events { margin-top: 3px; }
    #workCalendar .fc-daygrid-event-harness + .fc-daygrid-event-harness { margin-top: 4px; }

    /* Custom styles for FullCalendar list view on desktop */
    #workCalendar .fc-list {
        background: var(--color-white) !important;
        border: 1px solid var(--color-slate-200) !important;
        border-radius: 1rem !important;
        overflow: hidden;
    }
    .dark #workCalendar .fc-list {
        background: var(--color-slate-900) !important;
        border-color: var(--color-slate-800) !important;
    }
    #workCalendar .fc-list-day-cushion {
        background: var(--color-slate-50) !important;
        padding: 12px 18px !important;
    }
    .dark #workCalendar .fc-list-day-cushion {
        background: rgba(255, 255, 255, 0.02) !important;
    }
    #workCalendar .fc-list-day-text {
        font-size: 0.85rem !important;
        font-weight: 800 !important;
        color: var(--color-slate-800) !important;
        text-decoration: none !important;
    }
    .dark #workCalendar .fc-list-day-text {
        color: var(--color-white) !important;
    }
    #workCalendar .fc-list-day-side-text {
        font-size: 0.85rem !important;
        font-weight: 700 !important;
        color: var(--color-slate-500) !important;
    }
    #workCalendar .fc-list-event td {
        padding: 10px 16px !important;
    }
    #workCalendar .fc-list-event-title {
        font-weight: 700 !important;
        font-size: 0.85rem !important;
    }

    /* Event block card styling on desktop calendar grid */
    #workCalendar .fc-daygrid-event {
        background: var(--color-white) !important;
        border: 1px solid var(--color-slate-200) !important;
        border-left: 4px solid var(--fc-event-border-color, var(--color-slate-350)) !important;
        color: var(--color-slate-850) !important;
        border-radius: 0.5rem !important;
        padding: 2px 8px !important;
        margin: 2px 4px !important;
        box-shadow: 0 1px 2px rgb(15 23 42 / 0.04) !important;
        transition: all .2s ease !important;
    }
    .dark #workCalendar .fc-daygrid-event {
        background: var(--color-slate-900) !important;
        border-color: var(--color-slate-800) !important;
        color: var(--color-slate-300) !important;
    }
    #workCalendar .fc-daygrid-event:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgb(15 23 42 / 0.08) !important;
    }
    #workCalendar .fc-event-past { opacity: 0.65; }
    #workCalendar .fc-event-past:hover { opacity: 1; }

    .wc-event-chip { line-height: 1.25; }
</style>
@endpush

@section('content')
    <div class="relative min-h-screen pb-12">
        <div class="space-y-3">
            <!-- Solid Employee Header Card -->
            {{-- <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-3 flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-gradient-to-tr from-pcrm-500 to-indigo-650 flex items-center justify-center flex-shrink-0 text-lg font-black text-white shadow-sm">
                    {{ Str::of(Str::of($employee->name)->explode(' ')->last())->substr(0, 1)->upper() }}
                </div>
                <div class="min-w-0">
                    <h2 class="text-base font-bold text-slate-800 dark:text-slate-100 leading-tight">{{ $employee->name }}</h2>
                </div>
            </div> --}}

            @if ($isMobileDevice)
                {{-- MOBILE VIEW: Custom Card-Based Timeline List View --}}
                @php
                    $today = now()->toDateString();
                @endphp
                
                <!-- Month Navigator Chevrons -->
                <div class="flex items-center justify-between bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-3">
                    <a href="{{ route('my-schedule.index', ['month' => $prevDate->month, 'year' => $prevDate->year]) }}" class="w-10 h-10 flex items-center justify-center rounded-xl bg-slate-50 dark:bg-slate-850 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200/30 dark:border-slate-700/30 transition-all">
                        <i class="bi bi-chevron-left text-slate-600 dark:text-slate-355"></i>
                    </a>
                    <div class="text-center">
                        <h3 class="text-sm font-black text-slate-800 dark:text-slate-100 uppercase tracking-wide">Tháng {{ $month }} / {{ $year }}</h3>
                        <p class="text-[10px] text-slate-400 dark:text-slate-500 font-bold mt-0.5">Lịch làm việc & Ca chấm công</p>
                    </div>
                    <a href="{{ route('my-schedule.index', ['month' => $nextDate->month, 'year' => $nextDate->year]) }}" class="w-10 h-10 flex items-center justify-center rounded-xl bg-slate-50 dark:bg-slate-850 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200/30 dark:border-slate-700/30 transition-all">
                        <i class="bi bi-chevron-right text-slate-600 dark:text-slate-355"></i>
                    </a>
                </div>

                <!-- Today's Shift Info (only shown if there is a shift/leave today) -->
                @php
                    $todayData = $daysInMonth[$today] ?? null;
                    $todaySchedules = $todayData['schedules'] ?? collect();
                    $todayLeaves = $todayData['leaves'] ?? collect();
                @endphp

                @if($todaySchedules->isNotEmpty() || $todayLeaves->isNotEmpty())
                    <a href="{{ route('attendance.index') }}" class="block bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 hover:border-pcrm-500/40 dark:hover:border-pcrm-500/30 transition-colors">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-xs font-black text-slate-800 dark:text-slate-100 uppercase tracking-wide flex items-center gap-1.5">
                                <i class="bi bi-calendar-star text-pcrm-600"></i> Ca làm việc hôm nay
                            </h3>
                            <span class="text-[9px] font-bold text-pcrm-600 dark:text-pcrm-400 bg-pcrm-50 dark:bg-pcrm-900/20 px-2 py-0.5 rounded-full flex items-center gap-1">
                                {{ \Carbon\Carbon::parse($today)->format('d/m/Y') }}
                                <i class="bi bi-chevron-right text-[8px]"></i>
                            </span>
                        </div>

                        @foreach($todaySchedules as $schedule)
                            @php
                                // effectiveShift(): ca linh hoạt (shift_id null) dựng Shift tạm từ custom_*.
                                $shift = $schedule->effectiveShift();
                                $shiftName = $schedule->shift?->name ?? 'Ca linh hoạt';
                                $timeRange = $shift ? substr($shift->start_time, 0, 5) . ' – ' . substr($shift->end_time, 0, 5) : '—';
                                // Lượt chấm công của ĐÚNG ca này (không dùng chung 1 log/ngày).
                                $todayLog = $scheduleLogs[$schedule->id] ?? null;

                                if ($todayLog && $todayLog->check_in_at && $todayLog->check_out_at) {
                                    $statusLabel = 'Đã hoàn thành';
                                    $statusCls = 'bg-emerald-500/10 text-emerald-650 dark:text-emerald-400 border-emerald-500/20';
                                } elseif ($todayLog && $todayLog->check_in_at) {
                                    $statusLabel = 'Đang trong ca';
                                    $statusCls = 'bg-blue-500/10 text-blue-650 dark:text-blue-400 border-blue-500/20';
                                } else {
                                    $statusLabel = 'Sắp tới';
                                    $statusCls = 'bg-slate-500/10 text-slate-500 dark:text-slate-400 border-slate-500/20';
                                }
                            @endphp

                            <div class="mb-3 last:mb-0 border-b border-slate-100/50 dark:border-slate-800/30 pb-3 last:border-b-0 last:pb-0">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <h4 class="text-xs font-black text-slate-850 dark:text-slate-100 flex items-center gap-1">
                                            @if($shift?->isWfh())
                                                <span>🏠</span>
                                            @endif
                                            <span>{{ $shiftName }}</span>
                                        </h4>
                                        <p class="text-[11px] text-slate-500 dark:text-slate-450 font-bold mt-0.5">Ca: {{ $timeRange }}</p>
                                    </div>
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[9px] font-black border uppercase tracking-wider {{ $statusCls }}">
                                        {{ $statusLabel }}
                                    </span>
                                </div>

                                @if($todayLog && ($todayLog->check_in_at || $todayLog->check_out_at))
                                    <div class="grid grid-cols-2 gap-2 mt-2 pt-2 border-t border-slate-100/50 dark:border-slate-800/20 text-[10px]">
                                        <div>
                                            <span class="text-slate-450 dark:text-slate-500 font-bold block uppercase tracking-wide text-[8px]">Vào ca</span>
                                            <span class="font-extrabold text-slate-700 dark:text-slate-350 mt-0.5 block">
                                                {{ $todayLog->check_in_at?->format('H:i') ?? '—' }}
                                                @if($todayLog->late_minutes > 0)
                                                    <span class="text-amber-500 font-bold">(trễ {{ $todayLog->late_minutes }}p)</span>
                                                @endif
                                            </span>
                                        </div>
                                        <div>
                                            <span class="text-slate-450 dark:text-slate-500 font-bold block uppercase tracking-wide text-[8px]">Ra ca</span>
                                            <span class="font-extrabold text-slate-700 dark:text-slate-355 mt-0.5 block">
                                                {{ $todayLog->check_out_at?->format('H:i') ?? '—' }}
                                                @if($todayLog->early_minutes > 0)
                                                    <span class="text-amber-500 font-bold">(sớm {{ $todayLog->early_minutes }}p)</span>
                                                @endif
                                            </span>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach

                        @foreach($todayLeaves as $leave)
                            <div class="{{ !$loop->first ? 'mt-3 pt-3 border-t border-slate-100/50 dark:border-slate-800/30' : '' }}">
                                <div class="flex items-center justify-between gap-2">
                                    <h4 class="text-xs font-black text-indigo-650 dark:text-indigo-400">✈ Nghỉ phép</h4>
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[9px] font-black bg-indigo-500/10 text-indigo-650 dark:text-indigo-400 border border-indigo-500/20 uppercase tracking-wider">
                                        {{ $leave->typeLabel() }}
                                    </span>
                                </div>
                                @if($leave->reason)
                                    <p class="text-[10px] text-slate-500 dark:text-slate-400 font-bold mt-1 bg-slate-50 dark:bg-slate-900/30 p-2 rounded-lg">Lý do: {{ $leave->reason }}</p>
                                @endif
                            </div>
                        @endforeach
                    </a>
                @endif

                <!-- Monthly Summary Cards Grid -->
                @php
                    // Nhân viên part_time trả lương theo giờ nên chỉ theo dõi "Giờ làm" — "Tính công"
                    // (quy đổi giờ làm sang công theo giờ chuẩn ca fulltime) không áp dụng cho họ.
                    $isPartTime = $employee->employment_type === 'part_time';
                @endphp
                <div class="grid {{ $isPartTime ? 'grid-cols-2' : 'grid-cols-3' }} gap-2.5">
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-3 text-center">
                        <span class="text-[9px] font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wider block">Giờ làm</span>
                        <span class="text-sm font-black text-slate-800 dark:text-white mt-1 block">{{ $summary['worked_hours'] }}h</span>
                    </div>
                    @unless($isPartTime)
                        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-3 text-center">
                            <span class="text-[9px] font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wider block">Tính công</span>
                            <span class="text-sm font-black text-pcrm-600 dark:text-pcrm-400 mt-1 block">{{ $summary['cong'] }}</span>
                        </div>
                    @endunless
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-3 text-center">
                        <span class="text-[9px] font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wider block">Ngày công</span>
                        <span class="text-sm font-black text-indigo-650 dark:text-indigo-400 mt-1 block">{{ $summary['days_worked'] }}d</span>
                    </div>
                </div>

                <!-- Enlarged Toggle Tab Switcher (Light gray bg, active theme green color) -->
                <div class="flex p-1 rounded-xl bg-slate-200 dark:bg-slate-900">
                    <button id="toggleAllDays" onclick="filterSchedules('all')" class="flex-1 py-3.5 px-4 rounded-lg text-xs font-black text-center bg-pcrm-600 text-white shadow-sm transition-all">
                        Tất cả ngày
                    </button>
                    <button id="toggleWorkDays" onclick="filterSchedules('work')" class="flex-1 py-3.5 px-4 rounded-lg text-xs font-bold text-center text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 transition-all">
                        Chỉ ngày làm việc
                    </button>
                </div>

                <!-- Mobile Schedule Cards List -->
                <div class="space-y-3" id="mobileScheduleContainer">
                    @foreach($daysInMonth as $dateStr => $dayData)
                        @php
                            $date = $dayData['date'];
                            $schedules = $dayData['schedules'];
                            $leaves = $dayData['leaves'];
                            $hasWork = $schedules->isNotEmpty() || $leaves->isNotEmpty();
                            
                            $dowMap = [1 => 'T2', 2 => 'T3', 3 => 'T4', 4 => 'T5', 5 => 'T6', 6 => 'T7', 0 => 'CN'];
                            $dow = $dowMap[$date->dayOfWeekIso % 7];
                            $isWeekend = $date->isWeekend();
                            $isToday = $dateStr === $today;
                        @endphp

                        <div class="date-card bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 flex items-start gap-4 transition-all {{ !$hasWork ? 'date-card-off opacity-60' : '' }} {{ $isToday ? 'ring-2 ring-pcrm-500/50' : '' }}" data-has-work="{{ $hasWork ? 'true' : 'false' }}">
                            <!-- Date Stamp Left -->
                            <div class="flex flex-col items-center justify-center w-11 h-11 rounded-xl flex-shrink-0 {{ $isToday ? 'bg-pcrm-600 text-white' : ($isWeekend ? 'bg-amber-500/10 text-amber-600 dark:text-amber-450 border border-amber-500/20' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-350 border border-slate-200/10') }}">
                                <span class="text-[9px] font-black uppercase tracking-wider">{{ $dow }}</span>
                                <span class="text-sm font-black mt-0.5">{{ $date->day }}</span>
                            </div>

                            <!-- Right Details -->
                            <div class="flex-1 min-w-0">
                                @if($schedules->isNotEmpty())
                                    @foreach($schedules as $schedule)
                                        @php
                                            $shift = $schedule->effectiveShift();
                                            $shiftName = $schedule->shift?->name ?? 'Ca linh hoạt';
                                            $timeRange = $shift ? substr($shift->start_time, 0, 5) . ' – ' . substr($shift->end_time, 0, 5) : '—';
                                            // Lượt chấm công của ĐÚNG ca này (ngày đa ca: mỗi ca 1 lượt riêng).
                                            $log = $scheduleLogs[$schedule->id] ?? null;

                                            if ($log && $log->check_in_at && $log->check_out_at) {
                                                $statusLabel = 'Đã hoàn thành';
                                                $statusCls = 'bg-emerald-500/10 text-emerald-650 dark:text-emerald-400 border-emerald-500/20';
                                            } elseif ($log && $log->check_in_at) {
                                                $statusLabel = 'Đang trong ca';
                                                $statusCls = 'bg-blue-500/10 text-blue-650 dark:text-blue-400 border-blue-500/20';
                                            } elseif ($dateStr < $today) {
                                                $statusLabel = 'Chưa chấm công';
                                                $statusCls = 'bg-rose-500/10 text-rose-650 dark:text-rose-450 border-rose-500/20';
                                            } else {
                                                $statusLabel = 'Sắp tới';
                                                $statusCls = 'bg-slate-500/10 text-slate-500 dark:text-slate-400 border-slate-500/20';
                                            }
                                        @endphp
                                        
                                        <div class="mb-3 last:mb-0 border-b border-slate-100/50 dark:border-slate-800/30 pb-3 last:border-b-0 last:pb-0">
                                            <div class="flex items-start justify-between gap-2">
                                                <div>
                                                    <h4 class="text-xs font-black text-slate-850 dark:text-slate-100 flex items-center gap-1">
                                                        @if($shift?->isWfh())
                                                            <span>🏠</span>
                                                        @endif
                                                        <span>{{ $shiftName }}</span>
                                                    </h4>
                                                    <p class="text-[11px] text-slate-500 dark:text-slate-450 font-bold mt-0.5">Ca: {{ $timeRange }}</p>
                                                </div>
                                                <span class="inline-flex px-2 py-0.5 rounded-full text-[9px] font-black border uppercase tracking-wider {{ $statusCls }}">
                                                    {{ $statusLabel }}
                                                </span>
                                            </div>

                                            @if($log && ($log->check_in_at || $log->check_out_at))
                                                <div class="grid grid-cols-2 gap-2 mt-2 pt-2 border-t border-slate-100/50 dark:border-slate-800/20 text-[10px]">
                                                    <div>
                                                        <span class="text-slate-450 dark:text-slate-500 font-bold block uppercase tracking-wide text-[8px]">Vào ca</span>
                                                        <span class="font-extrabold text-slate-700 dark:text-slate-350 mt-0.5 block">
                                                            {{ $log->check_in_at?->format('H:i') ?? '—' }}
                                                            @if($log->late_minutes > 0)
                                                                <span class="text-amber-500 font-bold">(trễ {{ $log->late_minutes }}p)</span>
                                                            @endif
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <span class="text-slate-450 dark:text-slate-500 font-bold block uppercase tracking-wide text-[8px]">Ra ca</span>
                                                        <span class="font-extrabold text-slate-700 dark:text-slate-355 mt-0.5 block">
                                                            {{ $log->check_out_at?->format('H:i') ?? '—' }}
                                                            @if($log->early_minutes > 0)
                                                                <span class="text-amber-500 font-bold">(sớm {{ $log->early_minutes }}p)</span>
                                                            @endif
                                                        </span>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                @elseif($leaves->isNotEmpty())
                                    @foreach($leaves as $leave)
                                        <div>
                                            <div class="flex items-center justify-between gap-2">
                                                <h4 class="text-xs font-black text-indigo-650 dark:text-indigo-400">✈ Nghỉ phép</h4>
                                                <span class="inline-flex px-2 py-0.5 rounded-full text-[9px] font-black bg-indigo-500/10 text-indigo-650 dark:text-indigo-400 border border-indigo-500/20 uppercase tracking-wider">
                                                    {{ $leave->typeLabel() }}
                                                </span>
                                            </div>
                                            @if($leave->reason)
                                                <p class="text-[10px] text-slate-500 dark:text-slate-400 font-bold mt-1 bg-slate-50 dark:bg-slate-900/30 p-2 rounded-lg">Lý do: {{ $leave->reason }}</p>
                                            @endif
                                        </div>
                                    @endforeach
                                @else
                                    <div class="flex items-center justify-between">
                                        <span class="text-[11px] text-slate-400 dark:text-slate-550 font-bold italic">Ngày nghỉ (Off)</span>
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-300 dark:bg-slate-700"></span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Custom Mobile Filter Script -->
                <script>
                function filterSchedules(mode) {
                    const container = document.getElementById('mobileScheduleContainer');
                    const allDaysBtn = document.getElementById('toggleAllDays');
                    const workDaysBtn = document.getElementById('toggleWorkDays');
                    
                    const activeCls = "flex-1 py-3.5 px-4 rounded-lg text-xs font-black text-center bg-pcrm-600 text-white shadow-sm transition-all";
                    const inactiveCls = "flex-1 py-3.5 px-4 rounded-lg text-xs font-bold text-center text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 transition-all";
                    
                    if (mode === 'work') {
                        // Hide days with no work
                        const offCards = container.querySelectorAll('.date-card-off');
                        offCards.forEach(card => card.classList.add('hidden'));
                        
                        // Toggle button styling
                        workDaysBtn.className = activeCls;
                        allDaysBtn.className = inactiveCls;
                    } else {
                        // Show all days
                        const allCards = container.querySelectorAll('.date-card');
                        allCards.forEach(card => card.classList.remove('hidden'));
                        
                        // Toggle button styling
                        allDaysBtn.className = activeCls;
                        workDaysBtn.className = inactiveCls;
                    }
                }
                </script>

                <!-- Legend / Notes Section for Mobile -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 flex flex-col gap-4 text-[10px] font-bold text-slate-500 dark:text-slate-400">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-[8px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 mr-2">Phân loại ca:</span>
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-slate-50 dark:bg-slate-800/40 border border-slate-200/30 dark:border-slate-750/30 text-slate-600 dark:text-slate-350">
                            <span class="w-2 h-2 rounded-full bg-sky-400 block"></span> Ca thường
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-slate-50 dark:bg-slate-800/40 border border-slate-200/30 dark:border-slate-750/30 text-slate-600 dark:text-slate-350">
                            <span class="w-2 h-2 rounded-full bg-slate-400 block"></span> Nghỉ phép
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-slate-50 dark:bg-slate-800/40 border border-slate-200/30 dark:border-slate-750/30 text-slate-600 dark:text-slate-350">
                            🏠 WFH
                        </span>
                    </div>
                    
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-[8px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 mr-2">Chấm công:</span>
                        <span class="inline-flex px-1.5 py-0.5 rounded bg-emerald-50 dark:bg-emerald-950/30 text-emerald-655 dark:text-emerald-400 border border-emerald-100/30 dark:border-emerald-900/20">✓ Đủ công</span>
                        <span class="inline-flex px-1.5 py-0.5 rounded bg-amber-50 dark:bg-amber-950/30 text-amber-600 dark:text-amber-400 border border-amber-100/30 dark:border-amber-900/20">⏰ Trễ/sớm</span>
                        <span class="inline-flex px-1.5 py-0.5 rounded bg-blue-50 dark:bg-blue-950/30 text-blue-650 dark:text-blue-400 border border-blue-100/30 dark:border-blue-900/30">🟡 Đang trong ca</span>
                        <span class="inline-flex px-1.5 py-0.5 rounded bg-rose-50 dark:bg-rose-950/30 text-rose-600 dark:text-rose-455 border border-rose-100/30 dark:border-rose-900/20">⚠ Chưa chấm công</span>
                    </div>
                </div>

            @else
                {{-- DESKTOP VIEW: Full Calendar Layout (Solid panels, visual shift representation) --}}
                <div class="bg-white dark:bg-slate-900 rounded-xl p-2 shadow-sm sm:p-4 border border-slate-200/50 dark:border-slate-800/60">
                    <!-- Calendar mount container -->
                    <div id="workCalendar" class="w-full"></div>

                    <!-- Legend / Notes Section (Moved to the Bottom) -->
                    <div class="mt-6 pt-5 border-t border-slate-200/40 dark:border-slate-800/60 flex flex-col gap-4 text-xs font-semibold text-slate-500 dark:text-slate-400">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-[9px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 mr-2">Phân loại ca:</span>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-50 dark:bg-slate-800/40 border border-slate-200/30 dark:border-slate-750/30 text-[11px] font-bold text-slate-600 dark:text-slate-350">
                                <span class="w-2.5 h-2.5 rounded-full bg-sky-400 block"></span> Ca thường
                            </span>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-50 dark:bg-slate-800/40 border border-slate-200/30 dark:border-slate-750/30 text-[11px] font-bold text-slate-600 dark:text-slate-355">
                                <span class="w-2.5 h-2.5 rounded-full bg-slate-400 block"></span> Nghỉ phép
                            </span>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-50 dark:bg-slate-800/40 border border-slate-200/30 dark:border-slate-750/30 text-[11px] font-bold text-slate-600 dark:text-slate-355">
                                🏠 Làm từ xa (WFH)
                            </span>
                        </div>
                        
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-[9px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 mr-2">Chấm công:</span>
                            <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/30 text-emerald-650 dark:text-emerald-400 border border-emerald-100/30 dark:border-emerald-900/20">✓ Đủ công</span>
                            <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 dark:bg-amber-950/30 text-amber-600 dark:text-amber-400 border border-amber-100/30 dark:border-amber-900/20">⏰ Trễ / sớm</span>
                            <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 dark:bg-blue-950/30 text-blue-650 dark:text-blue-400 border border-blue-100/30 dark:border-blue-900/30">🟡 Đang trong ca</span>
                            <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold bg-rose-50 dark:bg-rose-950/30 text-rose-600 dark:text-rose-455 border border-rose-100/30 dark:border-rose-900/20">⚠ Chưa chấm công</span>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('modals')
    <div id="shiftDetailModal" class="hidden fixed inset-0 bg-black/50 z-50 items-center justify-center p-4"
         onclick="if(event.target===this) closeModal('shiftDetailModal')">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-sm overflow-hidden border border-slate-100 dark:border-slate-700">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 dark:border-slate-700">
                <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2 min-w-0">
                    <i class="bi bi-calendar-check text-pcrm-600 flex-shrink-0 text-lg"></i>
                    <span id="shiftDetailDate" class="truncate font-extrabold text-sm text-slate-850 dark:text-slate-100"></span>
                </h3>
                <button onclick="closeModal('shiftDetailModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 flex-shrink-0">
                    <i class="bi bi-x-lg text-sm"></i>
                </button>
            </div>

            <div class="px-5 py-4">
                <div class="flex items-start justify-between gap-2 mb-1.5">
                    <p id="shiftDetailName" class="font-black text-slate-800 dark:text-slate-100 leading-snug"></p>
                    <span id="shiftDetailStatusBadge" class="text-xs font-bold px-2 py-0.5 rounded-full whitespace-nowrap flex-shrink-0"></span>
                </div>
                <p id="shiftDetailTime" class="text-xs font-bold text-slate-500 dark:text-slate-400 mb-4"></p>

                <div id="shiftDetailAttendance" class="grid grid-cols-2 gap-3">
                    <div class="rounded-xl border border-slate-200 dark:border-slate-750 p-3 bg-slate-50/50 dark:bg-slate-900/20">
                        <p class="text-[10px] font-bold text-slate-400 uppercase mb-1">Check-in</p>
                        <p id="shiftDetailCheckIn" class="font-black text-slate-800 dark:text-slate-100 text-sm">—</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 dark:border-slate-755 p-3 bg-slate-50/50 dark:bg-slate-900/20">
                        <p class="text-[10px] font-bold text-slate-400 uppercase mb-1">Check-out</p>
                        <p id="shiftDetailCheckOut" class="font-black text-slate-800 dark:text-slate-100 text-sm">—</p>
                    </div>
                </div>

                <p id="shiftDetailLeaveReason" class="hidden text-sm font-semibold text-slate-650 dark:text-slate-350 mt-3 p-2.5 bg-slate-50 dark:bg-slate-900/40 rounded-xl"></p>
            </div>
        </div>
    </div>
@endpush

@push('scripts')
@if (!$isMobileDevice)
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6/locales-all.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var calendarEl = document.getElementById('workCalendar');

    var calendar = new FullCalendar.Calendar(calendarEl, {
        locale: 'vi',
        timeZone: 'local',
        initialView: 'dayGridMonth',
        height: 'auto',
        firstDay: 1, // Start on Monday
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,listMonth'
        },
        buttonText: { 
            today: 'Hôm nay',
            month: 'Tháng',
            list: 'Danh sách'
        },
        events: '{{ route('my-schedule.events') }}',
        eventClick: function (arg) {
            var props = arg.event.extendedProps;
            var dateLabel = arg.event.start.toLocaleDateString('vi-VN', {
                weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric',
            });
            document.getElementById('shiftDetailDate').textContent = dateLabel;

            var nameEl          = document.getElementById('shiftDetailName');
            var timeEl          = document.getElementById('shiftDetailTime');
            var badgeEl         = document.getElementById('shiftDetailStatusBadge');
            var attendanceBox   = document.getElementById('shiftDetailAttendance');
            var checkInEl       = document.getElementById('shiftDetailCheckIn');
            var checkOutEl      = document.getElementById('shiftDetailCheckOut');
            var leaveReasonEl   = document.getElementById('shiftDetailLeaveReason');

            if (props.type === 'leave') {
                nameEl.textContent = arg.event.title;
                timeEl.textContent = '';
                badgeEl.textContent = '';
                attendanceBox.classList.add('hidden');
                leaveReasonEl.textContent = props.reason ? 'Lý do: ' + props.reason : 'Không có ghi chú';
                leaveReasonEl.classList.remove('hidden');
                openModal('shiftDetailModal');
                return;
            }

            var statusMap = {
                completed:   { label: 'Đã hoàn thành',   cls: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' },
                in_progress: { label: 'Đang trong ca',    cls: 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400' },
                missed:      { label: 'Chưa chấm công',   cls: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' },
                upcoming:    { label: 'Sắp tới',          cls: 'bg-slate-100 text-slate-650 dark:bg-slate-700 dark:text-slate-300' },
            };
            var status = statusMap[props.attendanceStatus] || statusMap.upcoming;

            nameEl.textContent = arg.event.title;
            timeEl.textContent = 'Ca: ' + props.timeRange + (props.wfh ? ' · Làm việc từ xa (WFH)' : '');
            badgeEl.textContent = status.label;
            badgeEl.className = 'text-xs font-semibold px-2 py-0.5 rounded-full whitespace-nowrap flex-shrink-0 ' + status.cls;

            checkInEl.textContent = props.checkInAt
                ? props.checkInAt + (props.lateMinutes > 0 ? ' (trễ ' + props.lateMinutes + 'p)' : '')
                : '—';
            checkOutEl.textContent = props.checkOutAt
                ? props.checkOutAt + (props.earlyMinutes > 0 ? ' (sớm ' + props.earlyMinutes + 'p)' : '')
                : '—';
            attendanceBox.classList.remove('hidden');
            leaveReasonEl.classList.add('hidden');

            openModal('shiftDetailModal');
        },
        eventContent: function (arg) {
            var props = arg.event.extendedProps;

            var wrapper = document.createElement('div');
            wrapper.className = 'wc-event-chip flex flex-col gap-1 w-full';

            // Title / Shift Name
            var titleEl = document.createElement('div');
            titleEl.className = 'wc-event-title font-extrabold text-[11px] leading-tight text-slate-800 dark:text-slate-100 flex items-center gap-1 truncate';
            
            if (props.type === 'leave') {
                titleEl.innerHTML = '<span class="text-xs">✈</span> ' + arg.event.title.replace('✈ ', '');
            } else {
                titleEl.innerHTML = (props.wfh ? '<span class="text-xs">🏠</span> ' : '') + arg.event.title.replace('🏠 ', '').split(' (')[0];
            }
            wrapper.appendChild(titleEl);

            // Shift time details
            if (props.type === 'shift') {
                var timeEl = document.createElement('span');
                timeEl.className = 'text-[9px] font-bold text-slate-500 dark:text-slate-450';
                timeEl.textContent = props.timeRange;
                wrapper.appendChild(timeEl);
            } else if (props.type === 'leave') {
                var leaveEl = document.createElement('div');
                leaveEl.className = 'text-[9px] font-bold text-slate-550 dark:text-slate-400 italic truncate';
                leaveEl.textContent = props.reason || 'Nghỉ phép';
                wrapper.appendChild(leaveEl);
            }

            return { domNodes: [wrapper] };
        },
        eventDidMount: function (info) {
            var props = info.event.extendedProps;
            
            // Set dynamic left border color to match the shift's theme color
            if (info.event.borderColor) {
                info.el.style.setProperty('border-left-color', info.event.borderColor, 'important');
            }
            
            var lines = [info.event.title];

            if (props.type === 'leave' && props.reason) {
                lines.push(props.reason);
            }
            if (props.type === 'shift') {
                if (props.checkInAt) {
                    lines.push('Check-in: ' + props.checkInAt + (props.lateMinutes > 0 ? ' (trễ ' + props.lateMinutes + ' phút)' : ''));
                }
                if (props.checkOutAt) {
                    lines.push('Check-out: ' + props.checkOutAt + (props.earlyMinutes > 0 ? ' (sớm ' + props.earlyMinutes + ' phút)' : ''));
                }
                if (props.attendanceStatus === 'missed') {
                    lines.push('Chưa có dữ liệu chấm công cho ngày này');
                }
            }

            info.el.setAttribute('title', lines.join('\n'));
        },
    });

    calendar.render();
});
</script>
@endif
@endpush
