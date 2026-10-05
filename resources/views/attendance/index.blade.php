@extends('layouts.admin')

@section('title', 'Chấm công')
@section('page-title', 'Chấm công')
@section('page-subtitle', 'Check-in / check-out ca làm hôm nay của bạn')
@section('breadcrumb', 'Ca làm việc & Chấm công')

@section('content')
    @php
        // Khi đã ghi nhận check-in/out (disabled), chuyển hẳn sang màu xám thay vì chỉ giảm độ
        // mờ của màu gốc (xanh dương/đỏ) — dùng !important để thắng chắc chắn class btn-primary/
        // btn-danger bất kể thứ tự layer CSS.
        $btnDisabledClasses = 'disabled:!bg-slate-200 disabled:!text-slate-400 disabled:!border-slate-200 '
            . 'disabled:!opacity-100 disabled:cursor-not-allowed disabled:pointer-events-none '
            . 'dark:disabled:!bg-slate-700 dark:disabled:!text-slate-500 dark:disabled:!border-slate-700';
        $todayLabel = now()->format('d/m/Y');
        $allShifts = $activeShifts->concat($missedShifts);
        $cellData = $allShifts->map(fn($s) => [
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
            'note' => $s->note,
            'assigned_by' => null,
            'created_at' => null,
            'attendance' => $s->attendanceLog ? [
                'check_in_at' => $s->attendanceLog->check_in_at?->format('H:i:s'),
                'check_out_at' => $s->attendanceLog->check_out_at?->format('H:i:s'),
                'late_minutes' => $s->attendanceLog->late_minutes,
                'early_minutes' => $s->attendanceLog->early_minutes,
                'check_in_method' => $s->attendanceLog->check_in_method,
                'check_out_method' => $s->attendanceLog->check_out_method,
                'device_changed' => $s->attendanceLog->deviceChanged(),
            ] : null,
        ])->values();
    @endphp

    <div class="max-w-4xl mx-auto space-y-6">

        {{-- ── Hero: đồng hồ ────────────────────────────────────────────────── --}}
        <div class="rounded-2xl overflow-hidden shadow-lg relative"
             style="background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 50%, #1e3a8a 100%);">

            {{-- Decorative blobs --}}
            <div class="absolute inset-0 pointer-events-none">
                <div class="absolute -top-16 -right-10 w-64 h-64 rounded-full"
                     style="background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%)"></div>
                <div class="absolute -bottom-14 left-1/4 w-52 h-52 rounded-full"
                     style="background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%)"></div>
            </div>

            <div class="relative px-5 py-8 sm:px-8 sm:py-10 flex flex-col items-center text-center">
                <p class="text-white/60 text-sm font-medium flex items-center gap-1.5">
                    <i class="bi bi-calendar3"></i> {{ now()->translatedFormat('l, d/m/Y') }}
                </p>
                <h2 class="text-white text-5xl sm:text-6xl font-black tracking-tight tabular-nums mt-1" id="liveClock">
                    {{ now()->format('H:i:s') }}
                </h2>

                @if($allShifts->isNotEmpty())
                    <button type="button"
                        onclick="openDayDetailModal({{ $employee->id }}, {{ Illuminate\Support\Js::from($employee->name) }}, '{{ now()->toDateString() }}', {{ Illuminate\Support\Js::from($todayLabel) }}, {{ Illuminate\Support\Js::from($cellData) }}, { isOwnEmployee: true, dayIsFutureOrToday: true })"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium text-white/90 mt-5 hover:bg-white/10 transition"
                        style="background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.2);">
                        <i class="bi bi-clock-history"></i>
                        {{ $allShifts->count() >= 2 ? $allShifts->count() . ' ca hôm nay' : 'Ca hôm nay: ' . ($allShifts->first()->shift?->name ?? 'Ca linh hoạt') }}
                        <i class="bi bi-chevron-right text-xs"></i>
                    </button>
                @else
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-medium text-white/70 mt-5"
                         style="background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.15);">
                        <i class="bi bi-exclamation-circle"></i> Hôm nay bạn chưa được xếp ca
                    </div>
                @endif
            </div>
        </div>

        {{-- ── 1 card / ca — chấm công riêng biệt theo từng ca (ca đang diễn ra) ───── --}}
        @foreach($activeShifts as $sched)
            @php $sid = $sched->id; @endphp
            <div class="card p-5 sm:p-6">
                <div class="flex items-center justify-between gap-2 mb-4">
                    <div class="min-w-0">
                        <p class="font-semibold text-slate-900 dark:text-white truncate">{{ $sched->shift?->name ?? 'Ca linh hoạt' }}</p>
                        <p class="text-xs text-slate-400">
                            {{ substr($sched->effectiveShift()?->start_time ?? '',0,5) }}–{{ substr($sched->effectiveShift()?->end_time ?? '',0,5) }}
                            @if($sched->adjusted_start_time || $sched->adjusted_end_time)
                                <span class="text-amber-500" title="Đã điều chỉnh giờ do nghỉ phép theo giờ được duyệt">
                                    <i class="bi bi-calendar-minus"></i> đã điều chỉnh
                                </span>
                            @endif
                        </p>
                    </div>
                    @if($sched->shift ? $sched->shift->isWfh() : $sched->custom_is_wfh)
                        <span class="badge badge-info flex-shrink-0">WFH</span>
                    @endif
                </div>

                <div class="grid grid-cols-2 gap-2 sm:gap-3">
                    <div class="rounded-xl px-3 py-3 sm:px-4 flex items-center gap-2 sm:gap-3 min-w-0 bg-slate-50 dark:bg-slate-700/40">
                        <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-full flex items-center justify-center flex-shrink-0 bg-white dark:bg-slate-700">
                            <i class="bi bi-box-arrow-in-right text-pcrm-600 dark:text-pcrm-400"></i>
                        </div>
                        <div class="min-w-0 text-left">
                            <p class="text-xs text-slate-400">Check-in</p>
                            @if($sched->attendanceLog?->check_in_at)
                                <p class="font-semibold tabular-nums text-sm sm:text-base whitespace-nowrap text-slate-800 dark:text-slate-100">
                                    {{ $sched->attendanceLog->check_in_at->format('H:i:s') }}
                                    @if($sched->attendanceLog->late_minutes > 0)
                                        <span class="text-amber-600 dark:text-amber-400 font-normal text-xs block sm:inline">(trễ {{ $sched->attendanceLog->late_minutes }}p)</span>
                                    @endif
                                </p>
                            @else
                                <p class="text-slate-400 font-semibold text-sm sm:text-base truncate">Chưa check-in</p>
                            @endif
                        </div>
                    </div>

                    <div class="rounded-xl px-3 py-3 sm:px-4 flex items-center gap-2 sm:gap-3 min-w-0 bg-slate-50 dark:bg-slate-700/40">
                        <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-full flex items-center justify-center flex-shrink-0 bg-white dark:bg-slate-700">
                            <i class="bi bi-box-arrow-right text-pcrm-600 dark:text-pcrm-400"></i>
                        </div>
                        <div class="min-w-0 text-left">
                            <p class="text-xs text-slate-400">Check-out</p>
                            @if($sched->attendanceLog?->check_out_at)
                                <p class="font-semibold tabular-nums text-sm sm:text-base whitespace-nowrap text-slate-800 dark:text-slate-100">
                                    {{ $sched->attendanceLog->check_out_at->format('H:i:s') }}
                                    @if($sched->attendanceLog->early_minutes > 0)
                                        <span class="text-amber-600 dark:text-amber-400 font-normal text-xs block sm:inline">(sớm {{ $sched->attendanceLog->early_minutes }}p)</span>
                                    @endif
                                </p>
                                @if($sched->attendanceLog->deviceChanged())
                                    <p class="text-amber-600 dark:text-amber-400 text-xs flex items-center gap-1 mt-0.5" title="Thiết bị check-out khác với lúc check-in">
                                        <i class="bi bi-exclamation-triangle-fill"></i> Khác thiết bị
                                    </p>
                                @endif
                            @else
                                <p class="text-slate-400 font-semibold text-sm sm:text-base truncate">Chưa check-out</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div id="attendanceMessage-{{ $sid }}" class="hidden mt-4 text-sm rounded-lg px-3 py-2 text-center"></div>

                @php
                    $effShift = $sched->effectiveShift();
                    $schedOvernight = (bool) $effShift?->is_overnight;
                    $schedStart = $effShift ? substr($effShift->start_time, 0, 5) : '';
                    $schedEnd   = $effShift ? substr($effShift->end_time, 0, 5) : '';

                    // Ca qua đêm bỏ qua các mốc cảnh báo ở client (so sánh chuỗi giờ đơn giản sai
                    // lệch khi giờ kết thúc vòng qua nửa đêm). Chỉ cảnh báo khi hành động thực sự
                    // vượt ngưỡng cho phép của ca — không cảnh báo cứng theo giờ bắt đầu/kết thúc ca.
                    $checkBoundaries = [];
                    if ($effShift && !$schedOvernight) {
                        $checkBoundaries = [
                            'startTime' => $schedStart,
                            'endTime' => $schedEnd,
                            'earlyCheckoutBoundary' => \Carbon\Carbon::parse($effShift->end_time)->subMinutes($effShift->grace_early_minutes ?? 0)->format('H:i'),
                        ];
                    }
                @endphp
                <div class="grid grid-cols-2 gap-2 sm:gap-3 mt-4">
                    <button id="btnCheckIn-{{ $sid }}" onclick="doAttendance('check-in', {{ $sid }}, {{ Illuminate\Support\Js::from($checkBoundaries) }})"
                            class="btn-primary justify-center py-3 text-sm sm:text-base {{ $btnDisabledClasses }}"
                            {{ $sched->attendanceLog?->check_in_at ? 'disabled' : '' }}>
                        <i class="bi {{ $sched->attendanceLog?->check_in_at ? 'bi-check-circle-fill' : 'bi-box-arrow-in-right' }}"></i>
                        {{ $sched->attendanceLog?->check_in_at ? 'Đã check-in' : 'Check-in' }}
                    </button>
                    <button id="btnCheckOut-{{ $sid }}" onclick="doAttendance('check-out', {{ $sid }}, {{ Illuminate\Support\Js::from($checkBoundaries) }})"
                            class="btn-danger justify-center py-3 text-sm sm:text-base {{ $btnDisabledClasses }}"
                            {{ (!$sched->attendanceLog?->check_in_at || $sched->attendanceLog?->check_out_at) ? 'disabled' : '' }}>
                        <i class="bi {{ $sched->attendanceLog?->check_out_at ? 'bi-check-circle-fill' : 'bi-box-arrow-right' }}"></i>
                        {{ $sched->attendanceLog?->check_out_at ? 'Đã check-out' : 'Check-out' }}
                    </button>
                </div>
            </div>
        @endforeach

        {{-- ── Ca đã bỏ lỡ — quá giờ kết thúc mà chưa từng check-in, không cho chấm công nữa ── --}}
        @if($missedShifts->isNotEmpty())
            <div>
                <div class="flex items-center gap-2 px-1 mb-3">
                    <i class="bi bi-clock-history text-slate-400 dark:text-slate-500"></i>
                    <h3 class="text-sm font-semibold text-slate-500 dark:text-slate-400">Ca đã bỏ lỡ</h3>
                    <span class="text-xs text-slate-400 ml-auto">Liên hệ quản lý để được hỗ trợ</span>
                </div>
                <div class="space-y-4">
                    @foreach($missedShifts as $sched)
                        <div class="card p-5 sm:p-6 opacity-70 border-l-4 border-l-slate-300 dark:border-l-slate-600">
                            <div class="flex items-center justify-between gap-2 mb-3">
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-500 dark:text-slate-400 truncate">{{ $sched->shift?->name ?? 'Ca linh hoạt' }}</p>
                                    <p class="text-xs text-slate-400">
                                        {{ substr($sched->effectiveShift()?->start_time ?? '',0,5) }}–{{ substr($sched->effectiveShift()?->end_time ?? '',0,5) }}
                                    </p>
                                </div>
                                <span class="badge badge-neutral flex-shrink-0">
                                    <i class="bi bi-clock-history"></i> Đã kết thúc
                                </span>
                            </div>
                            <div class="rounded-lg px-3 py-2.5 text-sm text-center bg-slate-50 dark:bg-slate-700/40 text-slate-500 dark:text-slate-400">
                                <i class="bi bi-exclamation-circle"></i>
                                Bạn chưa chấm công cho ca này. Vui lòng liên hệ quản lý để được hỗ trợ chấm công.
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if($activeShifts->isEmpty() && $missedShifts->isEmpty())
            {{-- Chưa được xếp ca — vẫn cho phép chấm công (ca ngoài lịch) --}}
            <div class="card p-5 sm:p-6">
                <p class="font-semibold text-slate-900 dark:text-white mb-4">Ca ngoài lịch</p>

                <div class="grid grid-cols-2 gap-2 sm:gap-3">
                    <div class="rounded-xl px-3 py-3 sm:px-4 flex items-center gap-2 sm:gap-3 min-w-0 bg-slate-50 dark:bg-slate-700/40">
                        <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-full flex items-center justify-center flex-shrink-0 bg-white dark:bg-slate-700">
                            <i class="bi bi-box-arrow-in-right text-pcrm-600 dark:text-pcrm-400"></i>
                        </div>
                        <div class="min-w-0 text-left">
                            <p class="text-xs text-slate-400">Check-in</p>
                            @if($unscheduledLog?->check_in_at)
                                <p class="font-semibold tabular-nums text-sm sm:text-base whitespace-nowrap text-slate-800 dark:text-slate-100">
                                    {{ $unscheduledLog->check_in_at->format('H:i:s') }}
                                </p>
                            @else
                                <p class="text-slate-400 font-semibold text-sm sm:text-base truncate">Chưa check-in</p>
                            @endif
                        </div>
                    </div>

                    <div class="rounded-xl px-3 py-3 sm:px-4 flex items-center gap-2 sm:gap-3 min-w-0 bg-slate-50 dark:bg-slate-700/40">
                        <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-full flex items-center justify-center flex-shrink-0 bg-white dark:bg-slate-700">
                            <i class="bi bi-box-arrow-right text-pcrm-600 dark:text-pcrm-400"></i>
                        </div>
                        <div class="min-w-0 text-left">
                            <p class="text-xs text-slate-400">Check-out</p>
                            @if($unscheduledLog?->check_out_at)
                                <p class="font-semibold tabular-nums text-sm sm:text-base whitespace-nowrap text-slate-800 dark:text-slate-100">
                                    {{ $unscheduledLog->check_out_at->format('H:i:s') }}
                                </p>
                                @if($unscheduledLog->deviceChanged())
                                    <p class="text-amber-600 dark:text-amber-400 text-xs flex items-center gap-1 mt-0.5" title="Thiết bị check-out khác với lúc check-in">
                                        <i class="bi bi-exclamation-triangle-fill"></i> Khác thiết bị
                                    </p>
                                @endif
                            @else
                                <p class="text-slate-400 font-semibold text-sm sm:text-base truncate">Chưa check-out</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div id="attendanceMessage-0" class="hidden mt-4 text-sm rounded-lg px-3 py-2 text-center"></div>

                <div class="grid grid-cols-2 gap-2 sm:gap-3 mt-4">
                    <button id="btnCheckIn-0" onclick="doAttendance('check-in', null, {})"
                            class="btn-primary justify-center py-3 text-sm sm:text-base {{ $btnDisabledClasses }}"
                            {{ $unscheduledLog?->check_in_at ? 'disabled' : '' }}>
                        <i class="bi {{ $unscheduledLog?->check_in_at ? 'bi-check-circle-fill' : 'bi-box-arrow-in-right' }}"></i>
                        {{ $unscheduledLog?->check_in_at ? 'Đã check-in' : 'Check-in' }}
                    </button>
                    <button id="btnCheckOut-0" onclick="doAttendance('check-out', null, {})"
                            class="btn-danger justify-center py-3 text-sm sm:text-base {{ $btnDisabledClasses }}"
                            {{ (!$unscheduledLog?->check_in_at || $unscheduledLog?->check_out_at) ? 'disabled' : '' }}>
                        <i class="bi {{ $unscheduledLog?->check_out_at ? 'bi-check-circle-fill' : 'bi-box-arrow-right' }}"></i>
                        {{ $unscheduledLog?->check_out_at ? 'Đã check-out' : 'Check-out' }}
                    </button>
                </div>
            </div>
        @endif
        {{-- ── Truy cập nhanh ──────────────────────────────────────────────── --}}
        <div>
            <p class="text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-3 px-1">
                Truy cập nhanh
            </p>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                <a href="{{ route('profile.show') }}" class="stat-card !p-4 flex flex-col items-center text-center gap-2 group">
                    <div class="w-10 h-10 rounded-xl bg-pcrm-50 dark:bg-pcrm-900/30 flex items-center justify-center">
                        <i class="bi bi-person text-lg text-pcrm-600 dark:text-pcrm-400"></i>
                    </div>
                    <span class="text-xs font-medium text-slate-700 dark:text-slate-300">Hồ sơ của tôi</span>
                </a>

                <a href="{{ route('rankings.index') }}" class="stat-card !p-4 flex flex-col items-center text-center gap-2 group">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center">
                        <i class="bi bi-trophy text-lg text-amber-600 dark:text-amber-400"></i>
                    </div>
                    <span class="text-xs font-medium text-slate-700 dark:text-slate-300">Bảng xếp hạng</span>
                </a>

                <a href="{{ route('notifications.index') }}" class="stat-card !p-4 flex flex-col items-center text-center gap-2 group">
                    <div class="w-10 h-10 rounded-xl bg-sky-50 dark:bg-sky-900/30 flex items-center justify-center">
                        <i class="bi bi-bell text-lg text-sky-600 dark:text-sky-400"></i>
                    </div>
                    <span class="text-xs font-medium text-slate-700 dark:text-slate-300">Thông báo</span>
                </a>

                @can('view-shift-schedules')
                <a href="{{ route('shift-schedules.index') }}" class="stat-card !p-4 flex flex-col items-center text-center gap-2 group">
                    <div class="w-10 h-10 rounded-xl bg-violet-50 dark:bg-violet-900/30 flex items-center justify-center">
                        <i class="bi bi-calendar-week text-lg text-violet-600 dark:text-violet-400"></i>
                    </div>
                    <span class="text-xs font-medium text-slate-700 dark:text-slate-300">Xếp ca</span>
                </a>
                @endcan

                @can('view-shifts')
                <a href="{{ route('shifts.index') }}" class="stat-card !p-4 flex flex-col items-center text-center gap-2 group">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center">
                        <i class="bi bi-clock-history text-lg text-emerald-600 dark:text-emerald-400"></i>
                    </div>
                    <span class="text-xs font-medium text-slate-700 dark:text-slate-300">Ca làm việc</span>
                </a>
                @endcan

                @can('view-attendance-locations')
                <a href="{{ route('attendance-locations.index') }}" class="stat-card !p-4 flex flex-col items-center text-center gap-2 group">
                    <div class="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-900/30 flex items-center justify-center">
                        <i class="bi bi-geo-alt text-lg text-rose-600 dark:text-rose-400"></i>
                    </div>
                    <span class="text-xs font-medium text-slate-700 dark:text-slate-300">Điểm chấm công</span>
                </a>
                @endcan

                @can('view-attendance')
                <a href="{{ route('attendance-logs.index') }}" class="stat-card !p-4 flex flex-col items-center text-center gap-2 group">
                    <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                        <i class="bi bi-clipboard-check text-lg text-slate-600 dark:text-slate-300"></i>
                    </div>
                    <span class="text-xs font-medium text-slate-700 dark:text-slate-300">Báo cáo chấm công</span>
                </a>
                @endcan

                {{-- <a href="/html/Luat_Thuong_Phat_NhanVien.html" target="_blank" class="stat-card !p-4 flex flex-col items-center text-center gap-2 group">
                    <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                        <i class="bi bi-file-earmark-text text-lg text-slate-600 dark:text-slate-300"></i>
                    </div>
                    <span class="text-xs font-medium text-slate-700 dark:text-slate-300">Nội quy công ty</span>
                </a> --}}
            </div>
        </div>
    </div>
