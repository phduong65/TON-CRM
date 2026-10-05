@extends('layouts.admin')

@section('title', 'Đổi ca')
@section('page-title', 'Đổi ca làm việc')
@section('breadcrumb', 'Ca làm việc & Chấm công')

@section('page-subtitle')
    @if($isApprover)
    Danh sách yêu cầu đổi ca giữa các nhân viên
    @else
    Yêu cầu đổi ca bạn đã gửi hoặc được chọn để đổi
    @endif
@endsection

@section('content')
    <div class="rounded-xl bg-sky-50 dark:bg-sky-900/20 border border-sky-100 dark:border-sky-900/40 px-4 py-3 mb-4 flex items-start gap-3">
        <i class="bi bi-info-circle text-sky-500 mt-0.5"></i>
        <p class="text-sm text-sky-700 dark:text-sky-400">
            Để tạo yêu cầu đổi ca, vào trang
            <a href="{{ route('shift-schedules.index') }}" class="font-semibold underline">Xếp ca</a>,
            tìm ca của đồng nghiệp muốn đổi và bấm nút <strong>Đổi ca</strong> ngay trên ô lịch đó.
        </p>
    </div>

    <div class="card">
        <x-table-toolbar :paginator="$swapRequests">
            <button onclick="toggleFilterDrawer(true)" class="btn-secondary h-9 px-3 gap-1.5 text-xs font-black relative">
                <i class="bi bi-funnel"></i>
                <span>Bộ lọc</span>
                @if(request()->anyFilled(['employee_id', 'status']))
                    <span class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-pcrm-600 rounded-full animate-pulse"></span>
                @endif
            </button>
        </x-table-toolbar>

        <div class="card-body p-0">
            <div class="table-container border-0 rounded-none">
                <table class="table-base min-w-[900px]">
                    <thead>
                        <tr>
                            <th class="table-th">Mã yêu cầu</th>
                            <th class="table-th" data-mcard-title>Người yêu cầu</th>
                            <th class="table-th">Ca đề xuất đổi</th>
                            <th class="table-th">Đổi với</th>
                            <th class="table-th">Ca muốn nhận</th>
                            <th class="table-th text-center">Trạng thái</th>
                            <th class="table-th text-center">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($swapRequests as $swap)
                        <tr class="table-tr-hover">
                            <td class="table-td font-mono text-xs">{{ $swap->code }}</td>
                            <td class="table-td font-medium">{{ $swap->requesterEmployee?->name ?? '—' }}</td>
                            <td class="table-td text-sm">
                                {{ $swap->requesterSchedule?->work_date?->format('d/m/Y') ?? '—' }}
                                <span class="text-slate-400">({{ $swap->requesterSchedule?->shift?->name ?? '—' }})</span>
                            </td>
                            <td class="table-td font-medium">{{ $swap->targetEmployee?->name ?? '—' }}</td>
                            <td class="table-td text-sm">
                                {{ $swap->targetSchedule?->work_date?->format('d/m/Y') ?? '—' }}
                                <span class="text-slate-400">({{ $swap->targetSchedule?->shift?->name ?? '—' }})</span>
                            </td>
                            <td class="table-td text-center">
                                <span class="badge {{ $swap->statusBadgeClass() }}">{{ $swap->statusLabel() }}</span>
                                @if($swap->status === 'rejected' && $swap->rejection_reason)
                                    <p class="text-xs text-red-500 mt-1">{{ $swap->rejection_reason }}</p>
                                @endif
                            </td>
                            <td class="table-td text-center">
                                <div class="flex items-center justify-center gap-1">
                                    @if($swap->status === 'pending')
                                        @can('approve-shift-swaps')
                                        <form action="{{ route('shift-swap-requests.approve', $swap) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="btn-ghost btn-sm text-emerald-600 dark:text-emerald-400" title="Duyệt">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>
                                        <button onclick="openRejectSwapModal({{ $swap->id }}, '{{ addslashes($swap->code) }}')"
                                                class="btn-ghost btn-sm text-red-600 dark:text-red-400" title="Từ chối">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                        @endcan
                                    @else
                                        <span class="text-xs text-slate-400">
                                            {{ $swap->reviewer?->name ? 'bởi ' . $swap->reviewer->name : '—' }}
                                        </span>
                                    @endif
                                    {{-- Huỷ (chính chủ, pending) / Xoá từ chối (delete-shift-swaps) / Xoá đã duyệt kèm
                                         hoán lịch trở lại (CHỈ admin: delete-approved-requests) --}}
                                    @php($canPurgeSwap = auth()->user()->can('delete-shift-swaps'))
                                    @php($isOwnerSwap = $swap->requesterEmployee?->user_id === auth()->id())
                                    @php($canDeleteSwap = match($swap->status) {
                                        'pending'  => $isOwnerSwap || $canPurgeSwap,
                                        'approved' => auth()->user()->can('delete-approved-requests'),
                                        default    => $canPurgeSwap,
                                    })
                                    @if($canDeleteSwap)
                                    <form action="{{ route('shift-swap-requests.destroy', $swap) }}" method="POST" class="inline"
                                          onsubmit="return confirm('{{ $swap->status === 'approved' ? 'Xoá yêu cầu ĐÃ DUYỆT? Hệ thống sẽ hoán lịch làm việc trở lại cho 2 nhân viên.' : ($swap->status === 'rejected' ? 'Xoá hẳn yêu cầu này?' : 'Huỷ yêu cầu đổi ca này?') }}')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn-ghost btn-sm text-slate-500" title="{{ $swap->status === 'pending' && $isOwnerSwap && !$canPurgeSwap ? 'Huỷ yêu cầu' : 'Xoá yêu cầu' }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="table-td text-center py-8 text-slate-400">
                                <i class="bi bi-arrow-left-right text-3xl mb-2 block opacity-40"></i>
                                <p>Chưa có yêu cầu đổi ca nào</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($swapRequests->hasPages())
        <div class="card-footer">
            {{ $swapRequests->links() }}
        </div>
        @endif
    </div>
