@extends('layouts.admin')

@section('title', 'Xếp ca')
@section('page-title', 'Xếp ca làm việc')
@section('page-subtitle', 'Lịch xếp ca theo tuần — ca cố định và đa ca')
@section('breadcrumb', 'Ca làm việc & Chấm công')

@can('create-shift-schedules')
@section('page-actions')
    <button onclick="openModal('bulkAssignModal')" class="btn-primary h-9 text-xs font-bold">
        <i class="bi bi-calendar-plus"></i>
        <span>Xếp ca cố định</span>
    </button>
@endsection
@endcan

@section('content')
    @php
        $currentBranch = request('branch_id');
        $currentTeam = request('team_id');
        $q = fn(array $set = [], array $drop = []) => array_filter(
            array_merge(request()->except(array_merge(['page'], $drop, array_keys($set))), $set),
            fn($v) => $v !== null && $v !== ''
        );
        $sortedEmployees = $employees;
        if ($myEmployee) {
            $sortedEmployees = collect($employees instanceof \Illuminate\Pagination\LengthAwarePaginator ? $employees->items() : $employees)->sortBy(function($emp) use ($myEmployee) {
                if ($emp->id === $myEmployee->id) return 0;
                if ($emp->team_id === $myEmployee->team_id) return 1;
                return 2;
            });
        }
    @endphp
    <form action="{{ route('shift-schedules.index') }}" method="GET" id="shiftSchedulesFilterForm">
        <input type="hidden" name="view" id="schedCurrentViewInput" value="{{ $viewMode }}">
        @if(request('employee_id'))<input type="hidden" name="employee_id" value="{{ request('employee_id') }}">@endif
        @if(request('no_shift_today'))<input type="hidden" name="no_shift_today" value="{{ request('no_shift_today') }}">@endif
        @if(request('branch_id'))<input type="hidden" name="branch_id" value="{{ request('branch_id') }}">@endif
        @if(request('team_id'))<input type="hidden" name="team_id" value="{{ request('team_id') }}">@endif

        <div class="card overflow-hidden mb-4">
            {{-- Status Tabs: Lọc theo Chi nhánh (như trang Thông báo) --}}
            <div class="notif-head">
                <nav class="status-tabs" aria-label="Lọc theo Chi nhánh">
                    <a href="{{ route('shift-schedules.index', $q([], ['branch_id', 'team_id'])) }}"
                       class="status-tab {{ !$currentBranch ? 'is-active' : '' }}" @if (!$currentBranch) aria-current="page" @endif>
                        Tất cả chi nhánh <span class="status-tab-count">{{ number_format($branchCounts['all'] ?? $allEmployees->count()) }}</span>
                    </a>
                    @foreach ($branches as $b)
                        <a href="{{ route('shift-schedules.index', $q(['branch_id' => $b->id], ['team_id'])) }}"
                           class="status-tab {{ $currentBranch == $b->id ? 'is-active' : '' }}"
                           @if ($currentBranch == $b->id) aria-current="page" @endif>
                            {{ $b->name }} <span class="status-tab-count">{{ number_format($branchCounts[$b->id] ?? 0) }}</span>
                        </a>
                    @endforeach
                </nav>
                @if(request()->anyFilled(['branch_id', 'team_id']))
                    <a href="{{ route('shift-schedules.index', $q([], ['branch_id', 'team_id'])) }}"
                       class="notif-head-action text-slate-500 hover:text-red-600 dark:text-slate-400 dark:hover:text-red-400"
                       title="Bỏ lọc chi nhánh & đội nhóm">
                        <i class="bi bi-x-circle" aria-hidden="true"></i>
                        <span class="hidden sm:inline">Bỏ lọc</span>
                    </a>
                @endif
            </div>

            {{-- Type Chips: Lọc theo Đội nhóm (như nhóm Thông báo) --}}
            @php
                $visibleTeams = $teams->when($currentBranch, fn($c) => $c->where('branch_id', $currentBranch));
                $totalForTeams = $visibleTeams->sum(fn($t) => $teamCounts[$t->id] ?? 0);
            @endphp
            @if ($visibleTeams->isNotEmpty() || $currentTeam)
                <div class="type-chips notif-chips" role="group" aria-label="Lọc theo Đội nhóm">
                    <a href="{{ route('shift-schedules.index', $q([], ['team_id'])) }}"
                       class="type-chip {{ !$currentTeam ? 'is-active' : '' }}" @if (!$currentTeam) aria-current="true" @endif>
                        <i class="bi bi-grid" aria-hidden="true"></i> Mọi đội nhóm
                        <span class="type-chip-count">{{ number_format($totalForTeams) }}</span>
                    </a>
                    @foreach ($visibleTeams as $t)
                        <a href="{{ route('shift-schedules.index', $q(['team_id' => $t->id])) }}"
                           class="type-chip {{ $currentTeam == $t->id ? 'is-active' : '' }}"
                           @if ($currentTeam == $t->id) aria-current="true" @endif>
                            <i class="bi bi-people" aria-hidden="true"></i> {{ $t->name }}
                            <span class="type-chip-count">{{ number_format($teamCounts[$t->id] ?? 0) }}</span>
                        </a>
                    @endforeach
                </div>
            @endif

            <x-table-toolbar>
                <x-slot:info>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <div class="flex items-center gap-1.5">
                            <label for="schedWeekInput" class="text-xs font-bold text-slate-500 dark:text-slate-400 font-heading">Tuần:</label>
                            <input type="date" id="schedWeekInput" name="week" value="{{ $weekStart->toDateString() }}" class="form-input text-xs w-auto py-1 px-2.5 h-9 font-mono font-semibold" onchange="this.form.submit()">
                        </div>

                        <div class="ops-views" role="tablist" aria-label="Chế độ hiển thị">
                            <button type="button" role="tab" id="viewBtnMatrix" onclick="switchScheduleView('matrix')"
                                    aria-selected="{{ $viewMode === 'matrix' ? 'true' : 'false' }}" aria-controls="scheduleMatrixView"
                                    class="ops-view-btn font-heading {{ $viewMode === 'matrix' ? 'is-active' : '' }}" title="Lưới tuần chi tiết">
                                <i class="bi bi-grid-3x3" aria-hidden="true"></i> <span>Lưới tuần</span>
                            </button>
                            <button type="button" role="tab" id="viewBtnTable" onclick="switchScheduleView('table')"
                                    aria-selected="{{ $viewMode === 'table' ? 'true' : 'false' }}" aria-controls="scheduleTableView"
                                    class="ops-view-btn font-heading {{ $viewMode === 'table' ? 'is-active' : '' }}" title="Bảng tuần ngắn gọn">
                                <i class="bi bi-table" aria-hidden="true"></i> <span>Bảng gọn</span>
                            </button>
                            <button type="button" role="tab" id="viewBtnList" onclick="switchScheduleView('list')"
                                    aria-selected="{{ $viewMode === 'list' ? 'true' : 'false' }}" aria-controls="scheduleListView"
                                    class="ops-view-btn font-heading {{ $viewMode === 'list' ? 'is-active' : '' }}" title="Danh sách theo ngày">
                                <i class="bi bi-list-ul" aria-hidden="true"></i> <span>Danh sách</span>
                            </button>
                        </div>
                    </div>
                </x-slot:info>

                <button type="button" onclick="toggleFilterDrawer(true)" class="btn-secondary h-9 px-2.5 sm:px-3 gap-1.5 text-xs font-black relative" title="Bộ lọc tìm kiếm">
                    <i class="bi bi-funnel"></i>
                    <span>Bộ lọc</span>
                    @if(request()->anyFilled(['branch_id', 'team_id', 'employee_id']))
                        <span class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-pcrm-600 rounded-full animate-pulse"></span>
                    @endif
                </button>
                @php
                    $noShiftTodayActive = request()->boolean('no_shift_today');
                    $noShiftTodayParams = $noShiftTodayActive
                        ? collect(request()->query())->except('no_shift_today')->all()
                        : array_merge(request()->query(), ['no_shift_today' => 1]);
                @endphp
                <a href="{{ route('shift-schedules.index', $noShiftTodayParams) }}"
                   class="btn-secondary h-9 px-2.5 sm:px-3 text-xs font-bold {{ $noShiftTodayActive ? '!border-pcrm-400 !bg-pcrm-50 !text-pcrm-700 dark:!border-pcrm-600 dark:!bg-pcrm-900/30 dark:!text-pcrm-400' : '' }}"
                   title="{{ $noShiftTodayActive ? 'Bỏ lọc nhân viên chưa có ca hôm nay' : 'Lọc nhân viên chưa có ca hôm nay' }}"
                   @if($noShiftTodayActive) aria-current="true" @endif>
                    <i class="bi bi-person-dash"></i>
                    <span>{{ $noShiftTodayActive ? 'Đang lọc: Chưa có ca hôm nay' : 'NV chưa có ca hôm nay' }}</span>
                </a>
                @can('view-attendance')
                <button type="button" onclick="openOnShiftModal()" class="btn-secondary h-9 px-2.5 sm:px-3 text-xs font-bold" title="Xem danh sách nhân viên đang trong ca trực">
                    <i class="bi bi-person-badge-fill"></i>
                    <span>NV đang trong ca</span>
                </button>
                @endcan
                @can('export-shift-schedules')
                <button type="button" onclick="openModal('exportShiftSchedulesModal')" class="btn-secondary h-9 px-2.5 sm:px-3 text-xs font-bold" title="Xuất dữ liệu xếp ca ra file Excel">
                    <i class="bi bi-file-earmark-excel"></i>
                    <span class="hidden sm:inline">Xuất</span> <span>Excel</span>
                </button>
                @endcan
                @can('delete-shift-schedules')
                <button type="button" onclick="openModal('deleteShiftScheduleModal')" class="btn-secondary h-9 px-2.5 sm:px-3 text-xs font-bold text-red-600 dark:text-red-400" title="Xoá ca theo khoảng ngày hoặc nhân viên">
                    <i class="bi bi-trash3"></i>
                    <span>Xoá ca</span>
                </button>
                @endcan
            </x-table-toolbar>

            {{-- Thanh legend thu gọn thanh lịch --}}
            <div class="px-4 py-2 border-t border-slate-100 dark:border-slate-800/60 bg-slate-50/40 dark:bg-slate-900/20 flex items-center justify-between gap-3 text-[11px] text-slate-500 dark:text-slate-400 overflow-x-auto scrollbar-thin">
                <div class="flex items-center gap-4 flex-wrap">
                    <span class="font-semibold text-slate-600 dark:text-slate-300 flex items-center gap-1"><i class="bi bi-info-circle"></i> Chấm công:</span>
                    <span class="inline-flex items-center gap-1.5"><span class="sched-dot sched-dot-pending"></span> Chưa chấm công</span>
                    <span class="inline-flex items-center gap-1.5"><span class="sched-dot sched-dot-ontime"></span> Đúng giờ</span>
                    <span class="inline-flex items-center gap-1.5"><span class="sched-dot sched-dot-late"></span> Trễ / về sớm</span>
                </div>
                <div class="text-[10.5px] text-slate-400 dark:text-slate-500 hidden md:block">
                    * Nhấp vào ô ca để xem chi tiết hoặc chấm công hộ
                </div>
            </div>

        <div class="p-0 overflow-x-auto w-full">
            {{-- VIEW 1: LƯỚI TUẦN (GRID / MATRIX) --}}
            <div id="scheduleMatrixView" role="tabpanel" aria-labelledby="viewBtnMatrix" class="{{ $viewMode === 'matrix' ? '' : 'hidden' }}">
                <div class="hidden lg:block table-container border-0 rounded-none overflow-x-auto">
                    <table class="table-base min-w-[900px] border-separate border-spacing-0">
                        <thead>
                            <tr>
                                <th class="table-th sched-sticky-col font-heading">Nhân viên</th>
                                @foreach($days as $day)
                                    <th class="table-th text-center sched-th @if($day->isWeekend()) sched-th-weekend @endif @if($day->isToday()) sched-th-today @endif">
                                        <span class="sched-th-dow font-heading">{{ ['CN','T2','T3','T4','T5','T6','T7'][$day->dayOfWeek] }}</span>
                                        <span class="sched-th-date font-heading @if($day->isToday()) sched-th-date-today @endif">{{ $day->format('d/m') }}</span>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                    <tbody>
                        @forelse($sortedEmployees as $emp)
                        <tr class="table-tr-hover">
                            <td class="table-td font-medium sched-sticky-col">
                                <span class="sched-emp-name block text-sm font-bold text-slate-800 dark:text-slate-100" style="font-family: var(--font-heading, 'Bricolage Grotesque', sans-serif) !important;">{{ $emp->name }}</span>
                                <p class="text-xs text-slate-400 mt-0.5">{{ $emp->team?->name ?? '—' }}</p>
                            </td>
                            @foreach($days as $day)
                                @php
                                    $key = $emp->id . '_' . $day->toDateString();
                                    $cellSchedules = $schedules->get($key, collect());
                                    $cellData = $cellSchedules->map(fn($s) => [
                                        'id' => $s->id,
                                        'shift_id' => $s->shift_id,
                                        'is_flexible' => $s->isFlexible(),
                                        'shift_name' => $s->shift?->name ?? 'Ca linh hoạt',
                                        'shift_code' => $s->shift?->code,
                                        'start_time' => substr($s->effectiveShift()?->start_time ?? '', 0, 5),
                                        'end_time' => substr($s->effectiveShift()?->end_time ?? '', 0, 5),
                                        'leave_adjusted' => (bool) ($s->adjusted_start_time || $s->adjusted_end_time),
                                        'is_wfh' => $s->shift ? (bool) $s->shift->isWfh() : (bool) $s->custom_is_wfh,
                                        'custom_start_time' => $s->custom_start_time ? substr($s->custom_start_time, 0, 5) : null,
                                        'custom_end_time' => $s->custom_end_time ? substr($s->custom_end_time, 0, 5) : null,
                                        'custom_break_minutes' => $s->custom_break_minutes,
                                        'custom_is_overnight' => (bool) $s->custom_is_overnight,
                                        'custom_is_wfh' => (bool) $s->custom_is_wfh,
                                        'assignment_type' => $s->assignment_type,
                                        'batch_id' => $s->batch_id,
                                        'note' => $s->note,
                                        'assigned_by' => $s->assignedBy?->name,
                                        'created_at' => $s->created_at?->format('d/m/Y H:i'),
                                        'attendance' => $s->attendanceLog ? [
                                            'id' => $s->attendanceLog->id,
                                            'check_in_at' => $s->attendanceLog->check_in_at?->format('H:i:s'),
                                            'check_out_at' => $s->attendanceLog->check_out_at?->format('H:i:s'),
                                            'late_minutes' => $s->attendanceLog->late_minutes,
                                            'early_minutes' => $s->attendanceLog->early_minutes,
                                            'check_in_method' => $s->attendanceLog->check_in_method,
                                            'check_out_method' => $s->attendanceLog->check_out_method,
                                            'device_changed' => $s->attendanceLog->deviceChanged(),
                                            'overtime_hours' => (float) $s->attendanceLog->overtime_hours,
                                        ] : null,
                                    ])->values();
                                    $isOwnEmployeeCell = $myEmployee && $emp->id === $myEmployee->id;
                                    $overtimeOnlyLog = $overtimeOnlyLogs->get($key, collect())->first();
                                    $canCreateSchedule = auth()->user()->can('create-shift-schedules');
                                @endphp
                                <td class="table-td text-center sched-td @if($day->isWeekend()) sched-td-weekend @endif @if($day->isToday()) sched-td-today @endif">
                                    @if($cellSchedules->isEmpty())
                                        <div class="{{ $overtimeOnlyLog ? 'sched-cell-ot-only' : '' }}">
                                            @if($canCreateSchedule)
                                                <button type="button"
                                                    onclick="openAssignModal({{ $emp->id }}, {{ Illuminate\Support\Js::from($emp->name) }}, '{{ $day->toDateString() }}', null, null, null, false, null, null, null, false, false)"
                                                    class="sched-cell-empty">
                                                    <i class="bi bi-plus-lg"></i> Xếp ca
                                                </button>
                                            @elseif(!$overtimeOnlyLog)
                                                <span class="text-xs text-slate-300 dark:text-slate-600">—</span>
                                            @endif
                                            @if($overtimeOnlyLog)
                                                @php $otHours = rtrim(rtrim(number_format($overtimeOnlyLog->overtime_hours, 2, '.', ''), '0'), '.'); @endphp
                                                <span class="sched-ot-badge sched-ot-badge-standalone"
                                                    title="Tăng ca đã duyệt vào ngày không có ca xếp: +{{ $otHours }} giờ — đã cộng vào công. Xem chi tiết trong Yêu cầu & Phê duyệt.">
                                                    +{{ $otHours }}h TC
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <button type="button"
                                            onclick="openDayDetailModal({{ $emp->id }}, {{ Illuminate\Support\Js::from($emp->name) }}, '{{ $day->toDateString() }}', {{ Illuminate\Support\Js::from($day->format('d/m/Y')) }}, {{ Illuminate\Support\Js::from($cellData) }}, { isOwnEmployee: {{ $isOwnEmployeeCell ? 'true' : 'false' }}, dayIsFutureOrToday: {{ $day->gte(today()) ? 'true' : 'false' }} })"
                                            class="sched-cell">
                                            @if($cellSchedules->count() >= 2)
                                                <span class="sched-multi-count">{{ $cellSchedules->count() }} ca</span>
                                                <div class="sched-multi-list">
                                                    @foreach($cellSchedules as $s)
                                                        @php
                                                            $log = $s->attendanceLog;
                                                            $checkInDot = !$log?->check_in_at
                                                                ? 'sched-dot-pending'
                                                                : ($log->late_minutes > 0 ? 'sched-dot-late' : 'sched-dot-ontime');
                                                            $checkInTitle = !$log?->check_in_at
                                                                ? 'Chưa check-in'
                                                                : ($log->late_minutes > 0 ? "Check-in trễ {$log->late_minutes} phút" : 'Check-in đúng giờ');
                                                            $checkOutDot = !$log?->check_out_at
                                                                ? 'sched-dot-pending'
                                                                : ($log->early_minutes > 0 ? 'sched-dot-late' : 'sched-dot-ontime');
                                                            $checkOutTitle = !$log?->check_out_at
                                                                ? 'Chưa check-out'
                                                                : ($log->early_minutes > 0 ? "Check-out sớm {$log->early_minutes} phút" : 'Check-out đúng giờ');
                                                        @endphp
                                                        <div class="sched-multi-row">
                                                            <span class="sched-multi-time">{{ substr($s->effectiveShift()?->start_time ?? '', 0, 5) }}–{{ substr($s->effectiveShift()?->end_time ?? '', 0, 5) }}</span>
                                                            <span class="sched-actual-time">{{ $log?->check_in_at?->format('H:i') ?? '--:--' }}–{{ $log?->check_out_at?->format('H:i') ?? '--:--' }}</span>
                                                            @if($s->adjusted_start_time || $s->adjusted_end_time)
                                                                <i class="bi bi-calendar-minus text-amber-500" title="Đã điều chỉnh giờ do nghỉ phép theo giờ được duyệt"></i>
                                                            @endif
                                                            <span class="sched-dot-group">
                                                                <span class="sched-dot {{ $checkInDot }}" title="{{ $checkInTitle }}"></span>
                                                                <span class="sched-dot {{ $checkOutDot }}" title="{{ $checkOutTitle }}"></span>
                                                            </span>
                                                            @if($log && $log->overtime_hours > 0)
                                                                <span class="sched-ot-badge" title="Tăng ca đã duyệt: {{ rtrim(rtrim(number_format($log->overtime_hours, 2, '.', ''), '0'), '.') }} giờ">
                                                                    +{{ rtrim(rtrim(number_format($log->overtime_hours, 2, '.', ''), '0'), '.') }}h TC
                                                                </span>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                @php
                                                    $s = $cellSchedules->first();
                                                    $log = $s->attendanceLog;
                                                    $checkInDot = !$log?->check_in_at
                                                        ? 'sched-dot-pending'
                                                        : ($log->late_minutes > 0 ? 'sched-dot-late' : 'sched-dot-ontime');
                                                    $checkInTitle = !$log?->check_in_at
                                                        ? 'Chưa check-in'
                                                        : ($log->late_minutes > 0 ? "Check-in trễ {$log->late_minutes} phút" : 'Check-in đúng giờ');
                                                    $checkOutDot = !$log?->check_out_at
                                                        ? 'sched-dot-pending'
                                                        : ($log->early_minutes > 0 ? 'sched-dot-late' : 'sched-dot-ontime');
                                                    $checkOutTitle = !$log?->check_out_at
                                                        ? 'Chưa check-out'
                                                        : ($log->early_minutes > 0 ? "Check-out sớm {$log->early_minutes} phút" : 'Check-out đúng giờ');
                                                @endphp
                                                <span class="sched-chip-badge" style="font-family: var(--font-heading, 'Bricolage Grotesque', sans-serif) !important;">{{ $s->shift?->code ?? 'LH' }}</span>
                                                <span class="sched-cell-time">{{ substr($s->effectiveShift()?->start_time ?? '', 0, 5) }}–{{ substr($s->effectiveShift()?->end_time ?? '', 0, 5) }}</span>
                                                <span class="sched-actual-time">{{ $log?->check_in_at?->format('H:i') ?? '--:--' }}–{{ $log?->check_out_at?->format('H:i') ?? '--:--' }}</span>
                                                @if($s->adjusted_start_time || $s->adjusted_end_time)
                                                    <i class="bi bi-calendar-minus text-amber-500" title="Đã điều chỉnh giờ do nghỉ phép theo giờ được duyệt"></i>
                                                @endif
                                                <span class="sched-dot-group">
                                                    <span class="sched-dot {{ $checkInDot }}" title="{{ $checkInTitle }}"></span>
                                                    <span class="sched-dot {{ $checkOutDot }}" title="{{ $checkOutTitle }}"></span>
                                                </span>
                                                @if($log && $log->overtime_hours > 0)
                                                    <span class="sched-ot-badge" title="Tăng ca đã duyệt: {{ rtrim(rtrim(number_format($log->overtime_hours, 2, '.', ''), '0'), '.') }} giờ">
                                                        +{{ rtrim(rtrim(number_format($log->overtime_hours, 2, '.', ''), '0'), '.') }}h TC
                                                    </span>
                                                @endif
                                            @endif
                                        </button>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ $days->count() + 1 }}" class="table-td text-center py-8 text-slate-400">
                                <i class="bi bi-people text-3xl mb-2 block opacity-40"></i>
                                <p>Không có nhân viên phù hợp bộ lọc</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Mobile Layout (List of employee schedule cards with Accordion) -->
            <div class="block lg:hidden space-y-3">
                @forelse($sortedEmployees as $emp)
                    @php
                        $isOwn = $myEmployee && $emp->id === $myEmployee->id;
                        $hasSameTeam = $myEmployee && $emp->team_id === $myEmployee->team_id && !$isOwn;
                    @endphp
                    <div class="bg-white dark:bg-slate-800/80 rounded-xl shadow-sm border border-slate-100 dark:border-slate-750/70 overflow-hidden">
                        <!-- Accordion Header -->
                        <div onclick="toggleEmployeeAccordion({{ $emp->id }})"
                             class="flex items-center justify-between p-4 cursor-pointer hover:bg-slate-50/50 dark:hover:bg-slate-750/30 transition-colors select-none">
                            <div class="flex items-center gap-2">
                                @if($isOwn)
                                    <span class="w-2.5 h-2.5 rounded-full bg-pcrm-600 animate-pulse" title="Tôi"></span>
                                @elseif($hasSameTeam)
                                    <span class="w-2 h-2 rounded-full bg-emerald-500" title="Cùng team"></span>
                                @endif
                                <div>
                                    <h4 class="sched-emp-name font-bold text-slate-800 dark:text-slate-100 text-sm flex items-center gap-1.5" style="font-family: var(--font-heading, 'Bricolage Grotesque', sans-serif) !important;">
                                        {{ $emp->name }}
                                        @if($isOwn)
                                            <span class="text-[10px] py-0.5 px-1.5 rounded-full bg-pcrm-100 text-pcrm-750 dark:bg-pcrm-900/40 dark:text-pcrm-400 font-bold">Tôi</span>
                                        @elseif($hasSameTeam)
                                            <span class="text-[10px] py-0.5 px-1.5 rounded-full bg-emerald-100 text-emerald-750 dark:bg-emerald-900/40 dark:text-emerald-400 font-bold font-sans">Team</span>
                                        @endif
                                    </h4>
                                    <p class="text-xs text-slate-400 mt-0.5">{{ $emp->team?->name ?? '—' }}</p>
                                </div>
                            </div>
                            <div class="flex items-center">
                                <i id="emp-chevron-{{ $emp->id }}" class="bi {{ $isOwn ? 'bi-chevron-up' : 'bi-chevron-down' }} text-slate-400 text-sm"></i>
                            </div>
                        </div>

                        <!-- Accordion Content -->
                        <div id="emp-days-{{ $emp->id }}" class="{{ $isOwn ? '' : 'hidden' }} border-t border-slate-100 dark:border-slate-700/80 p-3 space-y-2 bg-slate-50/20 dark:bg-slate-900/10">
                            @foreach($days as $day)
                                @php
                                    $key = $emp->id . '_' . $day->toDateString();
                                    $cellSchedules = $schedules->get($key, collect());
                                    $cellData = $cellSchedules->map(fn($s) => [
                                        'id' => $s->id,
                                        'shift_id' => $s->shift_id,
                                        'is_flexible' => $s->isFlexible(),
                                        'shift_name' => $s->shift?->name ?? 'Ca linh hoạt',
                                        'shift_code' => $s->shift?->code,
                                        'start_time' => substr($s->effectiveShift()?->start_time ?? '', 0, 5),
                                        'end_time' => substr($s->effectiveShift()?->end_time ?? '', 0, 5),
                                        'leave_adjusted' => (bool) ($s->adjusted_start_time || $s->adjusted_end_time),
                                        'is_wfh' => $s->shift ? (bool) $s->shift->isWfh() : (bool) $s->custom_is_wfh,
                                        'custom_start_time' => $s->custom_start_time ? substr($s->custom_start_time, 0, 5) : null,
                                        'custom_end_time' => $s->custom_end_time ? substr($s->custom_end_time, 0, 5) : null,
                                        'custom_break_minutes' => $s->custom_break_minutes,
                                        'custom_is_overnight' => (bool) $s->custom_is_overnight,
                                        'custom_is_wfh' => (bool) $s->custom_is_wfh,
                                        'assignment_type' => $s->assignment_type,
                                        'batch_id' => $s->batch_id,
                                        'note' => $s->note,
                                        'assigned_by' => $s->assignedBy?->name,
                                        'created_at' => $s->created_at?->format('d/m/Y H:i'),
                                        'attendance' => $s->attendanceLog ? [
                                            'id' => $s->attendanceLog->id,
                                            'check_in_at' => $s->attendanceLog->check_in_at?->format('H:i:s'),
                                            'check_out_at' => $s->attendanceLog->check_out_at?->format('H:i:s'),
                                            'late_minutes' => $s->attendanceLog->late_minutes,
                                            'early_minutes' => $s->attendanceLog->early_minutes,
                                            'check_in_method' => $s->attendanceLog->check_in_method,
                                            'check_out_method' => $s->attendanceLog->check_out_method,
                                            'device_changed' => $s->attendanceLog->deviceChanged(),
                                            'overtime_hours' => (float) $s->attendanceLog->overtime_hours,
                                        ] : null,
                                    ])->values();
                                    $isOwnEmployeeCell = $myEmployee && $emp->id === $myEmployee->id;
                                    $overtimeOnlyLog = $overtimeOnlyLogs->get($key, collect())->first();
                                    $canCreateSchedule = auth()->user()->can('create-shift-schedules');
                                @endphp

                                <div class="flex items-center justify-between p-2.5 rounded-lg bg-slate-50 dark:bg-slate-900/40 @if($day->isToday()) border border-pcrm-200 dark:border-pcrm-800 bg-pcrm-50/10 dark:bg-pcrm-950/5 @endif">
                                    <div class="flex flex-col">
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-xs font-bold text-slate-700 dark:text-slate-350">{{ ['CN','Thứ 2','Thứ 3','Thứ 4','Thứ 5','Thứ 6','Thứ 7'][$day->dayOfWeek] }}</span>
                                            <span class="text-[10px] text-slate-400">({{ $day->format('d/m') }})</span>
                                            @if($day->isToday())
                                                <span class="px-1 py-0.5 text-[8px] font-black uppercase rounded bg-pcrm-100 text-pcrm-700 dark:bg-pcrm-900/40 dark:text-pcrm-400">Hôm nay</span>
                                            @endif
                                        </div>

                                        <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                            @if($cellSchedules->isEmpty())
                                                @if($overtimeOnlyLog)
                                                    @php $otHours = rtrim(rtrim(number_format($overtimeOnlyLog->overtime_hours, 2, '.', ''), '0'), '.'); @endphp
                                                    <span class="text-[10px] text-slate-400 font-medium">Không xếp ca</span>
                                                    <span class="sched-ot-badge !static !h-auto !py-0.5 !px-1.5"
                                                        title="Tăng ca đã duyệt: +{{ $otHours }} giờ">
                                                        +{{ $otHours }}h TC
                                                    </span>
                                                @else
                                                    <span class="text-[10px] text-slate-300 dark:text-slate-650 font-medium">—</span>
                                                @endif
                                            @else
                                                @if($cellSchedules->count() >= 2)
                                                    <span class="sched-chip-badge !py-0.5 !px-1.5" style="font-family: var(--font-heading, 'Bricolage Grotesque', sans-serif) !important;">{{ $cellSchedules->count() }} ca</span>
                                                    @foreach($cellSchedules as $s)
                                                        @php
                                                            $log = $s->attendanceLog;
                                                            $checkInDot = !$log?->check_in_at ? 'sched-dot-pending' : ($log->late_minutes > 0 ? 'sched-dot-late' : 'sched-dot-ontime');
                                                            $checkOutDot = !$log?->check_out_at ? 'sched-dot-pending' : ($log->early_minutes > 0 ? 'sched-dot-late' : 'sched-dot-ontime');
                                                        @endphp
                                                        <span class="text-[9px] text-slate-500 font-medium flex items-center gap-1">
                                                            {{ substr($s->effectiveShift()?->start_time ?? '', 0, 5) }}–{{ substr($s->effectiveShift()?->end_time ?? '', 0, 5) }}
                                                            <span class="sched-dot-group !static flex gap-0.5">
                                                                <span class="sched-dot {{ $checkInDot }}"></span>
                                                                <span class="sched-dot {{ $checkOutDot }}"></span>
                                                            </span>
                                                        </span>
                                                    @endforeach
                                                @else
                                                    @php
                                                        $s = $cellSchedules->first();
                                                        $log = $s->attendanceLog;
                                                        $checkInDot = !$log?->check_in_at ? 'sched-dot-pending' : ($log->late_minutes > 0 ? 'sched-dot-late' : 'sched-dot-ontime');
                                                        $checkOutDot = !$log?->check_out_at ? 'sched-dot-pending' : ($log->early_minutes > 0 ? 'sched-dot-late' : 'sched-dot-ontime');
                                                    @endphp
                                                    <span class="sched-chip-badge !py-0.5 !px-1.5" style="font-family: var(--font-heading, 'Bricolage Grotesque', sans-serif) !important;">{{ $s->shift?->code ?? 'LH' }}</span>
                                                    <span class="text-[10px] text-slate-500 font-medium">{{ substr($s->effectiveShift()?->start_time ?? '', 0, 5) }}–{{ substr($s->effectiveShift()?->end_time ?? '', 0, 5) }}</span>
                                                    <span class="sched-dot-group !static flex gap-0.5">
                                                        <span class="sched-dot {{ $checkInDot }}"></span>
                                                        <span class="sched-dot {{ $checkOutDot }}"></span>
                                                    </span>
                                                    @if($log && $log->overtime_hours > 0)
                                                        <span class="sched-ot-badge !static !h-auto !py-0.5 !px-1.5">
                                                            +{{ rtrim(rtrim(number_format($log->overtime_hours, 2, '.', ''), '0'), '.') }}h TC
                                                        </span>
                                                    @endif
                                                @endif
                                            @endif
                                        </div>
                                    </div>

                                    <div>
                                        @if($cellSchedules->isEmpty())
                                            @if($canCreateSchedule)
                                                <button type="button"
                                                    onclick="openAssignModal({{ $emp->id }}, {{ Illuminate\Support\Js::from($emp->name) }}, '{{ $day->toDateString() }}', null, null, null, false, null, null, null, false, false)"
                                                    class="py-1 px-2.5 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 text-[10px] font-bold transition-colors">
                                                    + Xếp ca
                                                </button>
                                            @endif
                                        @else
                                            <button type="button"
                                                onclick="openDayDetailModal({{ $emp->id }}, {{ Illuminate\Support\Js::from($emp->name) }}, '{{ $day->toDateString() }}', {{ Illuminate\Support\Js::from($day->format('d/m/Y')) }}, {{ Illuminate\Support\Js::from($cellData) }}, { isOwnEmployee: {{ $isOwnEmployeeCell ? 'true' : 'false' }}, dayIsFutureOrToday: {{ $day->gte(today()) ? 'true' : 'false' }} })"
                                                class="py-1 px-2.5 rounded-lg bg-pcrm-50 text-pcrm-700 dark:bg-pcrm-900/30 dark:text-pcrm-400 text-[10px] font-bold transition-colors">
                                                Chi tiết
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8 text-slate-400 bg-white dark:bg-slate-800 rounded-xl border border-slate-100 dark:border-slate-700/60">
                        <i class="bi bi-people text-3xl mb-2 block opacity-40"></i>
                        <p class="text-sm">Không có nhân viên phù hợp bộ lọc</p>
                    </div>
                @endforelse
            </div>
            {{-- Kết thúc VIEW 1: Lưới tuần --}}
        </div>

        {{-- VIEW 2: BẢNG TUẦN NGẮN GỌN (COMPACT TABLE) --}}
        <div id="scheduleTableView" role="tabpanel" aria-labelledby="viewBtnTable" class="{{ $viewMode === 'table' ? '' : 'hidden' }}">
            @include('shift-schedules.partials.table-view')
        </div>

        {{-- VIEW 3: DANH SÁCH THEO NGÀY (COMPACT LIST VIEW) --}}
        <div id="scheduleListView" role="tabpanel" aria-labelledby="viewBtnList" class="{{ $viewMode === 'list' ? '' : 'hidden' }}">
            @include('shift-schedules.partials.list-view')
        </div>
    </div>
