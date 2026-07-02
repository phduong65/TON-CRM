@extends('layouts.admin')

@section('title', 'Báo cáo chấm công')
@section('page-title', 'Báo cáo chấm công')
@section('breadcrumb', 'Ca làm việc & Chấm công')

@section('content')
    <div class="page-header">
        <div>
            <p class="page-subtitle">Lịch sử check-in/check-out của nhân viên</p>
        </div>
    </div>

    <div class="card">
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700">
            <form action="{{ route('attendance-logs.index') }}" method="GET" class="flex flex-wrap items-end gap-2">
                <div class="min-w-[220px]">
                    <x-employee-combobox name="employee_id" :employees="$employees" :selected="request('employee_id')"
                        label="Nhân viên" placeholder="Tìm theo tên, mã NV..." />
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
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Từ ngày</label>
                    <input type="date" id="dateFromInput" name="date_from" value="{{ request('date_from') }}" class="form-input h-9 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Đến ngày</label>
                    <input type="date" id="dateToInput" name="date_to" value="{{ request('date_to') }}" class="form-input h-9 text-sm">
                </div>
                <div class="flex items-center gap-1.5">
                    <button type="button" onclick="setAttendanceQuickRange('week')"
                        class="h-9 px-3 text-xs font-medium rounded-lg bg-pcrm-50 text-pcrm-700 hover:bg-pcrm-100 dark:bg-pcrm-900/30 dark:text-pcrm-400 dark:hover:bg-pcrm-900/50 transition-colors">
                        Tuần này
                    </button>
                    <button type="button" onclick="setAttendanceQuickRange('month')"
                        class="h-9 px-3 text-xs font-medium rounded-lg bg-pcrm-50 text-pcrm-700 hover:bg-pcrm-100 dark:bg-pcrm-900/30 dark:text-pcrm-400 dark:hover:bg-pcrm-900/50 transition-colors">
                        Tháng này
                    </button>
                </div>
                <button type="submit" class="btn-primary h-9 px-4 text-sm gap-1.5">
                    <i class="bi bi-funnel text-xs"></i> Lọc
                </button>
                @if(request()->anyFilled(['branch_id', 'team_id', 'employee_id', 'date_from', 'date_to']))
                <a href="{{ route('attendance-logs.index') }}"
                   class="btn-secondary h-9 px-3 inline-flex items-center gap-1 text-sm">
                    <i class="bi bi-x text-sm"></i>
                </a>
                @endif
                @can('export-attendance')
                <div class="relative" id="exportDropdown">
                    <button type="button" onclick="toggleExportDropdown()" class="btn-secondary h-9 px-4 text-sm gap-1.5">
                        <i class="bi bi-file-earmark-excel text-xs"></i> Xuất Excel <i class="bi bi-chevron-down text-xs ml-0.5"></i>
                    </button>
                    <div id="exportDropdownMenu"
                         class="hidden absolute right-0 mt-1 w-64 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-lg z-30 py-1">
                        <button type="submit" formaction="{{ route('attendance-logs.export') }}"
                                class="w-full text-left px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-700 flex items-start gap-2">
                            <i class="bi bi-list-ul text-slate-400 mt-0.5"></i>
                            <span>
                                <span class="block font-medium text-slate-700 dark:text-slate-200">Danh sách chi tiết</span>
                                <span class="block text-xs text-slate-400">Theo bộ lọc hiện tại</span>
                            </span>
                        </button>
                        <button type="button" onclick="closeExportDropdown(); openModal('exportTimesheetModal')"
                                class="w-full text-left px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-700 flex items-start gap-2">
                            <i class="bi bi-grid-3x3-gap text-slate-400 mt-0.5"></i>
                            <span>
                                <span class="block font-medium text-slate-700 dark:text-slate-200">Bảng chấm công (theo mẫu)</span>
                                <span class="block text-xs text-slate-400">Theo tuần / tháng / khoảng ngày tùy chọn</span>
                            </span>
                        </button>
                    </div>
                </div>
                @endcan
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-container border-0 rounded-none">
                <table class="table-base min-w-[900px]">
                    <thead>
                        <tr>
                            <th class="table-th">Ngày</th>
                            <th class="table-th">Nhân viên</th>
                            <th class="table-th">Ca</th>
                            <th class="table-th text-center">Check-in</th>
                            <th class="table-th text-center">Check-out</th>
                            <th class="table-th text-center">Trễ/Sớm</th>
                            <th class="table-th text-center">Phương thức</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                        <tr class="table-tr-hover">
                            <td class="table-td text-sm">{{ $log->work_date->format('d/m/Y') }}</td>
                            <td class="table-td">
                                <p class="font-medium">{{ $log->employee?->name ?? '—' }}</p>
                                <p class="text-xs text-slate-400">{{ $log->employee?->team?->name ?? '—' }}</p>
                            </td>
                            <td class="table-td text-sm">{{ $log->shiftSchedule?->shift?->name ?? '—' }}</td>
                            <td class="table-td text-center text-sm">
                                {{ $log->check_in_at?->format('H:i:s') ?? '—' }}
                            </td>
                            <td class="table-td text-center text-sm">
                                {{ $log->check_out_at?->format('H:i:s') ?? '—' }}
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
                            <td colspan="7" class="table-td text-center py-8 text-slate-400">
                                <i class="bi bi-calendar-x text-3xl mb-2 block opacity-40"></i>
                                <p>Chưa có dữ liệu chấm công</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($logs->hasPages())
        <div class="card-footer">
            {{ $logs->links() }}
        </div>
        @endif
    </div>
@endsection

@can('export-attendance')
@push('modals')
    <x-export-range-modal id="exportTimesheetModal" title="Xuất Bảng chấm công"
        :export-url="route('attendance-logs.export-timesheet')" :ref-date="now()"
        :hidden="['branch_id' => request('branch_id'), 'team_id' => request('team_id'), 'employee_id' => request('employee_id')]" />
@endpush
@endcan

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

function toggleExportDropdown() {
    document.getElementById('exportDropdownMenu').classList.toggle('hidden');
}
function closeExportDropdown() {
    document.getElementById('exportDropdownMenu').classList.add('hidden');
}
document.addEventListener('click', function(e) {
    const wrap = document.getElementById('exportDropdown');
    const menu = document.getElementById('exportDropdownMenu');
    if (wrap && menu && !wrap.contains(e.target)) {
        menu.classList.add('hidden');
    }
});
</script>
@endpush
