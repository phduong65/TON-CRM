@extends('layouts.admin')

@section('title', 'Xếp ca')
@section('page-title', 'Xếp ca làm việc')
@section('breadcrumb', 'Ca làm việc & Chấm công')

@section('content')
    <div class="page-header">
        <div>
            <p class="page-subtitle">Xếp ca cố định (hàng loạt) hoặc đa ca (từng ngày) cho nhân viên</p>
        </div>
        <div class="flex items-center gap-2">
            @can('view-attendance')
            <button type="button" onclick="openOnShiftModal()" class="btn-secondary">
                <i class="bi bi-person-badge-fill"></i>
                <span>Nhân viên đang trong ca</span>
            </button>
            @endcan
            @can('export-shift-schedules')
            <button onclick="openModal('exportShiftSchedulesModal')" class="btn-secondary">
                <i class="bi bi-file-earmark-excel"></i>
                <span>Xuất Excel</span>
            </button>
            @endcan
            @can('create-shift-schedules')
            <button onclick="openModal('bulkAssignModal')" class="btn-primary">
                <i class="bi bi-calendar-plus"></i>
                <span>Xếp ca cố định</span>
            </button>
            @endcan
        </div>
    </div>

    <div class="card mb-4">
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700">
            <form action="{{ route('shift-schedules.index') }}" method="GET" class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Tuần</label>
                    <input type="date" name="week" value="{{ $weekStart->toDateString() }}" class="form-input h-9 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Chi nhánh</label>
                    <select name="branch_id" class="form-input h-9 text-sm min-w-[160px]">
                        <option value="">Tất cả</option>
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}" @selected(request('branch_id') == $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Đội nhóm</label>
                    <select name="team_id" class="form-input h-9 text-sm min-w-[160px]">
                        <option value="">Tất cả</option>
                        @foreach($teams as $t)
                            <option value="{{ $t->id }}" @selected(request('team_id') == $t->id)>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-[220px]">
                    <x-employee-combobox name="employee_id" :employees="$allEmployees" :selected="request('employee_id')"
                        label="Nhân viên" placeholder="Xem ca của nhân viên..." />
                </div>
                <button type="submit" class="btn-primary h-9 px-4 text-sm gap-1.5">
                    <i class="bi bi-funnel text-xs"></i> Lọc
                </button>
                @if(request()->anyFilled(['branch_id', 'team_id', 'employee_id']))
                <a href="{{ route('shift-schedules.index', ['week' => $weekStart->toDateString()]) }}"
                   class="btn-secondary h-9 px-3 inline-flex items-center gap-1 text-sm">
                    <i class="bi bi-x text-sm"></i>
                </a>
                @endif
            </form>
        </div>

        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-2 text-[11px] text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-700">
            <span class="font-medium text-slate-400 dark:text-slate-500">Chấm công (check-in · check-out):</span>
            <span class="inline-flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-slate-400 dark:bg-slate-400"></span> Chưa chấm công</span>
            <span class="inline-flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Đúng giờ</span>
            <span class="inline-flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Trễ / về sớm</span>
        </div>

        <div class="card-body p-0">
            <div class="table-container border-0 rounded-none overflow-x-auto">
                <table class="table-base min-w-[900px]">
                    <thead>
                        <tr>
                            <th class="table-th sticky left-0 bg-white dark:bg-slate-800">Nhân viên</th>
                            @foreach($days as $day)
                                <th class="table-th text-center">
                                    {{ ['CN','T2','T3','T4','T5','T6','T7'][$day->dayOfWeek] }}<br>
                                    <span class="text-slate-400 font-normal">{{ $day->format('d/m') }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employees as $emp)
                        <tr class="table-tr-hover">
                            <td class="table-td font-medium sticky left-0 bg-white dark:bg-slate-800">
                                {{ $emp->name }}
                                <p class="text-xs text-slate-400">{{ $emp->team?->name ?? '—' }}</p>
                            </td>
                            @foreach($days as $day)
                                @php
                                    $key = $emp->id . '_' . $day->toDateString();
                                    $cellSchedules = $schedules->get($key, collect());
                                    $cellData = $cellSchedules->map(fn($s) => [
                                        'id' => $s->id,
                                        'shift_id' => $s->shift_id,
                                        'shift_name' => $s->shift?->name,
                                        'shift_code' => $s->shift?->code,
                                        'start_time' => substr($s->shift?->start_time ?? '', 0, 5),
                                        'end_time' => substr($s->shift?->end_time ?? '', 0, 5),
                                        'is_wfh' => (bool) $s->shift?->isWfh(),
                                        'assignment_type' => $s->assignment_type,
                                        'batch_id' => $s->batch_id,
                                        'note' => $s->note,
                                        'assigned_by' => $s->assignedBy?->name,
                                        'created_at' => $s->created_at?->format('d/m/Y H:i'),
                                        'attendance' => $s->attendanceLog ? [
                                            'check_in_at' => $s->attendanceLog->check_in_at?->format('H:i:s'),
                                            'check_out_at' => $s->attendanceLog->check_out_at?->format('H:i:s'),
                                            'late_minutes' => $s->attendanceLog->late_minutes,
                                            'early_minutes' => $s->attendanceLog->early_minutes,
                                            'check_in_method' => $s->attendanceLog->check_in_method,
                                            'check_out_method' => $s->attendanceLog->check_out_method,
                                        ] : null,
                                    ])->values();
                                    $isOwnEmployeeCell = $myEmployee && $emp->id === $myEmployee->id;
                                @endphp
                                <td class="table-td text-center">
                                    @if($cellSchedules->isEmpty() && !auth()->user()->can('create-shift-schedules'))
                                        <span class="text-xs text-slate-300">—</span>
                                    @elseif($cellSchedules->isEmpty())
                                        <button type="button"
                                            onclick="openAssignModal({{ $emp->id }}, {{ Illuminate\Support\Js::from($emp->name) }}, '{{ $day->toDateString() }}', null, null, null)"
                                            class="w-full min-w-[80px] min-h-[60px] flex items-center justify-center px-2 py-1.5 rounded-lg text-xs font-medium transition-colors bg-slate-50 text-slate-400 hover:bg-slate-100 dark:bg-slate-700/40 dark:hover:bg-slate-700">
                                            + Xếp ca
                                        </button>
                                    @else
                                        <button type="button"
                                            onclick="openDayDetailModal({{ $emp->id }}, {{ Illuminate\Support\Js::from($emp->name) }}, '{{ $day->toDateString() }}', {{ Illuminate\Support\Js::from($day->format('d/m/Y')) }}, {{ Illuminate\Support\Js::from($cellData) }}, { isOwnEmployee: {{ $isOwnEmployeeCell ? 'true' : 'false' }}, dayIsFutureOrToday: {{ $day->gte(today()) ? 'true' : 'false' }} })"
                                            class="w-full min-w-[150px] min-h-[60px] flex items-center justify-center px-2 py-1.5 rounded-lg text-xs font-medium transition-colors bg-pcrm-50 text-pcrm-700 dark:bg-pcrm-900/20 dark:text-pcrm-400 hover:bg-pcrm-100">
                                            @if($cellSchedules->count() >= 2)
                                                <div class="space-y-1 leading-tight">
                                                    <div>{{ $cellSchedules->count() }} ca</div>
                                                    <div class="flex flex-wrap items-start justify-center gap-x-1 gap-y-1">
                                                        @foreach($cellSchedules as $i => $s)
                                                            @if($i > 0)
                                                                <span class="text-[10px] text-slate-400 dark:text-slate-500 pt-px">-</span>
                                                            @endif
                                                            @php
                                                                $log = $s->attendanceLog;
                                                                $checkInDot = !$log?->check_in_at
                                                                    ? 'bg-slate-400 dark:bg-slate-400'
                                                                    : ($log->late_minutes > 0 ? 'bg-red-500' : 'bg-emerald-500');
                                                                $checkInTitle = !$log?->check_in_at
                                                                    ? 'Chưa check-in'
                                                                    : ($log->late_minutes > 0 ? "Check-in trễ {$log->late_minutes} phút" : 'Check-in đúng giờ');
                                                                $checkOutDot = !$log?->check_out_at
                                                                    ? 'bg-slate-400 dark:bg-slate-400'
                                                                    : ($log->early_minutes > 0 ? 'bg-red-500' : 'bg-emerald-500');
                                                                $checkOutTitle = !$log?->check_out_at
                                                                    ? 'Chưa check-out'
                                                                    : ($log->early_minutes > 0 ? "Check-out sớm {$log->early_minutes} phút" : 'Check-out đúng giờ');
                                                            @endphp
                                                            <div class="flex flex-col items-center gap-0.5">
                                                                <span class="text-[10px] font-normal text-slate-400 dark:text-slate-500 whitespace-nowrap">
                                                                    ({{ substr($s->shift?->start_time ?? '', 0, 5) }} - {{ substr($s->shift?->end_time ?? '', 0, 5) }})
                                                                </span>
                                                                <span class="flex items-center gap-0.5">
                                                                    <span class="w-1.5 h-1.5 rounded-full {{ $checkInDot }}" title="{{ $checkInTitle }}"></span>
                                                                    <span class="w-1.5 h-1.5 rounded-full {{ $checkOutDot }}" title="{{ $checkOutTitle }}"></span>
                                                                </span>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @else
                                                @php
                                                    $s = $cellSchedules->first();
                                                    $log = $s->attendanceLog;
                                                    $checkInDot = !$log?->check_in_at
                                                        ? 'bg-slate-400 dark:bg-slate-400'
                                                        : ($log->late_minutes > 0 ? 'bg-red-500' : 'bg-emerald-500');
                                                    $checkInTitle = !$log?->check_in_at
                                                        ? 'Chưa check-in'
                                                        : ($log->late_minutes > 0 ? "Check-in trễ {$log->late_minutes} phút" : 'Check-in đúng giờ');
                                                    $checkOutDot = !$log?->check_out_at
                                                        ? 'bg-slate-400 dark:bg-slate-400'
                                                        : ($log->early_minutes > 0 ? 'bg-red-500' : 'bg-emerald-500');
                                                    $checkOutTitle = !$log?->check_out_at
                                                        ? 'Chưa check-out'
                                                        : ($log->early_minutes > 0 ? "Check-out sớm {$log->early_minutes} phút" : 'Check-out đúng giờ');
                                                @endphp
                                                <div class="flex flex-col items-center leading-tight gap-1">
                                                    <span>{{ $s->shift?->code }}</span>
                                                    <span class="text-[10px] font-normal text-slate-400 dark:text-slate-500">
                                                        {{ substr($s->shift?->start_time ?? '', 0, 5) }}–{{ substr($s->shift?->end_time ?? '', 0, 5) }}
                                                    </span>
                                                    <span class="flex items-center gap-1 mt-0.5">
                                                        <span class="w-1.5 h-1.5 rounded-full {{ $checkInDot }}" title="{{ $checkInTitle }}"></span>
                                                        <span class="w-1.5 h-1.5 rounded-full {{ $checkOutDot }}" title="{{ $checkOutTitle }}"></span>
                                                    </span>
                                                </div>
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
        </div>
    </div>
