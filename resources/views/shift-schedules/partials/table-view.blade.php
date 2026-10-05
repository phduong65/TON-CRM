{{-- Chế độ xem: Bảng tuần ngắn gọn (Compact Table View — Redesigned) --}}
<div class="table-container border-0 rounded-none overflow-x-auto">
    <table class="table-base min-w-[1050px] sched-compact-table border-separate border-spacing-0">
        <thead>
            <tr>
                <th class="table-th sched-sticky-col font-heading text-xs uppercase tracking-wider py-3 px-3.5 w-48 text-left">
                    Nhân viên
                </th>
                <th class="table-th text-center font-heading text-xs uppercase tracking-wider py-3 px-2 w-20">
                    Tổng ca
                </th>
                @foreach($days as $day)
                    <th class="table-th text-center py-2 px-1 w-[11.5%] @if($day->isToday()) !bg-pcrm-50/40 dark:!bg-pcrm-950/20 border-t-2 !border-t-pcrm-600 dark:!border-t-pcrm-500 @endif">
                        <div class="flex flex-col items-center justify-center gap-0.5">
                            <span class="font-heading text-xs font-black uppercase tracking-wide @if($day->isToday()) text-pcrm-600 dark:text-pcrm-400 @elseif($day->isWeekend()) text-slate-400 dark:text-slate-500 @else text-slate-700 dark:text-slate-200 @endif">
                                {{ ['CN','T2','T3','T4','T5','T6','T7'][$day->dayOfWeek] }}
                            </span>
                            @if($day->isToday())
                                <span class="font-heading text-[10.5px] font-extrabold px-1.5 py-0.5 rounded-full bg-pcrm-600 text-white shadow-xs leading-none">
                                    {{ $day->format('d/m') }}
                                </span>
                            @else
                                <span class="font-heading text-[11px] font-medium leading-none {{ $day->isWeekend() ? 'text-slate-400 dark:text-slate-500' : 'text-slate-400 dark:text-slate-500' }}">
                                    {{ $day->format('d/m') }}
                                </span>
                            @endif
                        </div>
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($sortedEmployees as $emp)
                @php
                    $isOwn = $myEmployee && $emp->id === $myEmployee->id;
                    $empWeekSchedules = $allWeekSchedules->where('employee_id', $emp->id);
                    $empShiftCount = $empWeekSchedules->count();
                    $nameWords = preg_split('/\s+/', trim($emp->name));
                    $initials = count($nameWords) >= 2
                        ? mb_substr($nameWords[0], 0, 1) . mb_substr(end($nameWords), 0, 1)
                        : mb_substr($emp->name, 0, 2);
                @endphp
                <tr class="table-tr-hover">
                    {{-- Nhân viên (Sticky col) --}}
                    <td class="table-td sched-sticky-col py-1.5 px-3">
                        <div class="flex items-center gap-2">
                            <x-employee-avatar :employee="$emp" :name="$initials" size="w-6 h-6" :initials="2" text="text-[10px]"
                                :fallback="($isOwn ? 'bg-pcrm-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300') . ' font-black font-heading'" />
                            <div class="min-w-0 flex-1">
                                <span class="sched-emp-name block text-xs font-bold text-slate-800 dark:text-slate-100 truncate" style="font-family: var(--font-heading, 'Bricolage Grotesque', sans-serif) !important;">
                                    {{ $emp->name }}
                                </span>
                                <span class="text-[10.5px] text-slate-400 dark:text-slate-500 block truncate leading-tight">{{ $emp->team?->name ?? '—' }}</span>
                            </div>
                        </div>
                    </td>

                    {{-- Tổng ca tuần --}}
                    <td class="table-td text-center py-1.5 px-1.5">
                        @if($empShiftCount > 0)
                            <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[11px] font-bold font-heading bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200/50 dark:border-slate-700/50">
                                {{ $empShiftCount }} <span class="text-[9.5px] text-slate-400 ml-0.5 font-normal">ca</span>
                            </span>
                        @else
                            <span class="text-xs text-slate-300 dark:text-slate-600 font-normal">—</span>
                        @endif
                    </td>

                    {{-- 7 Ngày trong tuần --}}
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
                        <td class="table-td text-center sched-td @if($day->isWeekend()) sched-td-weekend @endif @if($day->isToday()) !bg-pcrm-50/20 dark:!bg-pcrm-950/10 @endif py-1 px-1 align-middle">
                            @if($cellSchedules->isEmpty())
                                @if($overtimeOnlyLog)
                                    @php $otHours = rtrim(rtrim(number_format($overtimeOnlyLog->overtime_hours, 2, '.', ''), '0'), '.'); @endphp
                                    <span class="sched-ot-badge !static !h-auto !py-0.5 !px-1.5 text-[10px]"
                                          title="Tăng ca đã duyệt: +{{ $otHours }}h">
                                        +{{ $otHours }}h TC
                                    </span>
                                @elseif($canCreateSchedule)
                                    <button type="button"
                                        onclick="openAssignModal({{ $emp->id }}, {{ Illuminate\Support\Js::from($emp->name) }}, '{{ $day->toDateString() }}', null, null, null, false, null, null, null, false, false)"
                                        class="sched-mini-empty-btn group" title="Xếp ca cho {{ $emp->name }} ngày {{ $day->format('d/m') }}">
                                        <i class="bi bi-plus-lg sched-empty-icon"></i>
                                    </button>
                                @else
                                    <div class="sched-mini-empty-btn cursor-default opacity-40">
                                        <span class="text-xs text-slate-300 dark:text-slate-650">—</span>
                                    </div>
                                @endif
                            @elseif($cellSchedules->count() === 1)
                                @php
                                    $s = $cellSchedules->first();
                                    $log = $s->attendanceLog;
                                    $checkInDot = !$log?->check_in_at
                                        ? 'sched-dot-pending'
                                        : ($log->late_minutes > 0 ? 'sched-dot-late' : 'sched-dot-ontime');
                                    $checkInTitle = !$log?->check_in_at
                                        ? 'Chưa check-in'
                                        : ($log->late_minutes > 0 ? "Trễ {$log->late_minutes} phút" : 'Đúng giờ');
                                    $startTime = substr($s->effectiveShift()?->start_time ?? '', 0, 5);
                                    $endTime = substr($s->effectiveShift()?->end_time ?? '', 0, 5);
                                    $isWfh = $s->shift ? (bool) $s->shift->isWfh() : (bool) $s->custom_is_wfh;
                                    $isOvernight = $s->shift ? (bool) $s->shift->is_overnight : (bool) $s->custom_is_overnight;
                                    $shiftDisplayName = $s->shift?->name ?? 'Ca linh hoạt';
                                @endphp
                                <button type="button"
                                    onclick="openDayDetailModal({{ $emp->id }}, {{ Illuminate\Support\Js::from($emp->name) }}, '{{ $day->toDateString() }}', {{ Illuminate\Support\Js::from($day->format('d/m/Y')) }}, {{ Illuminate\Support\Js::from($cellData) }}, { isOwnEmployee: {{ $isOwnEmployeeCell ? 'true' : 'false' }}, dayIsFutureOrToday: {{ $day->gte(today()) ? 'true' : 'false' }} })"
                                    class="sched-mini-card {{ $isWfh ? 'is-wfh' : '' }} {{ $isOvernight ? 'is-overnight' : '' }} group"
                                    title="{{ $shiftDisplayName }} ({{ $startTime }} – {{ $endTime }})">
                                    <div class="flex items-center justify-between gap-1 w-full">
                                        <span class="sched-mini-time">{{ $startTime }}–{{ $endTime }}</span>
                                        <span class="sched-dot {{ $checkInDot }}" title="{{ $checkInTitle }}"></span>
                                    </div>
                                    <div class="flex items-center justify-between gap-1 w-full mt-0.5">
                                        <span class="sched-mini-label truncate" title="{{ $shiftDisplayName }}">
                                            {{ $shiftDisplayName }}
                                        </span>
                                        @if($isWfh)
                                            <span class="sched-mini-tag is-wfh">WFH</span>
                                        @elseif($isOvernight)
                                            <i class="bi bi-moon-stars text-[9px] text-purple-500" title="Ca qua đêm"></i>
                                        @endif
                                    </div>
                                </button>
                            @else
                                {{-- 2 ca trở lên (ca gãy / nhiều ca trong ngày) --}}
                                <button type="button"
                                    onclick="openDayDetailModal({{ $emp->id }}, {{ Illuminate\Support\Js::from($emp->name) }}, '{{ $day->toDateString() }}', {{ Illuminate\Support\Js::from($day->format('d/m/Y')) }}, {{ Illuminate\Support\Js::from($cellData) }}, { isOwnEmployee: {{ $isOwnEmployeeCell ? 'true' : 'false' }}, dayIsFutureOrToday: {{ $day->gte(today()) ? 'true' : 'false' }} })"
                                    class="sched-mini-card is-multi group"
                                    title="{{ $cellSchedules->count() }} ca trong ngày">
                                    @foreach($cellSchedules as $subIndex => $s)
                                        @php
                                            $subLog = $s->attendanceLog;
                                            $subDot = !$subLog?->check_in_at ? 'sched-dot-pending' : ($subLog->late_minutes > 0 ? 'sched-dot-late' : 'sched-dot-ontime');
                                            $subStart = substr($s->effectiveShift()?->start_time ?? '', 0, 5);
                                            $subEnd = substr($s->effectiveShift()?->end_time ?? '', 0, 5);
                                        @endphp
                                        <div class="flex items-center justify-between gap-1 w-full {{ $subIndex > 0 ? 'mt-0.5 pt-0.5 border-t border-amber-200/60 dark:border-amber-800/40' : '' }}">
                                            <span class="sched-mini-time text-amber-900 dark:text-amber-200">{{ $subStart }}–{{ $subEnd }}</span>
                                            <span class="sched-dot {{ $subDot }}"></span>
                                        </div>
                                    @endforeach
                                </button>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $days->count() + 2 }}" class="table-td text-center py-8 text-slate-400">
                        <i class="bi bi-people text-3xl mb-2 block opacity-40"></i>
                        <p class="text-sm font-medium">Không có nhân viên phù hợp bộ lọc</p>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
