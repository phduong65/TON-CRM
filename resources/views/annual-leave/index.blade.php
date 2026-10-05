@extends('layouts.admin')

@section('title', 'Phép năm')
@section('page-title', 'Phép năm')
@section('breadcrumb', 'Ca làm việc & Chấm công / Phép năm')

@section('page-subtitle')
    Số ngày phép năm (được hưởng/đã dùng/còn lại) của tất cả nhân viên văn phòng đủ điều kiện —
    +1 ngày cho mỗi tháng làm việc trọn vẹn, tối đa 12 ngày/năm, tính lại từ 1/1 mỗi năm.
@endsection

@section('content')
    <div class="card">
        <x-table-toolbar>
            <button onclick="toggleFilterDrawer(true)" class="btn-secondary h-9 px-3 gap-1.5 text-xs font-black relative">
                <i class="bi bi-funnel"></i>
                <span>Bộ lọc</span>
                @if(request()->anyFilled(['branch_id', 'search']))
                    <span class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-pcrm-600 rounded-full animate-pulse"></span>
                @endif
            </button>
        </x-table-toolbar>

        <div class="card-body p-0">
            <div class="table-container border-0 rounded-none">
                <table class="table-base min-w-[760px]">
                    <thead>
                        <tr>
                            <th class="table-th">Nhân viên</th>
                            <th class="table-th">Chi nhánh / Phòng ban</th>
                            <th class="table-th text-center">Được hưởng</th>
                            <th class="table-th text-center">Đã dùng</th>
                            <th class="table-th text-center">Còn lại</th>
                            <th class="table-th">Tiến trình</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                        @php
                            $percent = $row['entitled'] > 0
                                ? min(100, round($row['used'] / $row['entitled'] * 100))
                                : 0;
                            $fmt = fn($n) => rtrim(rtrim(number_format($n, 1), '0'), '.');
                        @endphp
                        <tr class="table-tr-hover">
                            <td class="table-td">
                                <a href="{{ route('employees.show', $row['employee']) }}" class="font-medium hover:underline">
                                    {{ $row['employee']->name }}
                                </a>
                                <p class="text-xs text-slate-400">{{ $row['employee']->code }}</p>
                            </td>
                            <td class="table-td text-sm">
                                {{ $row['employee']->branch?->name ?? '—' }}
                                <p class="text-xs text-slate-400">{{ $row['employee']->team?->name ?? '—' }}</p>
                            </td>
                            <td class="table-td text-center">{{ $fmt($row['entitled']) }}</td>
                            <td class="table-td text-center">{{ $fmt($row['used']) }}</td>
                            <td class="table-td text-center">
                                <span class="font-semibold {{ $row['remaining'] <= 0 ? 'text-red-500' : 'text-emerald-600 dark:text-emerald-400' }}">
                                    {{ $fmt($row['remaining']) }}
                                </span>
                            </td>
                            <td class="table-td">
                                <div class="w-full max-w-[140px] h-2 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                                    <div class="h-full bg-pcrm-500" style="width: {{ $percent }}%"></div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="table-td text-center py-8 text-slate-400">
                                <i class="bi bi-calendar-x text-3xl mb-2 block opacity-40"></i>
                                <p>Không có nhân viên văn phòng nào đủ điều kiện nghỉ phép năm</p>
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
        <form action="{{ route('annual-leave.index') }}" method="GET" class="flex-1 flex flex-col overflow-y-auto">
            <div class="p-5 space-y-4 flex-1">
                <div>
                    <label class="block text-xs font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wider mb-2">Năm</label>
                    <select name="year" class="form-input text-sm w-full">
                        @foreach($yearOptions as $y)
                            <option value="{{ $y }}" @selected($year == $y)>{{ $y }}</option>
                        @endforeach
                    </select>
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
                    <label class="block text-xs font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wider mb-2">Tìm kiếm</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Tên, mã NV..." class="form-input text-sm w-full">
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
            if (input.name !== 'year') {
                input.selectedIndex = 0;
            }
        }
    });
    toggleFilterDrawer(false);
    drawer.querySelector('form').submit();
}
</script>
@endpush