@endsection

@push('modals')
    @include('shift-schedules.partials.assign-modal')
    @include('shift-schedules.partials.bulk-assign-modal')
    @include('shift-schedules.partials.swap-request-modal')
    @include('shift-schedules.partials.on-shift-modal')
    @include('components.shift-day-detail-modal')
    <x-export-range-modal id="exportShiftSchedulesModal" title="Xuất Excel — Xếp ca"
        :export-url="route('shift-schedules.export')" :ref-date="$weekStart"
        :hidden="['branch_id' => request('branch_id'), 'team_id' => request('team_id'), 'employee_id' => request('employee_id')]" />
@endpush

@push('scripts')
<script>
window.SCHED_PERMS = {
    canEdit: @json(auth()->user()->can('edit-shift-schedules')),
    canDelete: @json(auth()->user()->can('delete-shift-schedules')),
    canCreate: @json(auth()->user()->can('create-shift-schedules')),
    canSwap: @json(auth()->user()->can('create-shift-swaps')),
    hasUpcoming: @json($myUpcomingSchedules->isNotEmpty()),
};

function openAssignModal(employeeId, employeeName, workDate, scheduleId, currentShiftId, currentNote) {
    const form = document.getElementById('assignShiftForm');
    document.getElementById('assignEmployeeId').value = employeeId;
    document.getElementById('assignEmployeeLabel').textContent = employeeName + ' — ' + workDate;
    document.getElementById('assignWorkDate').value = workDate;
    document.getElementById('assignShiftId').value = currentShiftId ?? '';
    document.getElementById('assignNote').value = currentNote ?? '';

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
</script>
@endpush
