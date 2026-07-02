@extends('layouts.admin')

@section('title', 'Xin nghỉ phép')
@section('page-title', 'Xin nghỉ phép')
@section('breadcrumb', 'Ca làm việc & Chấm công')

@section('content')
    <div class="page-header">
        <div>
            <p class="page-subtitle">
                @if($isApprover)
                    Danh sách đơn xin nghỉ của toàn bộ nhân viên
                @else
                    Đơn xin nghỉ phép của bạn
                @endif
            </p>
        </div>
        <button onclick="openModal('createLeaveModal')" class="btn-primary">
            <i class="bi bi-calendar-plus"></i>
            <span>Xin nghỉ phép</span>
        </button>
    </div>

    <div class="card">
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700">
            <form action="{{ route('leave-requests.index') }}" method="GET" class="flex flex-wrap items-end gap-2">
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
                <table class="table-base min-w-[800px]">
                    <thead>
                        <tr>
                            <th class="table-th">Mã đơn</th>
                            @if($isApprover)<th class="table-th">Nhân viên</th>@endif
                            <th class="table-th">Loại</th>
                            <th class="table-th">Thời gian nghỉ</th>
                            <th class="table-th">Lý do</th>
                            <th class="table-th text-center">Trạng thái</th>
                            <th class="table-th text-center">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($leaveRequests as $lr)
                        <tr class="table-tr-hover">
                            <td class="table-td font-mono text-xs">{{ $lr->code }}</td>
                            @if($isApprover)
                            <td class="table-td">
                                <p class="font-medium">{{ $lr->employee?->name ?? '—' }}</p>
                                <p class="text-xs text-slate-400">{{ $lr->employee?->branch?->name ?? '—' }}</p>
                            </td>
                            @endif
                            <td class="table-td text-sm">{{ $lr->typeLabel() }}</td>
                            <td class="table-td text-sm">
                                {{ $lr->date_from->format('d/m/Y') }} – {{ $lr->date_to->format('d/m/Y') }}
                                <span class="text-slate-400">({{ $lr->daysCount() }} ngày)</span>
                            </td>
                            <td class="table-td text-sm max-w-xs truncate" title="{{ $lr->reason }}">{{ $lr->reason }}</td>
                            <td class="table-td text-center">
                                <span class="badge {{ $lr->statusBadgeClass() }}">{{ $lr->statusLabel() }}</span>
                                @if($lr->status === 'rejected' && $lr->rejection_reason)
                                    <p class="text-xs text-red-500 mt-1">{{ $lr->rejection_reason }}</p>
                                @endif
                            </td>
                            <td class="table-td text-center">
                                <div class="flex items-center justify-center gap-1">
                                    @if($lr->status === 'pending')
                                        @can('approve-leave-requests')
                                        <form action="{{ route('leave-requests.approve', $lr) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="btn-ghost btn-sm text-emerald-600 dark:text-emerald-400" title="Duyệt">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>
                                        <button onclick="openRejectLeaveModal({{ $lr->id }}, '{{ addslashes($lr->code) }}')"
                                                class="btn-ghost btn-sm text-red-600 dark:text-red-400" title="Từ chối">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                        @endcan
                                        @if($lr->employee?->user_id === auth()->id())
                                        <form action="{{ route('leave-requests.destroy', $lr) }}" method="POST" class="inline"
                                              onsubmit="return confirm('Huỷ đơn xin nghỉ này?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn-ghost btn-sm text-slate-500" title="Huỷ đơn">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                        @endif
                                    @else
                                        <span class="text-xs text-slate-400">
                                            {{ $lr->reviewer?->name ? 'bởi ' . $lr->reviewer->name : '—' }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ $isApprover ? 7 : 6 }}" class="table-td text-center py-8 text-slate-400">
                                <i class="bi bi-calendar-x text-3xl mb-2 block opacity-40"></i>
                                <p>Chưa có đơn xin nghỉ nào</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($leaveRequests->hasPages())
        <div class="card-footer">
            {{ $leaveRequests->links() }}
        </div>
        @endif
    </div>
@endsection