@endsection

@push('modals')
    @include('components.shift-day-detail-modal')

    <div id="earlyConfirmModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
         onclick="if(event.target===this)closeModal('earlyConfirmModal')">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-sm p-4 sm:p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center shrink-0">
                    <i class="bi bi-clock-history text-amber-600 dark:text-amber-400"></i>
                </div>
                <h3 class="font-semibold text-slate-900 dark:text-white" id="earlyConfirmTitle">Xác nhận</h3>
            </div>
            <p class="text-sm text-slate-700 dark:text-slate-300 mb-5" id="earlyConfirmMessage"></p>
            <div class="flex gap-3">
                <button type="button" onclick="closeModal('earlyConfirmModal')" class="btn-secondary flex-1">Hủy</button>
                <button type="button" id="earlyConfirmProceedBtn" class="btn-primary flex-1">Xác nhận</button>
            </div>
        </div>
    </div>
@endpush

@push('scripts')
<script>
window.SCHED_PERMS = { canEdit: false, canDelete: false, canCreate: false, canSwap: false, hasUpcoming: false };

setInterval(function () {
    document.getElementById('liveClock').textContent = new Date().toLocaleTimeString('vi-VN');
}, 1000);

function showAttendanceMessage(shiftScheduleId, message, isError) {
    const el = document.getElementById('attendanceMessage-' + (shiftScheduleId ?? 0));
    if (!el) return;
    el.textContent = message;
    el.classList.remove('hidden', 'bg-red-50', 'text-red-700', 'bg-emerald-50', 'text-emerald-700');
    el.classList.add(isError ? 'bg-red-50' : 'bg-emerald-50', isError ? 'text-red-700' : 'text-emerald-700');
}