</form>

@endsection

@push('modals')
    @include('shift-schedules.partials.assign-modal')
    @include('shift-schedules.partials.bulk-assign-modal')
    @include('shift-schedules.partials.swap-request-modal')
    @include('shift-schedules.partials.on-shift-modal')
    @include('components.shift-day-detail-modal')
    @can('delete-shift-schedules')
        @include('shift-schedules.partials.delete-modal')
    @endcan
    <x-export-range-modal id="exportShiftSchedulesModal" title="Xuất Excel — Xếp ca"
        :export-url="route('shift-schedules.export')" :ref-date="$weekStart"
        :hidden="['branch_id' => request('branch_id'), 'team_id' => request('team_id'), 'employee_id' => request('employee_id')]" />

    <!-- Overlay for filter drawer -->
    <div id="filterDrawerOverlay" class="fixed inset-0 bg-black/40 z-40 hidden opacity-0 transition-opacity duration-300 pointer-events-none" onclick="toggleFilterDrawer(false)"></div>

    <!-- Right-Side Filter Drawer -->
    <aside id="filterDrawer" class="fixed inset-y-0 right-0 z-50 w-full max-w-xs sm:max-w-sm bg-white dark:bg-slate-900 border-l border-slate-200 dark:border-slate-800 shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between flex-shrink-0">
            <h3 class="text-sm font-black text-slate-855 dark:text-slate-100 uppercase tracking-wide flex items-center gap-1.5">
                <i class="bi bi-funnel text-pcrm-600"></i> Bộ lọc tìm kiếm
            </h3>
            <button type="button" onclick="toggleFilterDrawer(false)" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-750 transition-colors">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form action="{{ route('shift-schedules.index') }}" method="GET" class="flex-1 flex flex-col overflow-y-auto">
            @if(request('week'))<input type="hidden" name="week" value="{{ request('week') }}">@endif
            @if(request('no_shift_today'))<input type="hidden" name="no_shift_today" value="{{ request('no_shift_today') }}">@endif
            <input type="hidden" name="view" value="{{ $viewMode }}">
            <div class="p-5 space-y-4 flex-1">
                <div>
                    <label class="block text-xs font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wider mb-2">Chi nhánh</label>
                    <select name="branch_id" class="form-input text-sm w-full">
                        <option value="">Tất cả</option>
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}" @selected(request('branch_id') == $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wider mb-2">Đội nhóm</label>
                    <select name="team_id" class="form-input text-sm w-full">
                        <option value="">Tất cả</option>
                        @foreach($teams as $t)
                            <option value="{{ $t->id }}" @selected(request('team_id') == $t->id)>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-employee-combobox name="employee_id" :employees="$allEmployees" :selected="request('employee_id')"
                        label="Nhân viên" placeholder="Xem ca của nhân viên..." :compact="true" />
                </div>
            </div>
            <div class="p-5 border-t border-slate-100 dark:border-slate-800/80 flex items-center gap-3 bg-slate-50/50 dark:bg-slate-950/20 flex-shrink-0">
                <button type="button" onclick="resetFilters()" class="btn-secondary flex-1 py-2.5 px-3 text-xs font-bold">Đặt lại</button>
                <button type="submit" class="btn-primary flex-1 py-2.5 px-3 text-xs font-bold">Áp dụng</button>
            </div>
        </form>
    </aside>
