@extends('layouts.admin')

@section('title', 'Đổi ca')
@section('page-title', 'Đổi ca làm việc')
@section('breadcrumb', 'Ca làm việc & Chấm công')

@section('content')
    <div class="page-header">
        <div>
            <p class="page-subtitle">
                @if($isApprover)
                    Danh sách yêu cầu đổi ca giữa các nhân viên
                @else
                    Yêu cầu đổi ca bạn đã gửi hoặc được chọn để đổi
                @endif
            </p>
        </div>
    </div>

    <div class="rounded-xl bg-sky-50 dark:bg-sky-900/20 border border-sky-100 dark:border-sky-900/40 px-4 py-3 mb-4 flex items-start gap-3">
        <i class="bi bi-info-circle text-sky-500 mt-0.5"></i>
        <p class="text-sm text-sky-700 dark:text-sky-400">
            Để tạo yêu cầu đổi ca, vào trang
            <a href="{{ route('shift-schedules.index') }}" class="font-semibold underline">Xếp ca</a>,
            tìm ca của đồng nghiệp muốn đổi và bấm nút <strong>Đổi ca</strong> ngay trên ô lịch đó.
        </p>
    </div>

    <div class="card">
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700">
            <form action="{{ route('shift-swap-requests.index') }}" method="GET" class="flex flex-wrap items-end gap-2">
                @if($isApprover)
                <div class="min-w-[220px]">
                    <x-employee-combobox name="employee_id" :employees="$employees" :selected="request('employee_id')"
                        label="Nhân viên" placeholder="Tìm theo tên, mã NV..." />
                </div>
                @endif
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Trạng thái</label>
                    <select name="status" class="form-input h-9 text-sm min-w-[150px]">
                        <option value="">Tất cả</option>
                        <option value="pending" @selected(request('status') === 'pending')>Chờ duyệt</option>
                        <option value="approved" @selected(request('status') === 'approved')>Đã duyệt</option>
                        <option value="rejected" @selected(request('status') === 'rejected')>Từ chối</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary h-9 px-4 text-sm gap-1.5">
                    <i class="bi bi-funnel text-xs"></i> Lọc
                </button>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-container border-0 rounded-none">
                <table class="table-base min-w-[900px]">
                    <thead>
                        <tr>
                            <th class="table-th">Mã yêu cầu</th>
                            <th class="table-th">Người yêu cầu</th>
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
                                        @if($swap->requesterEmployee?->user_id === auth()->id())
                                        <form action="{{ route('shift-swap-requests.destroy', $swap) }}" method="POST" class="inline"
                                              onsubmit="return confirm('Huỷ yêu cầu đổi ca này?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn-ghost btn-sm text-slate-500" title="Huỷ yêu cầu">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                        @endif
                                    @else
                                        <span class="text-xs text-slate-400">
                                            {{ $swap->reviewer?->name ? 'bởi ' . $swap->reviewer->name : '—' }}
                                        </span>
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
@endpush

@push('scripts')
<script>
function openRejectSwapModal(id, code) {
    document.getElementById('rejectSwapCode').textContent = code;
    document.getElementById('rejectSwapForm').action = '/shift-swap-requests/' + id + '/reject';
    openModal('rejectSwapModal');
}
</script>
@endpush