function openEarlyConfirmModal(message, onConfirm) {
    document.getElementById('earlyConfirmMessage').textContent = message;
    const btn = document.getElementById('earlyConfirmProceedBtn');
    const freshBtn = btn.cloneNode(true); // gỡ mọi listener 'confirm' cũ gắn từ lần mở trước
    btn.parentNode.replaceChild(freshBtn, btn);
    freshBtn.addEventListener('click', function () {
        closeModal('earlyConfirmModal');
        onConfirm();
    });
    openModal('earlyConfirmModal');
}

function doAttendance(type, shiftScheduleId, boundaries) {
    const suffix = shiftScheduleId ?? 0;
    const btnIn = document.getElementById('btnCheckIn-' + suffix);
    const btnOut = document.getElementById('btnCheckOut-' + suffix);
    const wasCheckedIn = btnIn.disabled;
    const wasCheckedOut = btnOut.disabled && wasCheckedIn;

    function proceed() {
        btnIn.disabled = true;
        btnOut.disabled = true;

        function submit(lat, lng) {
            fetch('{{ url("/attendance") }}/' + type, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ lat: lat, lng: lng, shift_schedule_id: shiftScheduleId }),
            })
            .then(res => res.json().then(data => ({ status: res.status, body: data })))
            .then(({ status, body }) => {
                showAttendanceMessage(shiftScheduleId, body.message, status !== 200);
                if (status === 200) {
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    btnIn.disabled = wasCheckedIn;
                    btnOut.disabled = !wasCheckedIn || wasCheckedOut;
                }
            })
            .catch(() => {
                showAttendanceMessage(shiftScheduleId, 'Có lỗi xảy ra, vui lòng thử lại.', true);
                btnIn.disabled = wasCheckedIn;
                btnOut.disabled = !wasCheckedIn || wasCheckedOut;
            });
        }

        if (!navigator.geolocation) {
            submit(null, null);
            return;
        }

        navigator.geolocation.getCurrentPosition(
            pos => submit(pos.coords.latitude, pos.coords.longitude),
            () => {
                showAttendanceMessage(shiftScheduleId, 'Không thể lấy vị trí GPS. Vui lòng cấp quyền định vị cho trình duyệt.', true);
                btnIn.disabled = wasCheckedIn;
                btnOut.disabled = !wasCheckedIn || wasCheckedOut;
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    }

    // Chỉ cảnh báo khi hành động thực sự vượt ngưỡng cho phép của ca — không cảnh báo cứng
    // theo giờ bắt đầu/kết thúc ca. Ca qua đêm / ca ngoài lịch không có mốc giờ (boundaries
    // rỗng) nên bỏ qua.
    const nowHM = new Date().toTimeString().slice(0, 5);

    if (type === 'check-out' && boundaries.earlyCheckoutBoundary && nowHM < boundaries.earlyCheckoutBoundary) {
        openEarlyConfirmModal(
            `Hiện tại là ${nowHM}, ca kết thúc lúc ${boundaries.endTime}. Bạn có chắc muốn ra ca sớm không?`,
            proceed
        );
        return;
    }

    proceed();
}
</script>
@endpush
