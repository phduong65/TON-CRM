@extends('layouts.admin')

@section('title', 'Chấm công')
@section('page-title', 'Chấm công')
@section('breadcrumb', 'Ca làm việc & Chấm công')

@section('content')
    @php
        $todayLabel = now()->format('d/m/Y');
        $cellData = $shiftSchedules->map(fn($s) => [
            'id' => $s->id,
            'shift_id' => $s->shift_id,
            'shift_name' => $s->shift?->name,
            'shift_code' => $s->shift?->code,
            'start_time' => substr($s->shift?->start_time ?? '', 0, 5),
            'end_time' => substr($s->shift?->end_time ?? '', 0, 5),
            'is_wfh' => (bool) $s->shift?->isWfh(),
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

                @if($shiftSchedules->isNotEmpty())
                    <button type="button"
                        onclick="openDayDetailModal({{ $employee->id }}, {{ Illuminate\Support\Js::from($employee->name) }}, '{{ now()->toDateString() }}', {{ Illuminate\Support\Js::from($todayLabel) }}, {{ Illuminate\Support\Js::from($cellData) }}, { isOwnEmployee: true, dayIsFutureOrToday: true })"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium text-white/90 mt-5 hover:bg-white/10 transition"
                        style="background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.2);">
                        <i class="bi bi-clock-history"></i>
                        {{ $shiftSchedules->count() >= 2 ? $shiftSchedules->count() . ' ca hôm nay' : 'Ca hôm nay: ' . $shiftSchedules->first()->shift?->name }}
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

        {{-- ── 1 card / ca — chấm công riêng biệt theo từng ca ─────────────── --}}
        @forelse($shiftSchedules as $sched)
            @php $sid = $sched->id; @endphp
            <div class="card p-5 sm:p-6">
                <div class="flex items-center justify-between gap-2 mb-4">
                    <div class="min-w-0">
                        <p class="font-semibold text-slate-900 dark:text-white truncate">{{ $sched->shift?->name }}</p>
                        <p class="text-xs text-slate-400">
                            {{ substr($sched->shift?->start_time,0,5) }}–{{ substr($sched->shift?->end_time,0,5) }}
                        </p>
                    </div>
                    @if($sched->shift?->isWfh())
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
                            @else
                                <p class="text-slate-400 font-semibold text-sm sm:text-base truncate">Chưa check-out</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div id="attendanceMessage-{{ $sid }}" class="hidden mt-4 text-sm rounded-lg px-3 py-2 text-center"></div>

                <div class="grid grid-cols-2 gap-2 sm:gap-3 mt-4">
                    <button id="btnCheckIn-{{ $sid }}" onclick="doAttendance('check-in', {{ $sid }})"
                            class="btn-primary justify-center py-3 text-sm sm:text-base disabled:opacity-40 disabled:cursor-not-allowed disabled:pointer-events-none"
                            {{ $sched->attendanceLog?->check_in_at ? 'disabled' : '' }}>
                        <i class="bi bi-box-arrow-in-right"></i> Check-in
                    </button>
                    <button id="btnCheckOut-{{ $sid }}" onclick="doAttendance('check-out', {{ $sid }})"
                            class="btn-secondary justify-center py-3 text-sm sm:text-base disabled:opacity-40 disabled:cursor-not-allowed disabled:pointer-events-none"
                            {{ (!$sched->attendanceLog?->check_in_at || $sched->attendanceLog?->check_out_at) ? 'disabled' : '' }}>
                        <i class="bi bi-box-arrow-right"></i> Check-out
                    </button>
                </div>
            </div>
        @empty
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
                            @else
                                <p class="text-slate-400 font-semibold text-sm sm:text-base truncate">Chưa check-out</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div id="attendanceMessage-0" class="hidden mt-4 text-sm rounded-lg px-3 py-2 text-center"></div>

                <div class="grid grid-cols-2 gap-2 sm:gap-3 mt-4">
                    <button id="btnCheckIn-0" onclick="doAttendance('check-in', null)"
                            class="btn-primary justify-center py-3 text-sm sm:text-base disabled:opacity-40 disabled:cursor-not-allowed disabled:pointer-events-none"
                            {{ $unscheduledLog?->check_in_at ? 'disabled' : '' }}>
                        <i class="bi bi-box-arrow-in-right"></i> Check-in
                    </button>
                    <button id="btnCheckOut-0" onclick="doAttendance('check-out', null)"
                            class="btn-secondary justify-center py-3 text-sm sm:text-base disabled:opacity-40 disabled:cursor-not-allowed disabled:pointer-events-none"
                            {{ (!$unscheduledLog?->check_in_at || $unscheduledLog?->check_out_at) ? 'disabled' : '' }}>
                        <i class="bi bi-box-arrow-right"></i> Check-out
                    </button>
                </div>
            </div>
        @endforelse

        <p class="text-xs text-slate-400 text-center px-2">
            Hệ thống sẽ yêu cầu quyền truy cập vị trí (GPS) để xác thực bạn đang ở trong khu vực chấm công cho phép,
            trừ khi ca là WFH.
        </p>

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

                <a href="/html/Luat_Thuong_Phat_NhanVien.html" target="_blank" class="stat-card !p-4 flex flex-col items-center text-center gap-2 group">
                    <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                        <i class="bi bi-file-earmark-text text-lg text-slate-600 dark:text-slate-300"></i>
                    </div>
                    <span class="text-xs font-medium text-slate-700 dark:text-slate-300">Nội quy công ty</span>
                </a>
            </div>
        </div>
    </div>
@endsection

@push('modals')
    @include('components.shift-day-detail-modal')
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

function doAttendance(type, shiftScheduleId) {
    const suffix = shiftScheduleId ?? 0;
    const btnIn = document.getElementById('btnCheckIn-' + suffix);
    const btnOut = document.getElementById('btnCheckOut-' + suffix);
    const wasCheckedIn = btnIn.disabled;
    const wasCheckedOut = btnOut.disabled && wasCheckedIn;
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
</script>
@endpush
