@extends('layouts.admin')

@section('title', 'Cảnh báo Ca')

@section('content')
<div class="space-y-6">
    <!-- Breadcrumb & Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-1">
                <a href="{{ route('dashboard') }}" class="hover:text-indigo-600 dark:hover:text-indigo-400">Bảng điều khiển</a>
                <span>/</span>
                <a href="{{ route('attendance-logs.index') }}" class="hover:text-indigo-600 dark:hover:text-indigo-400">Chấm công</a>
                <span>/</span>
                <span class="text-slate-800 dark:text-slate-200 font-medium">Cảnh báo Ca</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill text-amber-500"></i>
                Cảnh báo thiếu Check-in / Check-out
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                Theo dõi các ca đã kết thúc nhưng chưa có dữ liệu check-in hoặc check-out để xử lý bổ sung hoặc miễn cảnh báo.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('operational-schedule.index') }}"
                class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700 shadow-sm transition-all">
                <i class="bi bi-calendar2-range text-indigo-500"></i>
                Lịch vận hành
            </a>

            <a href="{{ route('attendance-logs.index') }}"
                class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 shadow-sm shadow-indigo-600/30 transition-all">
                <i class="bi bi-journal-text"></i>
                Báo cáo chấm công
            </a>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3">
        <!-- Open / Seen -->
        <a href="{{ request()->fullUrlWithQuery(['status' => 'unresolved']) }}"
            class="bg-white dark:bg-slate-800 rounded-2xl p-3.5 shadow-sm border {{ $status === 'unresolved' ? 'border-amber-500 ring-2 ring-amber-500/20' : 'border-slate-200/80 dark:border-slate-700/80' }} transition-all">
            <p class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Chưa xử lý</p>
            <div class="text-xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ $totalOpen }}</div>
            <p class="text-[10px] text-slate-400 mt-0.5">Cần kiểm tra</p>
        </a>

        <!-- Missing check-in -->
        <a href="{{ request()->fullUrlWithQuery(['status' => 'unresolved', 'alert_type' => 'missing_check_in']) }}"
            class="bg-white dark:bg-slate-800 rounded-2xl p-3.5 shadow-sm border border-slate-200/80 dark:border-slate-700/80 hover:border-slate-300 dark:hover:border-slate-600 transition-all">
            <p class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Thiếu Check-in</p>
            <div class="text-xl font-bold text-red-600 dark:text-red-400 mt-1">{{ $totalMissingCheckIn }}</div>
            <p class="text-[10px] text-slate-400 mt-0.5">Không có log vào</p>
        </a>

        <!-- Missing check-out -->
        <a href="{{ request()->fullUrlWithQuery(['status' => 'unresolved', 'alert_type' => 'missing_check_out']) }}"
            class="bg-white dark:bg-slate-800 rounded-2xl p-3.5 shadow-sm border border-slate-200/80 dark:border-slate-700/80 hover:border-slate-300 dark:hover:border-slate-600 transition-all">
            <p class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Thiếu Check-out</p>
            <div class="text-xl font-bold text-orange-600 dark:text-orange-400 mt-1">{{ $totalMissingCheckOut }}</div>
            <p class="text-[10px] text-slate-400 mt-0.5">Chưa bấm ra ca</p>
        </a>

        <!-- Resolved -->
        <a href="{{ request()->fullUrlWithQuery(['status' => 'resolved']) }}"
            class="bg-white dark:bg-slate-800 rounded-2xl p-3.5 shadow-sm border {{ $status === 'resolved' ? 'border-emerald-500 ring-2 ring-emerald-500/20' : 'border-slate-200/80 dark:border-slate-700/80' }} transition-all">
            <p class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Đã bổ sung log</p>
            <div class="text-xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ $totalResolved }}</div>
            <p class="text-[10px] text-slate-400 mt-0.5">Đã giải quyết</p>
        </a>

        <!-- Excused -->
        <a href="{{ request()->fullUrlWithQuery(['status' => 'excused']) }}"
            class="bg-white dark:bg-slate-800 rounded-2xl p-3.5 shadow-sm border {{ $status === 'excused' ? 'border-indigo-500 ring-2 ring-indigo-500/20' : 'border-slate-200/80 dark:border-slate-700/80' }} transition-all">
            <p class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Đã miễn cảnh báo</p>
            <div class="text-xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ $totalExcused }}</div>
            <p class="text-[10px] text-slate-400 mt-0.5">Có lý do xác nhận</p>
        </a>
    </div>

    <!-- Filter Form -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200/80 dark:border-slate-700/80 p-4">
        <form method="GET" action="{{ route('attendance-alerts.index') }}" class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 items-end">
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Chi nhánh</label>
                <select name="branch_id" onchange="this.form.submit()"
                    class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white px-3 py-2 focus:ring-2 focus:ring-indigo-500">
                    <option value="">-- Tất cả chi nhánh --</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}" {{ request('branch_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Bộ phận</label>
                <select name="team_id" onchange="this.form.submit()"
                    class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white px-3 py-2 focus:ring-2 focus:ring-indigo-500">
                    <option value="">-- Tất cả bộ phận --</option>
                    @foreach($teams as $t)
                        <option value="{{ $t->id }}" {{ request('team_id') == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Trạng thái</label>
                <select name="status" onchange="this.form.submit()"
                    class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white px-3 py-2 focus:ring-2 focus:ring-indigo-500">
                    <option value="unresolved" {{ $status === 'unresolved' ? 'selected' : '' }}>Chưa xử lý (Mở + Đã xem)</option>
                    <option value="resolved" {{ $status === 'resolved' ? 'selected' : '' }}>Đã bổ sung log</option>
                    <option value="excused" {{ $status === 'excused' ? 'selected' : '' }}>Đã miễn cảnh báo</option>
                    <option value="all" {{ $status === 'all' ? 'selected' : '' }}>Tất cả</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Loại cảnh báo</label>
                <select name="alert_type" onchange="this.form.submit()"
                    class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white px-3 py-2 focus:ring-2 focus:ring-indigo-500">
                    <option value="">-- Tất cả loại --</option>
                    <option value="missing_check_in" {{ request('alert_type') === 'missing_check_in' ? 'selected' : '' }}>Thiếu Check-in</option>
                    <option value="missing_check_out" {{ request('alert_type') === 'missing_check_out' ? 'selected' : '' }}>Thiếu Check-out</option>
                </select>
            </div>

            <div class="col-span-2 lg:col-span-1">
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Tìm nhân viên</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Tên hoặc mã NV..."
                    class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white px-3 py-2 focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <a href="{{ route('attendance-alerts.index') }}"
                    class="inline-flex items-center justify-center w-full px-3 py-2 text-xs font-medium text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-700 rounded-xl hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors">
                    <i class="bi bi-arrow-counterclockwise mr-1.5"></i> Đặt lại
                </a>
            </div>
        </form>
    </div>

    <!-- Alerts Table -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200/80 dark:border-slate-700/80 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 uppercase tracking-wider font-semibold">
                        <th class="px-3 py-3">Nhân viên</th>
                        <th class="px-3 py-3">Ca làm việc</th>
                        <th class="px-3 py-3">Cảnh báo</th>
                        <th class="px-3 py-3">Trạng thái / Ghi chú</th>
                        <th class="px-3 py-3 text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($alerts as $alert)
                        @php
                            $emp = $alert->employee;
                            $sch = $alert->shiftSchedule;
                            $eff = $sch?->effectiveShift();
                            $timeStr = $eff ? (substr($eff->start_time, 0, 5) . ' – ' . substr($eff->end_time, 0, 5)) : '--:--';
                            $isOpen = in_array($alert->status, ['open', 'seen']);
                        @endphp
                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-700/40 transition-colors align-top">
                            <td class="px-3 py-3">
                                <div class="font-semibold text-slate-900 dark:text-white">{{ $emp?->name }}</div>
                                <div class="text-[10px] text-slate-400 font-mono">{{ $emp?->code }}</div>
                                <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">
                                    {{ $emp?->branch?->name }}@if($emp?->team) · {{ $emp->team->name }}@endif
                                </div>
                            </td>
                            <td class="px-3 py-3">
                                <div class="font-semibold text-slate-800 dark:text-slate-200">{{ $sch?->work_date?->format('d/m/Y') }}</div>
                                <div class="text-[11px] text-slate-500 dark:text-slate-400">{{ $sch?->shift?->name ?? 'Ca linh hoạt' }}</div>
                                <div class="text-[11px] font-mono text-slate-400">{{ $timeStr }}</div>
                            </td>
                            <td class="px-3 py-3">
                                @if($alert->alert_type === 'missing_check_in')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold whitespace-nowrap bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300">
                                        <i class="bi bi-box-arrow-in-right"></i> Thiếu Check-in
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold whitespace-nowrap bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300">
                                        <i class="bi bi-box-arrow-right"></i> Thiếu Check-out
                                    </span>
                                @endif
                                <div class="text-[10px] text-slate-400 mt-1">Phát hiện {{ $alert->triggered_at?->format('H:i d/m/Y') }}</div>
                            </td>
                            <td class="px-3 py-3 text-[11px] text-slate-600 dark:text-slate-400 max-w-[220px]">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $alert->statusBadgeClass() }}">
                                    {{ $alert->statusLabel() }}
                                </span>
                                @if($alert->status === 'open')
                                    <p class="mt-1 text-[10px] font-semibold text-amber-600 dark:text-amber-400"><i class="bi bi-eye-slash"></i> NV chưa xem</p>
                                @elseif($alert->seen_at)
                                    <p class="mt-1 text-[10px] text-blue-600 dark:text-blue-400"><i class="bi bi-eye"></i> NV đã xem lúc {{ $alert->seen_at->format('H:i d/m/Y') }}</p>
                                @endif
                                @if($alert->resolution_note)
                                    <p class="mt-1 break-words" title="{{ $alert->resolution_note }}">{{ \Illuminate\Support\Str::limit($alert->resolution_note, 80) }}</p>
                                @endif
                                @if($alert->resolvedBy)
                                    <p class="text-[10px] text-slate-400">Bởi: {{ $alert->resolvedBy->name }} ({{ $alert->resolved_at?->format('d/m/Y') }})</p>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-right">
                                <div class="inline-flex flex-wrap items-center justify-end gap-1.5">
                                    @if($isOpen)
                                        @can('manage-attendance-alerts')
                                        <button type="button"
                                            onclick="openExcuseModal({{ $alert->id }}, '{{ addslashes($emp?->name) }}', '{{ $sch?->work_date?->format('d/m/Y') }} ({{ $sch?->shift?->name }})', '{{ $alert->alertTypeLabel() }}')"
                                            class="px-2 py-1 text-[11px] font-medium text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 rounded-lg transition-colors"
                                            title="Miễn cảnh báo">
                                            Miễn
                                        </button>
                                        @endcan

                                        @can('create-attendance-logs')
                                        <a href="{{ route('attendance-logs.index', ['employee_id' => $emp?->id, 'work_date' => $sch?->work_date?->toDateString()]) }}"
                                            class="px-2 py-1 text-[11px] font-semibold whitespace-nowrap text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 rounded-lg transition-colors"
                                            title="Chuyển tới trang chấm công để bổ sung log">
                                            Bổ sung log
                                        </a>
                                        @endcan
                                    @else
                                        <span class="text-slate-400 text-xs">—</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5"class="px-6 py-12 text-center text-slate-400 dark:text-slate-500">
                                <i class="bi bi-check2-circle text-3xl mb-2 block text-emerald-500"></i>
                                Không có cảnh báo chấm công nào phù hợp với bộ lọc.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($alerts->hasPages())
            <div class="px-4 py-3 border-t border-slate-100 dark:border-slate-700">
                {{ $alerts->links() }}
            </div>
        @endif
    </div>
</div>

@include('attendance-alerts.partials.excuse-modal')

@push('scripts')
<script>
function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('hidden');
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('hidden');
}

function openExcuseModal(id, empName, shiftInfo, alertInfo) {
    document.getElementById('excuseEmployeeName').textContent = empName;
    document.getElementById('excuseShiftInfo').textContent = shiftInfo;
    document.getElementById('excuseAlertInfo').textContent = alertInfo;
    document.getElementById('excuseAlertForm').action = '/attendance-alerts/' + id + '/excuse';
    openModal('excuseAlertModal');
}
</script>
@endpush
@endsection