@endpush

@push('scripts')
<script>
window.SCHED_PERMS = {
    canEdit: @json(auth()->user()->can('edit-shift-schedules')),
    canDelete: @json(auth()->user()->can('delete-shift-schedules')),
    canCreate: @json(auth()->user()->can('create-shift-schedules')),
    canSwap: @json(auth()->user()->can('create-shift-swaps')),
    hasUpcoming: @json($myUpcomingSchedules->isNotEmpty()),
    canEditAttendance: @json(auth()->user()->can('edit-attendance-logs')),
    canCreateAttendance: @json(auth()->user()->can('create-attendance-logs')),
};

function switchScheduleView(mode) {
    ['matrix', 'table', 'list'].forEach(function(m) {
        const cap = m.charAt(0).toUpperCase() + m.slice(1);
        const panel = document.getElementById('schedule' + cap + 'View');
        const btn = document.getElementById('viewBtn' + cap);
        if (panel) panel.classList.toggle('hidden', m !== mode);
        if (btn) {
            btn.classList.toggle('is-active', m === mode);
            btn.setAttribute('aria-selected', m === mode ? 'true' : 'false');
        }
    });
    const inputView = document.getElementById('schedCurrentViewInput');
    if (inputView) inputView.value = mode;

    try {
        const url = new URL(window.location.href);
        url.searchParams.set('view', mode);
        window.history.replaceState({}, '', url.toString());

        // Cập nhật cả hidden input trong filter drawer nếu có
        const drawerForm = document.querySelector('#filterDrawer form');
        if (drawerForm) {
            let drawerInput = drawerForm.querySelector('input[name="view"]');
            if (drawerInput) drawerInput.value = mode;
        }
    } catch (e) {}
}