@endsection

@push('modals')
<div id="rejectSwapModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('rejectSwapModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-semibold text-slate-900 dark:text-white">Từ chối yêu cầu <span id="rejectSwapCode"></span></h3>
            <button onclick="closeModal('rejectSwapModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form id="rejectSwapForm" method="POST" class="px-4 sm:px-6 py-4 sm:py-5 space-y-4">
            @csrf
            <div>
                <label class="form-label">Lý do từ chối <span class="text-red-500">*</span></label>
                <textarea name="rejection_reason" rows="3" class="form-input" required></textarea>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('rejectSwapModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-medium">Từ chối</button>
            </div>
        </form>
    </div>
</div>

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
    <form action="{{ route('shift-swap-requests.index') }}" method="GET" class="flex-1 flex flex-col overflow-y-auto">
        <div class="p-5 space-y-4 flex-1">
            @if($isApprover)
            <div>
                <x-employee-combobox name="employee_id" :employees="$employees" :selected="request('employee_id')"
                    label="Nhân viên" placeholder="Tìm theo tên, mã NV..." :compact="true" />
            </div>
            @endif
            <div>
                <label class="block text-xs font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wider mb-2">Trạng thái</label>
                <select name="status" class="form-input text-sm w-full">
                    <option value="">Tất cả</option>
                    <option value="pending" @selected(request('status') === 'pending')>Chờ duyệt</option>
                    <option value="approved" @selected(request('status') === 'approved')>Đã duyệt</option>
                    <option value="rejected" @selected(request('status') === 'rejected')>Từ chối</option>
                </select>
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
function openRejectSwapModal(id, code) {
    document.getElementById('rejectSwapCode').textContent = code;
    document.getElementById('rejectSwapForm').action = '/shift-swap-requests/' + id + '/reject';
    openModal('rejectSwapModal');
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
    window.location.href = "{{ route('shift-swap-requests.index') }}";
}
</script>
@endpush