@push('modals')
<div id="createLeaveModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('createLeaveModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-lg">
        <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-calendar-plus text-pcrm-600"></i> Xin nghỉ phép
            </h3>
            <button onclick="closeModal('createLeaveModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form id="createLeaveForm" action="{{ route('leave-requests.store') }}" method="POST" class="px-4 sm:px-6 py-4 sm:py-5 space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Ngày bắt đầu <span class="text-red-500">*</span></label>
                    <input type="date" name="date_from" class="form-input" required>
                    @error('date_from') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Đến ngày <span class="text-red-500">*</span></label>
                    <input type="date" name="date_to" class="form-input" required>
                    @error('date_to') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="form-label">Loại nghỉ phép <span class="text-red-500">*</span></label>
                <select name="type" class="form-input" id="createLeaveType" required>
                    <option value="annual">Nghỉ phép năm</option>
                    <option value="sick">Nghỉ ốm</option>
                    <option value="unpaid">Nghỉ không lương</option>
                    <option value="other">Khác</option>
                </select>
                <p id="createLeaveBalanceNote" class="text-xs text-slate-400 mt-1 hidden"></p>
                @error('type') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">Ca làm</label>
                <select name="shift_schedule_id" class="form-input" id="createLeaveShiftSelect">
                    <option value="">— Không áp dụng —</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <x-employee-combobox name="handover_employee_id" :employees="$allEmployees"
                        label="Người nhận bàn giao" placeholder="Tìm theo tên, mã NV..." />
                </div>
                <div>
                    <label class="form-label">Số điện thoại</label>
                    <input type="text" name="handover_phone" class="form-input" placeholder="SĐT người nhận bàn giao...">
                </div>
            </div>
            <div>
                <label class="form-label">Nội dung trao đổi</label>
                <textarea name="handover_note" rows="2" class="form-input" placeholder="Nội dung bàn giao, trao đổi công việc..."></textarea>
            </div>
            <div>
                <label class="form-label">Lý do <span class="text-red-500">*</span></label>
                <textarea name="reason" rows="3" class="form-input" placeholder="Lý do xin nghỉ..." required></textarea>
                @error('reason') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('createLeaveModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-primary"><i class="bi bi-send"></i> Gửi đơn</button>
            </div>
            <script type="application/json" id="createLeaveShiftData">@json($ownShiftSchedules)</script>
            <script type="application/json" id="createLeaveBalanceData">@json($annualLeaveBalances)</script>
        </form>
    </div>
</div>

<div id="rejectLeaveModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('rejectLeaveModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-semibold text-slate-900 dark:text-white">Từ chối đơn <span id="rejectLeaveCode"></span></h3>
            <button onclick="closeModal('rejectLeaveModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form id="rejectLeaveForm" method="POST" class="px-4 sm:px-6 py-4 sm:py-5 space-y-4">
            @csrf
            <div>
                <label class="form-label">Lý do từ chối <span class="text-red-500">*</span></label>
                <textarea name="rejection_reason" rows="3" class="form-input" required></textarea>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('rejectLeaveModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-medium">Từ chối</button>
            </div>
        </form>
    </div>
</div>
@endpush

@push('scripts')
<script>
function openRejectLeaveModal(id, code) {
    document.getElementById('rejectLeaveCode').textContent = code;
    document.getElementById('rejectLeaveForm').action = '/leave-requests/' + id + '/reject';
    openModal('rejectLeaveModal');
}

function refreshCreateLeaveShifts() {
    const form = document.getElementById('createLeaveForm');
    if (!form) return;
    const select = document.getElementById('createLeaveShiftSelect');
    const data = JSON.parse(document.getElementById('createLeaveShiftData').textContent || '[]');
    const dateFrom = form.querySelector('[name="date_from"]').value;
    const dateTo = form.querySelector('[name="date_to"]').value || dateFrom;
    const currentValue = select.value;

    const matches = data.filter(function (s) {
        if (dateFrom && s.date < dateFrom) return false;
        if (dateTo && s.date > dateTo) return false;
        return true;
    });

    select.innerHTML = '<option value="">— Không áp dụng —</option>' + matches.map(function (s) {
        return '<option value="' + s.id + '">' + s.label + '</option>';
    }).join('');
    if (matches.some(function (s) { return String(s.id) === currentValue; })) {
        select.value = currentValue;
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('createLeaveForm');
    if (!form) return;
    ['date_from', 'date_to'].forEach(function (name) {
        const el = form.querySelector('[name="' + name + '"]');
        if (el) el.addEventListener('change', refreshCreateLeaveShifts);
    });
});

// Hiển thị số ngày phép năm còn lại của chính nhân viên đang đăng nhập khi chọn "Nghỉ phép năm".
const CREATE_LEAVE_OWN_EMPLOYEE_ID = {{ auth()->user()->employee?->id ?? 'null' }};
function refreshCreateLeaveBalanceNote() {
    const typeSelect = document.getElementById('createLeaveType');
    const note = document.getElementById('createLeaveBalanceNote');
    if (!typeSelect || !note) return;
    const balances = JSON.parse(document.getElementById('createLeaveBalanceData').textContent || '{}');
    const remaining = CREATE_LEAVE_OWN_EMPLOYEE_ID !== null ? balances[CREATE_LEAVE_OWN_EMPLOYEE_ID] : undefined;

    if (typeSelect.value === 'annual' && remaining !== undefined) {
        note.textContent = 'Phép năm còn lại: ' + remaining + ' ngày';
        note.classList.remove('hidden');
    } else if (typeSelect.value === 'annual') {
        note.textContent = 'Bạn không đủ điều kiện nghỉ phép năm (chỉ áp dụng NV chính thức, văn phòng).';
        note.classList.remove('hidden');
    } else {
        note.classList.add('hidden');
    }
}
document.addEventListener('DOMContentLoaded', function () {
    const typeSelect = document.getElementById('createLeaveType');
    if (typeSelect) {
        typeSelect.addEventListener('change', refreshCreateLeaveBalanceNote);
        refreshCreateLeaveBalanceNote();
    }
});
</script>
@endpush