function filterScheduleListByDay(dayStr, btn) {
    document.querySelectorAll('.sched-day-pill').forEach(function(p) {
        p.classList.remove('is-active');
    });
    if (btn) {
        btn.classList.add('is-active');
    }

    const rows = document.querySelectorAll('.sched-list-row');
    rows.forEach(function(row) {
        if (dayStr === 'all' || row.getAttribute('data-day') === dayStr) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function openAssignModal(employeeId, employeeName, workDate, scheduleId, currentShiftId, currentNote,
    isFlexible, customStartTime, customEndTime, customBreakMinutes, customIsOvernight, customIsWfh) {
    const form = document.getElementById('assignShiftForm');
    document.getElementById('assignEditId').value = scheduleId ?? '';
    document.getElementById('assignEmployeeId').value = employeeId;
    document.getElementById('assignEmployeeLabel').textContent = employeeName + ' — ' + workDate;
    document.getElementById('assignWorkDate').value = workDate;
    document.getElementById('assignShiftId').value = currentShiftId ?? '';
    document.getElementById('assignNote').value = currentNote ?? '';

    document.getElementById('assignModeTemplate').checked = !isFlexible;
    document.getElementById('assignModeFlexible').checked = !!isFlexible;
    document.getElementById('assignCustomStartTime').value = customStartTime ?? '';
    document.getElementById('assignCustomEndTime').value = customEndTime ?? '';
    document.getElementById('assignCustomBreakMinutes').value = customBreakMinutes ?? '';
    document.getElementById('assignCustomIsOvernight').checked = !!customIsOvernight;
    document.getElementById('assignCustomIsWfh').checked = !!customIsWfh;
    toggleAssignMode();

    if (scheduleId) {
        form.action = '/shift-schedules/' + scheduleId;
        document.getElementById('assignMethodField').value = 'PUT';
        document.getElementById('assignModalTitle').textContent = 'Sửa ca';
    } else {
        form.action = '{{ route('shift-schedules.store') }}';
        document.getElementById('assignMethodField').value = '';
        document.getElementById('assignModalTitle').textContent = 'Xếp ca (đa ca)';
    }

    openModal('assignShiftModal');
}

function openSwapModal(targetScheduleId, targetEmployeeName, dateLabel, shiftLabel) {
    document.getElementById('swapTargetScheduleId').value = targetScheduleId;
    document.getElementById('swapTargetLabel').textContent =
        'Đổi ca với ' + targetEmployeeName + ' — ' + dateLabel + (shiftLabel ? ' (' + shiftLabel + ')' : '');
    openModal('swapRequestModal');
}

@if($errors->any() && old('_modal'))
document.addEventListener('DOMContentLoaded', function() {
    @if(old('_modal') === 'assignShiftModal')
    openAssignModal(
        '{{ old("employee_id") }}',
        {{ Illuminate\Support\Js::from(optional($allEmployees->firstWhere('id', (int) old('employee_id')))->name ?? '') }},
        '{{ old("work_date") }}',
        {{ old('_edit_id') ? "'" . old('_edit_id') . "'" : 'null' }},
        '{{ old("shift_id") }}',
        {{ Illuminate\Support\Js::from(old('note')) }},
        {{ old('shift_id') ? 'false' : 'true' }},
        {{ Illuminate\Support\Js::from(old('custom_start_time')) }},
        {{ Illuminate\Support\Js::from(old('custom_end_time')) }},
        {{ Illuminate\Support\Js::from(old('custom_break_minutes')) }},
        {{ old('custom_is_overnight') ? 'true' : 'false' }},
        {{ old('custom_is_wfh') ? 'true' : 'false' }}
    );
    @else
    openModal('{{ old("_modal") }}');
    @endif
});
@endif
function toggleFilterDrawer(open) {
    const drawer = document.getElementById('filterDrawer');
    const overlay = document.getElementById('filterDrawerOverlay');
    if (open) {
        overlay.classList.remove('hidden');
        overlay.offsetHeight; // trigger reflow
        overlay.classList.add('opacity-100', 'pointer-events-auto');
        drawer.classList.remove('translate-x-full');
    } else {
        overlay.classList.remove('opacity-100', 'pointer-events-auto');
        drawer.classList.add('translate-x-full');
        setTimeout(() => {
            if (drawer.classList.contains('translate-x-full')) {
                overlay.classList.add('hidden');
            }
        }, 300);
    }
}

function resetFilters() {
    const drawer = document.getElementById('filterDrawer');
    const inputs = drawer.querySelectorAll('input, select');
    inputs.forEach(input => {
        if (input.type === 'text' || input.type === 'date' || input.type === 'hidden') {
            if (input.name !== 'week' && input.name !== 'view') {
                input.value = '';
            }
        } else if (input.tagName === 'SELECT') {
            input.selectedIndex = 0;
        }
    });
    toggleFilterDrawer(false);
    drawer.querySelector('form').submit();
}
function toggleEmployeeAccordion(id) {
    const content = document.getElementById('emp-days-' + id);
    const chevron = document.getElementById('emp-chevron-' + id);
    if (content.classList.contains('hidden')) {
        content.classList.remove('hidden');
        chevron.classList.remove('bi-chevron-down');
        chevron.classList.add('bi-chevron-up');
    } else {
        content.classList.add('hidden');
        chevron.classList.remove('bi-chevron-up');
        chevron.classList.add('bi-chevron-down');
    }
}
</script>
@endpush
