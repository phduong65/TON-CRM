@extends('layouts.admin')

@section('title', 'Lịch sử chấm công')
@section('page-title', 'Lịch sử chấm công')
@section('page-subtitle', 'Lịch sử check-in / check-out của bạn theo ngày')
@section('breadcrumb', 'Ca làm việc & Chấm công')

@section('content')
    @php
        // Nhân viên part_time trả lương theo giờ — hiển thị "Giờ công" là chỉ số chính, ẩn "Công"
        // (quy đổi theo giờ chuẩn ca fulltime, không áp dụng cho part_time).
        $isPartTime = $employee->employment_type === 'part_time';
    @endphp

    <div class="card">
        <x-table-toolbar :paginator="$logs" label="ngày công">
            <button onclick="toggleFilterDrawer(true)" class="btn-secondary h-9 px-3 gap-1.5 text-xs font-black relative">
                <i class="bi bi-funnel"></i>
                <span>Bộ lọc</span>
                @if(request()->anyFilled(['date_from', 'date_to']))
                    <span class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-pcrm-600 rounded-full animate-pulse"></span>
                @endif
            </button>
        </x-table-toolbar>
        <div class="card-body p-0">
            @if ($isMobileDevice)
                {{-- Mobile Optimized Glassmorphic Cards List --}}
                <div class="p-3 space-y-3 bg-transparent">
                    @forelse($logs as $log)
                        @php
                            $leaveFraction = $partialLeaveIndex[$log->employee_id . '_' . $log->work_date->toDateString() . '_' . $log->shift_schedule_id] ?? null;
                            $workedHours   = $log->netWorkedHours();
                            $cong          = $log->computeCong(null, $leaveFraction);
                            $fmt           = fn($n) => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
                        @endphp
                        
                        <div class="backdrop-blur-md bg-white/80 dark:bg-slate-900/60 border border-slate-200/40 dark:border-slate-800/60 rounded-2xl p-4 shadow-sm relative flex flex-col justify-between">
                            <!-- Top Date and Shift -->
                            <div class="flex items-center justify-between border-b border-slate-100/50 dark:border-slate-800/30 pb-2 mb-2.5">
                                <span class="text-sm font-extrabold text-slate-800 dark:text-slate-200">
                                    {{ $log->work_date->format('d/m/Y') }}
                                </span>
                                <span class="text-xs font-bold text-pcrm-600 dark:text-pcrm-400 bg-pcrm-50 dark:bg-pcrm-950/40 px-2.5 py-0.5 rounded-full border border-pcrm-100/10">
                                    {{ $log->shiftSchedule?->shift?->name ?? 'Ca tự do' }}
                                </span>
                            </div>

                            <!-- Middle Times & Details -->
                            <div class="grid grid-cols-2 gap-x-2 gap-y-3 text-xs">
                                <div>
                                    <p class="text-slate-400 dark:text-slate-500 font-bold uppercase tracking-wider text-[9px]">Vào ca</p>
                                    <p class="font-extrabold text-slate-800 dark:text-slate-200 mt-0.5">
                                        {{ $log->check_in_at?->format('H:i:s') ?? '—' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-slate-400 dark:text-slate-500 font-bold uppercase tracking-wider text-[9px]">Ra ca</p>
                                    <p class="font-extrabold text-slate-800 dark:text-slate-200 mt-0.5 flex items-center">
                                        {{ $log->check_out_at?->format('H:i:s') ?? '—' }}
                                        @if($log->deviceChanged())
                                            <i class="bi bi-exclamation-triangle-fill text-amber-500 text-[10px] ml-1"
                                               title="Chấm công bằng thiết bị khác với lúc check-in"></i>
                                        @endif
                                    </p>
                                </div>
                                <div>
                                    <p class="text-slate-400 dark:text-slate-500 font-bold uppercase tracking-wider text-[9px]">Giờ công</p>
                                    <p class="font-extrabold text-slate-700 dark:text-slate-300 mt-0.5">
                                        {{ $workedHours !== null ? $fmt($workedHours) . 'h' : '—' }}
                                    </p>
                                </div>
                                @unless($isPartTime)
                                    <div>
                                        <p class="text-slate-400 dark:text-slate-500 font-bold uppercase tracking-wider text-[9px]">Tính công</p>
                                        <p class="font-extrabold text-slate-750 dark:text-slate-300 mt-0.5">
                                            @if($cong !== null)
                                                <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 dark:bg-blue-950/40 text-blue-650 dark:text-blue-400 border border-blue-100 dark:border-blue-900/30" @if($leaveFraction !== null) title="Đã trừ {{ $fmt($leaveFraction) }} công nghỉ" @endif>
                                                    {{ $fmt($cong) }} công
                                                </span>
                                            @else
                                                —
                                            @endif
                                        </p>
                                    </div>
                                @endunless
                            </div>

                            <!-- Bottom Status & Method -->
                            <div class="flex items-center justify-between mt-3.5 pt-2.5 border-t border-slate-100/50 dark:border-slate-800/30 text-[10px] font-bold">
                                <!-- Late/Early badges -->
                                <div class="flex flex-wrap gap-1">
                                    @if($log->late_minutes > 0)
                                        <span class="px-1.5 py-0.5 rounded bg-amber-50 dark:bg-amber-950/30 text-amber-600 dark:text-amber-400 border border-amber-100/30 dark:border-amber-900/20">Trễ {{ $log->late_minutes }}p</span>
                                    @endif
                                    @if($log->early_minutes > 0)
                                        <span class="px-1.5 py-0.5 rounded bg-amber-50 dark:bg-amber-950/30 text-amber-600 dark:text-amber-400 border border-amber-100/30 dark:border-amber-900/20">Sớm {{ $log->early_minutes }}p</span>
                                    @endif
                                    @if($log->late_minutes == 0 && $log->early_minutes == 0)
                                        <span class="px-1.5 py-0.5 rounded bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400 border border-emerald-100/30 dark:border-emerald-900/20">Đúng giờ</span>
                                    @endif
                                    @if($log->overtime_hours > 0)
                                        <span class="px-1.5 py-0.5 rounded bg-rose-50 dark:bg-rose-950/30 text-rose-600 dark:text-rose-400 border border-rose-100/30 dark:border-rose-900/20">+{{ $fmt((float) $log->overtime_hours) }}h OT</span>
                                    @endif
                                </div>
                                
                                <!-- Method -->
                                <span class="text-slate-400 dark:text-slate-500 uppercase text-[9px] tracking-wider">
                                    {{ strtoupper($log->check_in_method ?? '—') }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="py-12 text-center text-slate-400 dark:text-slate-500">
                            <i class="bi bi-calendar-x text-4xl mb-3 block opacity-40"></i>
                            <p class="text-sm font-medium">Chưa có dữ liệu chấm công</p>
                        </div>
                    @endforelse
                </div>
            @else
                {{-- Desktop View --}}
                <div class="table-container border-0 rounded-none">
                    <table class="table-base min-w-[800px]">
                        <thead>
                            <tr>
                                <th class="table-th">Ngày</th>
                                <th class="table-th">Ca</th>
                                <th class="table-th text-center">Check-in</th>
                                <th class="table-th text-center">Check-out</th>
                                <th class="table-th text-center">Giờ công</th>
                                @unless($isPartTime)
                                    <th class="table-th text-center">Công</th>
                                @endunless
                                <th class="table-th text-center">Tăng ca</th>
                                <th class="table-th text-center">Trễ/Sớm</th>
                                <th class="table-th text-center">Phương thức</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                            @php
                                $leaveFraction = $partialLeaveIndex[$log->employee_id . '_' . $log->work_date->toDateString() . '_' . $log->shift_schedule_id] ?? null;
                                $workedHours   = $log->netWorkedHours();
                                $cong          = $log->computeCong(null, $leaveFraction);
                                $fmt           = fn($n) => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
                            @endphp
                            <tr class="table-tr-hover">
                                <td class="table-td text-sm">{{ $log->work_date->format('d/m/Y') }}</td>
                                <td class="table-td text-sm">{{ $log->shiftSchedule?->shift?->name ?? '—' }}</td>
                                <td class="table-td text-center text-sm">
                                    {{ $log->check_in_at?->format('H:i:s') ?? '—' }}
                                </td>
                                <td class="table-td text-center text-sm">
                                    {{ $log->check_out_at?->format('H:i:s') ?? '—' }}
                                    @if($log->deviceChanged())
                                        <i class="bi bi-exclamation-triangle-fill text-amber-500 text-xs ml-1"
                                           title="Chấm công bằng thiết bị khác với lúc check-in"></i>
                                    @endif
                                </td>
                                <td class="table-td text-center text-sm text-slate-500">
                                    {{ $workedHours !== null ? $fmt($workedHours) . 'h' : '—' }}
                                </td>
                                @unless($isPartTime)
                                    <td class="table-td text-center">
                                        @if($cong !== null)
                                            <span class="badge badge-info font-semibold" @if($leaveFraction !== null) title="Đã trừ {{ $fmt($leaveFraction) }} công nghỉ theo giờ đã duyệt" @endif>{{ $fmt($cong) }} công</span>
                                        @else
                                            <span class="text-slate-400 text-sm">—</span>
                                        @endif
                                    </td>
                                @endunless
                                <td class="table-td text-center">
                                    @if($log->overtime_hours > 0)
                                        <span class="badge badge-danger font-semibold">+{{ $fmt((float) $log->overtime_hours) }}h</span>
                                    @else
                                        <span class="text-slate-300 dark:text-slate-600 text-sm">—</span>
                                    @endif
                                </td>
                                <td class="table-td text-center text-xs">
                                    @if($log->late_minutes > 0)
                                        <span class="badge badge-warning">Trễ {{ $log->late_minutes }}p</span>
                                    @endif
                                    @if($log->early_minutes > 0)
                                        <span class="badge badge-warning">Sớm {{ $log->early_minutes }}p</span>
                                    @endif
                                    @if($log->late_minutes == 0 && $log->early_minutes == 0)
                                        <span class="badge badge-success">Đúng giờ</span>
                                    @endif
                                </td>
                                <td class="table-td text-center text-xs text-slate-500">
                                    {{ strtoupper($log->check_in_method ?? '—') }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="table-td text-center py-8 text-slate-400">
                                    <i class="bi bi-calendar-x text-3xl mb-2 block opacity-40"></i>
                                    <p>Chưa có dữ liệu chấm công</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @if($logs->hasPages())
        <div class="card-footer">
            {{ $logs->links() }}
        </div>
        @endif
    </div>
@endsection

@push('modals')
    <!-- Overlay for filter drawer -->
    <div id="filterDrawerOverlay" class="fixed inset-0 bg-black/40 z-40 hidden opacity-0 transition-opacity duration-300 pointer-events-none" onclick="toggleFilterDrawer(false)"></div>

    <!-- Right-Side Filter Drawer -->
    <aside id="filterDrawer" class="fixed inset-y-0 right-0 z-50 w-full max-w-xs sm:max-w-sm bg-white dark:bg-slate-900 border-l border-slate-200 dark:border-slate-800 shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between flex-shrink-0">
            <h3 class="text-sm font-black text-slate-850 dark:text-slate-100 uppercase tracking-wide flex items-center gap-1.5">
                <i class="bi bi-funnel text-pcrm-600"></i> Bộ lọc tìm kiếm
            </h3>
            <button type="button" onclick="toggleFilterDrawer(false)" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-750 transition-colors">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form action="{{ route('my-attendance-logs.index') }}" method="GET" class="flex-1 flex flex-col overflow-y-auto">
            <div class="p-5 space-y-4 flex-1">
                <div>
                    <label class="block text-xs font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wider mb-2">Từ ngày</label>
                    <input type="date" id="dateFromInput" name="date_from" value="{{ request('date_from') }}" class="form-input text-sm w-full">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wider mb-2">Đến ngày</label>
                    <input type="date" id="dateToInput" name="date_to" value="{{ request('date_to') }}" class="form-input text-sm w-full">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wider mb-2">Chọn nhanh</label>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" onclick="setAttendanceQuickRange('week')"
                            class="py-2.5 px-3 text-xs font-bold rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-750 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-750 transition-all shadow-sm">
                            Tuần này
                        </button>
                        <button type="button" onclick="setAttendanceQuickRange('month')"
                            class="py-2.5 px-3 text-xs font-bold rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-750 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-750 transition-all shadow-sm">
                            Tháng này
                        </button>
                    </div>
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
function pad2(n) { return String(n).padStart(2, '0'); }
function toDateInputValue(d) { return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate()); }

function setAttendanceQuickRange(type) {
    const today = new Date();
    let from, to;

    if (type === 'week') {
        const day = (today.getDay() + 6) % 7; // 0 = Monday
        from = new Date(today); from.setDate(today.getDate() - day);
        to = new Date(from); to.setDate(from.getDate() + 6);
    } else {
        from = new Date(today.getFullYear(), today.getMonth(), 1);
        to = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    }

    document.getElementById('dateFromInput').value = toDateInputValue(from);
    document.getElementById('dateToInput').value = toDateInputValue(to);
}

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
    document.getElementById('dateFromInput').value = '';
    document.getElementById('dateToInput').value = '';
    toggleFilterDrawer(false);
    window.location.href = "{{ route('my-attendance-logs.index') }}";
}
</script>
@endpush
