@extends('layouts.admin')

@section('title', 'Xác nhận công (HR)')
@section('page-title', 'Xác nhận công (HR)')
@section('breadcrumb', 'Ca làm việc & Chấm công')

@section('page-subtitle')
    Danh sách bảng công cần xác nhận
@endsection

@section('content')
    <div class="card">
        <x-table-toolbar :paginator="$employees">
            <button onclick="toggleFilterDrawer(true)" class="btn-secondary h-9 px-3 gap-1.5 text-xs font-black relative">
                <i class="bi bi-funnel"></i>
                <span>Bộ lọc</span>
                @if(request()->anyFilled(['employee_id', 'branch_id', 'team_id', 'month', 'year']))
                    <span class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-pcrm-600 rounded-full animate-pulse"></span>
                @endif
            </button>
        </x-table-toolbar>

        <div class="card-body p-0">
            <div class="table-container border-0 rounded-none">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th class="table-th">Nhân viên</th>
                            <th class="table-th">Chi nhánh</th>
                            <th class="table-th">Đội nhóm</th>
                            <th class="table-th text-center">Trạng thái</th>
                            <th class="table-th text-center">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employees as $employee)
                            @php $confirmation = $employee->timesheetConfirmations->first(); @endphp
                            <tr>
                                <td class="table-td">
                                    <div class="flex items-center gap-2.5">
                                        <x-employee-avatar :employee="$employee" size="w-8 h-8" />
                                        <div class="min-w-0">
                                            <span class="font-medium text-slate-800 dark:text-slate-100">{{ $employee->name }}</span>
                                            <span class="block text-xs text-slate-400">{{ $employee->code }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="table-td">{{ $employee->branch->name ?? '—' }}</td>
                                <td class="table-td">{{ $employee->team->name ?? '—' }}</td>
                                <td class="table-td text-center">
                                    @if($confirmation && $confirmation->isConfirmed())
                                        <span class="badge {{ $confirmation->statusBadgeClass() }}">{{ $confirmation->statusLabel() }}</span>
                                    @else
                                        <span class="badge badge-warning">Chưa xác nhận</span>
                                    @endif
                                </td>
                                <td class="table-td text-center">
                                    <a href="{{ route('timesheet-confirmations.show', [$employee, 'month' => $month, 'year' => $year]) }}"
                                        class="btn-ghost btn-sm">
                                        <i class="bi bi-eye"></i> Xem chi tiết
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="table-td text-center py-10 text-slate-400">Không có nhân viên phù hợp.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($employees->hasPages())
        <div class="px-4 py-3 border-t border-slate-100 dark:border-slate-700">
            {{ $employees->links() }}
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
            <h3 class="text-sm font-black text-slate-855 dark:text-slate-100 uppercase tracking-wide flex items-center gap-1.5">
                <i class="bi bi-funnel text-pcrm-600"></i> Bộ lọc tìm kiếm
            </h3>
            <button type="button" onclick="toggleFilterDrawer(false)" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-750 transition-colors">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form action="{{ route('timesheet-confirmations.index') }}" method="GET" class="flex-1 flex flex-col overflow-y-auto">
            <div class="p-5 space-y-4 flex-1">
                <div>
                    <x-employee-combobox name="employee_id" :employees="$allEmployees" :selected="request('employee_id')"
                        label="Nhân viên" placeholder="Tìm theo tên, mã NV..." :compact="true" />
                </div>
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
                    <label class="block text-xs font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wider mb-2">Tháng</label>
                    <select name="month_year" class="form-input text-sm w-full"
                        onchange="const [m,y]=this.value.split('-'); document.getElementById('monthField').value=m; document.getElementById('yearField').value=y;">
                        @foreach($monthOptions as $opt)
                            <option value="{{ $opt['month'] }}-{{ $opt['year'] }}" @selected($opt['month'] == $month && $opt['year'] == $year)>{{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                    <input type="hidden" id="monthField" name="month" value="{{ $month }}">
                    <input type="hidden" id="yearField" name="year" value="{{ $year }}">
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
            input.value = '';
        } else if (input.tagName === 'SELECT') {
            input.selectedIndex = 0;
        }
    });
    toggleFilterDrawer(false);
    window.location.href = "{{ route('timesheet-confirmations.index') }}";
}
</script>
@endpush
