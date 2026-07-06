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
            @can('delete-shift-schedules')
            <button type="button" id="selectModeToggleBtn" onclick="toggleSelectMode()" class="btn-secondary">
                <i class="bi bi-check2-square"></i>
                <span id="selectModeToggleLabel">Chọn nhiều</span>
            </button>
            <button type="button" onclick="confirmDeleteAll()" class="btn-secondary text-red-600 dark:text-red-400">
                <i class="bi bi-trash3"></i>
                <span>Xoá tất cả</span>
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
                        label="Nhân viên" placeholder="Xem ca của nhân viên..." :compact="true" />
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

        <div class="sched-legend">
            <span class="sched-legend-label"><i class="bi bi-info-circle"></i> Chấm công</span>
            <span class="sched-legend-item"><span class="sched-dot sched-dot-pending"></span> Chưa chấm công</span>
            <span class="sched-legend-item"><span class="sched-dot sched-dot-ontime"></span> Đúng giờ</span>
            <span class="sched-legend-item"><span class="sched-dot sched-dot-late"></span> Trễ / về sớm</span>
        </div>

        <div class="card-body p-0">
            <div class="table-container border-0 rounded-none overflow-x-auto">
                <table class="table-base min-w-[900px] border-separate border-spacing-0">
                    <thead>
                        <tr>
                            <th class="table-th sched-sticky-col">Nhân viên</th>
                            @foreach($days as $day)
                                <th class="table-th text-center sched-th @if($day->isWeekend()) sched-th-weekend @endif @if($day->isToday()) sched-th-today @endif">
                                    <span class="sched-th-dow">{{ ['CN','T2','T3','T4','T5','T6','T7'][$day->dayOfWeek] }}</span>
                                    <span class="sched-th-date @if($day->isToday()) sched-th-date-today @endif">{{ $day->format('d/m') }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employees as $emp)
                        <tr class="table-tr-hover">
                            <td class="table-td font-medium sched-sticky-col">
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
                                        'is_flexible' => $s->isFlexible(),
                                        'shift_name' => $s->shift?->name ?? 'Ca linh hoạt',
                                        'shift_code' => $s->shift?->code,
                                        'start_time' => substr($s->shift?->start_time ?? $s->custom_start_time ?? '', 0, 5),
                                        'end_time' => substr($s->shift?->end_time ?? $s->custom_end_time ?? '', 0, 5),
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
                                        <div class="relative">
                                        @can('delete-shift-schedules')
                                        <input type="checkbox"
                                            class="sched-select-checkbox hidden absolute top-1 right-1 z-10"
                                            data-ids="{{ $cellData->pluck('id')->implode(',') }}"
                                            onclick="event.stopPropagation(); toggleCellSelection(this)">
                                        @endcan
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
                                                            <span class="sched-multi-time">{{ substr($s->shift?->start_time ?? $s->custom_start_time ?? '', 0, 5) }}–{{ substr($s->shift?->end_time ?? $s->custom_end_time ?? '', 0, 5) }}</span>
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
                                                <span class="sched-chip-badge">{{ $s->shift?->code ?? 'LH' }}</span>
                                                <span class="sched-cell-time">{{ substr($s->shift?->start_time ?? $s->custom_start_time ?? '', 0, 5) }}–{{ substr($s->shift?->end_time ?? $s->custom_end_time ?? '', 0, 5) }}</span>
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
                                        </div>
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

    @can('delete-shift-schedules')
    {{-- Thanh nổi hiển thị khi đang ở chế độ "Chọn nhiều" và đã chọn ít nhất 1 ca --}}
    <div id="schedBulkBar" class="hidden fixed bottom-5 left-1/2 -translate-x-1/2 z-40 items-center gap-3 rounded-full bg-slate-900 dark:bg-slate-700 text-white text-sm font-medium px-4 py-2.5 shadow-2xl">
        <span id="schedBulkCount">Đã chọn 0 ca</span>
        <button type="button" onclick="submitBulkDelete()" class="btn-danger btn-sm">
            <i class="bi bi-trash3"></i> Xoá đã chọn
        </button>
        <button type="button" onclick="cancelSelectMode()" class="text-slate-300 hover:text-white text-xs underline">
            Huỷ
        </button>
    </div>

    <form id="schedBulkDeleteForm" method="POST" action="{{ route('shift-schedules.bulk-destroy') }}" class="hidden">
        @csrf
        @method('DELETE')
    </form>

    <form id="schedDestroyAllForm" method="POST" action="{{ route('shift-schedules.destroy-all') }}" class="hidden">
        @csrf
        @method('DELETE')
        <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">
        <input type="hidden" name="branch_id" value="{{ request('branch_id') }}">
        <input type="hidden" name="team_id" value="{{ request('team_id') }}">
        <input type="hidden" name="employee_id" value="{{ request('employee_id') }}">
    </form>
    @endcan
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

// --- Chọn nhiều ô để xoá hàng loạt ---
let schedSelectMode = false;
const schedSelectedIds = new Set();

function toggleSelectMode() {
    schedSelectMode = !schedSelectMode;

    document.querySelectorAll('.sched-select-checkbox').forEach(function (cb) {
        cb.classList.toggle('hidden', !schedSelectMode);
        if (!schedSelectMode) cb.checked = false;
    });

    const btn = document.getElementById('selectModeToggleBtn');
    btn.classList.toggle('btn-primary', schedSelectMode);
    btn.classList.toggle('btn-secondary', !schedSelectMode);
    document.getElementById('selectModeToggleLabel').textContent = schedSelectMode ? 'Đang chọn…' : 'Chọn nhiều';

    if (!schedSelectMode) {
        schedSelectedIds.clear();
        updateSchedBulkBar();
    }
}

function toggleCellSelection(checkbox) {
    const ids = (checkbox.dataset.ids || '').split(',').filter(Boolean);
    if (checkbox.checked) {
        ids.forEach(function (id) { schedSelectedIds.add(id); });
    } else {
        ids.forEach(function (id) { schedSelectedIds.delete(id); });
    }
    updateSchedBulkBar();
}

function updateSchedBulkBar() {
    const bar = document.getElementById('schedBulkBar');
    if (!bar) return;
    const count = schedSelectedIds.size;
    document.getElementById('schedBulkCount').textContent = 'Đã chọn ' + count + ' ca';
    bar.classList.toggle('hidden', count === 0);
    bar.classList.toggle('flex', count > 0);
}

function cancelSelectMode() {
    schedSelectedIds.clear();
    document.querySelectorAll('.sched-select-checkbox').forEach(function (cb) { cb.checked = false; });
    updateSchedBulkBar();
    if (schedSelectMode) toggleSelectMode();
}

function submitBulkDelete() {
    if (schedSelectedIds.size === 0) return;
    if (!confirm('Xoá ' + schedSelectedIds.size + ' ca đã chọn? Ca nào thuộc đợt xếp ca cố định sẽ bị huỷ toàn bộ đợt.')) return;

    const form = document.getElementById('schedBulkDeleteForm');
    form.querySelectorAll('input[name="schedule_ids[]"]').forEach(function (el) { el.remove(); });
    schedSelectedIds.forEach(function (id) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'schedule_ids[]';
        input.value = id;
        form.appendChild(input);
    });
    form.submit();
}

function confirmDeleteAll() {
    if (!confirm('Xoá TẤT CẢ ca đang hiển thị theo bộ lọc/tuần hiện tại? Hành động này không thể hoàn tác.')) return;
    document.getElementById('schedDestroyAllForm').submit();
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
</script>
@endpush
