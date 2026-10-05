{{-- Chế độ xem: Danh sách ca làm việc (Compact List View) --}}
<div>
    {{-- Thanh lọc nhanh theo từng ngày trong tuần --}}
    <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/30 flex items-center gap-2 overflow-x-auto scrollbar-thin">
        <span class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider flex-shrink-0 flex items-center gap-1 mr-1">
            <i class="bi bi-filter"></i> Lọc ngày:
        </span>
        <button type="button" onclick="filterScheduleListByDay('all', this)"
                id="dayPillAll"
                class="sched-day-pill is-active">
            <span>Tất cả</span>
            <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-pcrm-800/40 text-white font-bold">{{ $allWeekSchedules->count() }}</span>
        </button>
        @foreach($days as $day)
            @php
                $dayStr = $day->toDateString();
                $dayCount = $allWeekSchedules->where('work_date', $day)->count();
                $dayShort = ['CN','T2','T3','T4','T5','T6','T7'][$day->dayOfWeek];
            @endphp
            <button type="button" onclick="filterScheduleListByDay('{{ $dayStr }}', this)"
                    class="sched-day-pill @if($day->isToday()) !border-pcrm-400 dark:!border-pcrm-600 @endif">
                <span>{{ $dayShort }} {{ $day->format('d/m') }}</span>
                @if($day->isToday())
                    <span class="text-[9px] px-1 py-0.2 rounded bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300 font-bold">Nay</span>
                @endif
                <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold">{{ $dayCount }}</span>
            </button>
        @endforeach
    </div>

    {{-- Bảng danh sách ca --}}
    <div class="table-container border-0 rounded-none overflow-x-auto">
        <table class="table-base min-w-[950px]" id="scheduleListTable">
            <thead>
                <tr>
                    <th class="table-th font-heading text-xs uppercase tracking-wider py-2.5 px-3 w-36">Ngày làm việc</th>
                    <th class="table-th font-heading text-xs uppercase tracking-wider py-2.5 px-3">Nhân viên & Bộ phận</th>
                    <th class="table-th font-heading text-xs uppercase tracking-wider py-2.5 px-3">Ca làm việc</th>
                    <th class="table-th font-heading text-xs uppercase tracking-wider py-2.5 px-3">Khung giờ quy định</th>
                    <th class="table-th font-heading text-xs uppercase tracking-wider py-2.5 px-3">Chấm công thực tế</th>
                    <th class="table-th font-heading text-xs uppercase tracking-wider py-2.5 px-3">Ghi chú & Người tạo</th>
                    <th class="table-th font-heading text-xs uppercase tracking-wider py-2.5 px-3 text-right w-24">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse($allWeekSchedules as $s)
                    @php
                        $day = $s->work_date;
                        $dayStr = $day->toDateString();
                        $emp = $s->employee;
                        $isOwn = $myEmployee && $emp && $emp->id === $myEmployee->id;
                        $log = $s->attendanceLog;
                        $effShift = $s->effectiveShift();
                        $startTime = substr($effShift?->start_time ?? '', 0, 5);
                        $endTime = substr($effShift?->end_time ?? '', 0, 5);
                        $isFlexible = $s->isFlexible();
                        $isWfh = $s->shift ? (bool) $s->shift->isWfh() : (bool) $s->custom_is_wfh;
                        $isOvernight = $s->shift ? (bool) $s->shift->is_overnight : (bool) $s->custom_is_overnight;
                        $isLeaveAdjusted = (bool) ($s->adjusted_start_time || $s->adjusted_end_time);

                        // Dữ liệu dùng cho modal chi tiết
                        $singleCellData = [[
                            'id' => $s->id,
                            'shift_id' => $s->shift_id,
                            'is_flexible' => $isFlexible,
                            'shift_name' => $s->shift?->name ?? 'Ca linh hoạt',
                            'shift_code' => $s->shift?->code,
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                            'leave_adjusted' => $isLeaveAdjusted,
                            'is_wfh' => $isWfh,
                            'custom_start_time' => $s->custom_start_time ? substr($s->custom_start_time, 0, 5) : null,
                            'custom_end_time' => $s->custom_end_time ? substr($s->custom_end_time, 0, 5) : null,
                            'custom_break_minutes' => $s->custom_break_minutes,
                            'custom_is_overnight' => $isOvernight,
                            'custom_is_wfh' => $isWfh,
                            'assignment_type' => $s->assignment_type,
                            'batch_id' => $s->batch_id,
                            'note' => $s->note,
                            'assigned_by' => $s->assignedBy?->name,
                            'created_at' => $s->created_at?->format('d/m/Y H:i'),
                            'attendance' => $log ? [
                                'id' => $log->id,
                                'check_in_at' => $log->check_in_at?->format('H:i:s'),
                                'check_out_at' => $log->check_out_at?->format('H:i:s'),
                                'late_minutes' => $log->late_minutes,
                                'early_minutes' => $log->early_minutes,
                                'check_in_method' => $log->check_in_method,
                                'check_out_method' => $log->check_out_method,
                                'device_changed' => $log->deviceChanged(),
                                'overtime_hours' => (float) $log->overtime_hours,
                            ] : null,
                        ]];
                    @endphp
                    <tr class="table-tr-hover sched-list-row" data-day="{{ $dayStr }}">
                        {{-- Ngày làm việc --}}
                        <td class="table-td py-2.5 px-3">
                            <div class="flex items-center gap-1.5">
                                <span class="font-heading font-bold text-xs text-slate-800 dark:text-slate-100">
                                    {{ ['CN','Thứ 2','Thứ 3','Thứ 4','Thứ 5','Thứ 6','Thứ 7'][$day->dayOfWeek] }}
                                </span>
                                <span class="text-xs text-slate-400">({{ $day->format('d/m') }})</span>
                            </div>
                            @if($day->isToday())
                                <span class="mt-0.5 inline-block text-[9px] font-extrabold uppercase px-1.5 py-0.2 rounded bg-pcrm-100 text-pcrm-700 dark:bg-pcrm-900/50 dark:text-pcrm-300">
                                    Hôm nay
                                </span>
                            @endif
                        </td>

                        {{-- Nhân viên & Bộ phận --}}
                        <td class="table-td py-2.5 px-3">
                            @php
                                $nameWords = preg_split('/\s+/', trim($emp?->name ?? ''));
                                $initials = count($nameWords) >= 2
                                    ? mb_substr($nameWords[0], 0, 1) . mb_substr(end($nameWords), 0, 1)
                                    : mb_substr($emp?->name ?? 'NV', 0, 2);
                            @endphp
                            <div class="flex items-center gap-2.5">
                                <x-employee-avatar :employee="$emp" :name="$initials" size="w-7 h-7" :initials="2" text="text-[10.5px]"
                                    :fallback="($isOwn ? 'bg-pcrm-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300') . ' font-black font-heading'" />
                                <div class="min-w-0 flex-1">
                                    <span class="sched-emp-name block text-xs font-bold text-slate-850 dark:text-slate-100 truncate" style="font-family: var(--font-heading, 'Bricolage Grotesque', sans-serif) !important;">
                                        {{ $emp?->name ?? '—' }}
                                    </span>
                                    <p class="text-[11px] text-slate-400 truncate">{{ $emp?->team?->name ?? '—' }}</p>
                                </div>
                            </div>
                        </td>

                        {{-- Ca làm việc --}}
                        <td class="table-td py-2.5 px-3">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-100 font-heading">
                                    {{ $s->shift?->name ?? 'Ca linh hoạt' }}
                                </span>
                                @if($s->isFlexible())
                                    <span class="text-[10px] px-1.5 py-0.2 rounded bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300 font-bold">Linh hoạt</span>
                                @endif
                                @if($isWfh)
                                    <span class="text-[10px] px-1.5 py-0.2 rounded bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300 font-bold">WFH</span>
                                @endif
                                @if($isOvernight)
                                    <span class="text-[10px] px-1.5 py-0.2 rounded bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-300 font-bold" title="Ca qua đêm">
                                        <i class="bi bi-moon-stars"></i> Qua đêm
                                    </span>
                                @endif
                            </div>
                        </td>

                        {{-- Khung giờ quy định --}}
                        <td class="table-td py-2.5 px-3">
                            <div class="flex items-center gap-1 font-mono text-xs font-semibold text-slate-700 dark:text-slate-200">
                                <i class="bi bi-clock text-slate-400"></i>
                                <span>{{ $startTime }} – {{ $endTime }}</span>
                            </div>
                            @if($isLeaveAdjusted)
                                <span class="inline-flex items-center gap-1 text-[10px] text-amber-600 dark:text-amber-400 mt-0.5" title="Đã trừ thời gian nghỉ phép">
                                    <i class="bi bi-calendar-minus"></i> Giờ sau điều chỉnh
                                </span>
                            @endif
                        </td>

                        {{-- Chấm công thực tế --}}
                        <td class="table-td py-2.5 px-3">
                            @if($log)
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-mono text-xs text-slate-700 dark:text-slate-300">
                                        {{ $log->check_in_at?->format('H:i') ?? '--:--' }} – {{ $log->check_out_at?->format('H:i') ?? '--:--' }}
                                    </span>
                                    @if(!$log->check_in_at)
                                        <span class="text-[10px] px-1.5 py-0.2 rounded bg-slate-100 text-slate-600 dark:bg-slate-750 dark:text-slate-400 font-medium">Chưa vào</span>
                                    @elseif($log->late_minutes > 0)
                                        <span class="text-[10px] px-1.5 py-0.2 rounded bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300 font-bold">Trễ {{ $log->late_minutes }}p</span>
                                    @else
                                        <span class="text-[10px] px-1.5 py-0.2 rounded bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 font-bold">Đúng giờ</span>
                                    @endif
                                    @if($log->overtime_hours > 0)
                                        <span class="sched-ot-badge !static !h-auto !py-0.2 !px-1.5 text-[10px]">
                                            +{{ rtrim(rtrim(number_format($log->overtime_hours, 2, '.', ''), '0'), '.') }}h TC
                                        </span>
                                    @endif
                                </div>
                            @else
                                <span class="text-[11px] text-slate-400 italic">Chưa chấm công</span>
                            @endif
                        </td>

                        {{-- Ghi chú & Người tạo --}}
                        <td class="table-td py-2.5 px-3">
                            @if($s->note)
                                <p class="text-xs text-slate-600 dark:text-slate-300 truncate max-w-xs" title="{{ $s->note }}">{{ $s->note }}</p>
                            @endif
                            <span class="text-[11px] text-slate-400 block">
                                Gán bởi: {{ $s->assignedBy?->name ?? 'Hệ thống' }}
                            </span>
                        </td>

                        {{-- Thao tác --}}
                        <td class="table-td py-2.5 px-3 text-right">
                            <div class="inline-flex items-center gap-1 justify-end">
                                <button type="button"
                                    onclick="openDayDetailModal({{ $s->employee_id }}, {{ Illuminate\Support\Js::from($emp?->name ?? '') }}, '{{ $dayStr }}', {{ Illuminate\Support\Js::from($day->format('d/m/Y')) }}, {{ Illuminate\Support\Js::from($singleCellData) }}, { isOwnEmployee: {{ $isOwn ? 'true' : 'false' }}, dayIsFutureOrToday: {{ $day->gte(today()) ? 'true' : 'false' }} })"
                                    class="p-1 rounded text-slate-400 hover:text-pcrm-600 hover:bg-pcrm-50 dark:hover:bg-pcrm-900/30 transition-colors"
                                    title="Xem chi tiết & chấm công">
                                    <i class="bi bi-eye text-sm"></i>
                                </button>
                                @can('edit-shift-schedules')
                                <button type="button"
                                    onclick="openAssignModal({{ $s->employee_id }}, {{ Illuminate\Support\Js::from($emp?->name ?? '') }}, '{{ $dayStr }}', {{ $s->id }}, {{ $s->shift_id ? $s->shift_id : 'null' }}, {{ Illuminate\Support\Js::from($s->note) }}, {{ $isFlexible ? 'true' : 'false' }}, {{ Illuminate\Support\Js::from($s->custom_start_time ? substr($s->custom_start_time, 0, 5) : null) }}, {{ Illuminate\Support\Js::from($s->custom_end_time ? substr($s->custom_end_time, 0, 5) : null) }}, {{ $s->custom_break_minutes ?? 'null' }}, {{ $isOvernight ? 'true' : 'false' }}, {{ $isWfh ? 'true' : 'false' }})"
                                    class="p-1 rounded text-slate-400 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/30 transition-colors"
                                    title="Sửa ca làm">
                                    <i class="bi bi-pencil text-sm"></i>
                                </button>
                                @endcan
                                @can('create-shift-swaps')
                                    @if(!$isOwn && !$isFlexible && $day->gte(today()) && $myUpcomingSchedules->isNotEmpty())
                                        <button type="button"
                                            onclick="openSwapModal({{ $s->id }}, {{ Illuminate\Support\Js::from($emp?->name ?? '') }}, {{ Illuminate\Support\Js::from($day->format('d/m/Y')) }}, {{ Illuminate\Support\Js::from($s->shift?->name ?? '') }})"
                                            class="p-1 rounded text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 transition-colors"
                                            title="Đề xuất đổi ca">
                                            <i class="bi bi-arrow-left-right text-sm"></i>
                                        </button>
                                    @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="table-td text-center py-8 text-slate-400">
                            <i class="bi bi-calendar-x text-3xl mb-2 block opacity-40"></i>
                            <p class="text-sm font-medium">Không có ca làm việc nào trong tuần này hoặc theo bộ lọc hiện tại.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
