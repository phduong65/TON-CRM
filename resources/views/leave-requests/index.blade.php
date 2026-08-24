@extends('layouts.admin')

@section('title', 'Xin nghỉ phép')
@section('page-title', 'Xin nghỉ phép')
@section('breadcrumb', 'Ca làm việc & Chấm công')

@section('content')
    <div class="page-header flex items-center justify-between gap-2 mb-4">
        <div>
            <p class="page-subtitle">
                @if($isApprover)
                    Danh sách đơn xin nghỉ của toàn bộ nhân viên
                @else
                    Đơn xin nghỉ phép của bạn
                @endif
            </p>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="toggleFilterDrawer(true)" class="btn-secondary h-9 px-3 gap-1.5 text-xs font-black relative">
                <i class="bi bi-funnel"></i>
                <span>Bộ lọc</span>
                @if(request()->anyFilled(['employee_id', 'status']))
                    <span class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-pcrm-600 rounded-full animate-pulse"></span>
                @endif
            </button>
            <button onclick="openModal('createLeaveModal')" class="btn-primary h-9 text-xs font-black gap-1.5">
                <i class="bi bi-calendar-plus"></i>
                <span>Xin nghỉ phép</span>
            </button>
        </div>
    </div>

    <div class="card">

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
                                @if($lr->is_partial_day)
                                    <span class="badge badge-neutral ml-1">
                                        <i class="bi bi-clock-history"></i> {{ $lr->partialDayLabel() }}
                                    </span>
                                @endif
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
                                    @else
                                        <span class="text-xs text-slate-400">
                                            {{ $lr->reviewer?->name ? 'bởi ' . $lr->reviewer->name : '—' }}
                                        </span>
                                    @endif
                                    {{-- Huỷ (chính chủ, pending) hoặc Xoá (quản lý có quyền delete-leave-requests, mọi trạng thái) --}}
                                    @php($canPurgeLeave = auth()->user()->can('delete-leave-requests'))
                                    @if(($lr->status === 'pending' && $lr->employee?->user_id === auth()->id()) || $canPurgeLeave)
                                    <form action="{{ route('leave-requests.destroy', $lr) }}" method="POST" class="inline"
                                          onsubmit="return confirm('{{ $canPurgeLeave && $lr->status !== 'pending' ? 'Xoá hẳn đơn này? Hành động không thể hoàn tác.' : 'Huỷ đơn xin nghỉ này?' }}')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn-ghost btn-sm text-slate-500" title="{{ $canPurgeLeave && $lr->status !== 'pending' ? 'Xoá đơn' : 'Huỷ đơn' }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
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
        <form id="createLeaveForm" action="{{ route('leave-requests.store') }}" method="POST" class="px-4 sm:px-6 py-4 sm:py-5 space-y-4" data-own-employee-id="{{ auth()->user()->employee?->id }}" data-shifts-url="{{ route('leave-requests.shifts-for-range') }}">
            @csrf
            <input type="hidden" name="_modal" value="createLeaveModal">
            @if($isApprover)
            <div id="createLeaveEmployeeField">
                <x-employee-combobox name="employee_id" :employees="$employees" :selected="old('employee_id')"
                    label="Nhân viên" placeholder="Chọn nhân viên cần tạo đơn..." />
                @error('employee_id') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            @endif
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Ngày bắt đầu <span class="text-red-500">*</span></label>
                    <input type="date" name="date_from" id="createLeaveDateFrom" class="form-input" value="{{ old('date_from') }}" required>
                    @error('date_from') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Đến ngày <span class="text-red-500">*</span></label>
                    <input type="date" name="date_to" id="createLeaveDateTo" class="form-input" value="{{ old('date_to') }}" required>
                    @error('date_to') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="form-label">Loại nghỉ phép <span class="text-red-500">*</span></label>
                <select name="type" class="form-input" id="createLeaveType" required>
                    <option value="annual" @selected(old('type', 'annual') === 'annual')>Nghỉ phép năm</option>
                    <option value="unpaid" @selected(old('type') === 'unpaid')>Nghỉ không lương</option>
                </select>
                <div id="createLeaveBalanceNote" class="mt-1.5 hidden">
                    <span id="createLeaveBalanceBadge" class="badge"></span>
                </div>
                @error('type') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" id="createLeavePartialToggle" name="is_partial_day" value="1"
                       class="rounded border-slate-300 text-pcrm-600 focus:ring-pcrm-500"
                       @checked(old('is_partial_day'))>
                <span class="text-sm text-slate-600 dark:text-slate-300">Chỉ nghỉ một số ca cụ thể (không nghỉ trọn khoảng ngày đã chọn)</span>
            </label>
            @error('is_partial_day') <p class="form-error">{{ $message }}</p> @enderror

            <div id="createLeaveShiftPickerWrap" class="hidden">
                <label class="form-label">Chọn ca cần nghỉ <span class="text-red-500">*</span></label>
                <div id="createLeaveShiftChips" class="hidden flex flex-wrap gap-1.5 mb-1.5"></div>
                <div class="relative">
                    <input type="text" id="createLeaveShiftSearch" class="form-input" autocomplete="off"
                           placeholder="Gõ để tìm ca theo ngày/tên ca, hoặc bấm để xem danh sách...">
                    <div id="createLeaveShiftDropdown" class="hidden absolute z-10 mt-1 w-full max-h-56 overflow-y-auto bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-lg divide-y divide-slate-100 dark:divide-slate-700"></div>
                </div>
                <div id="createLeaveShiftHiddenInputs"></div>
                <p id="createLeaveNoShiftHint" class="hidden mt-1.5 text-xs text-amber-600 dark:text-amber-400">
                    <i class="bi bi-exclamation-triangle-fill mr-1"></i>Không tìm thấy ca đã xếp cho nhân viên này trong khoảng ngày đã chọn — vui lòng liên hệ quản lý xếp ca trước, hoặc chọn "Nghỉ cả ngày" thay vì "Chỉ nghỉ một số ca cụ thể".
                </p>
                @error('shift_schedule_ids') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <x-employee-combobox name="handover_employee_id" :employees="$allEmployees" :selected="old('handover_employee_id')"
                        label="Người nhận bàn giao" placeholder="Tìm theo tên, mã NV..." />
                </div>
                <div>
                    <label class="form-label">Số điện thoại</label>
                    <input type="text" name="handover_phone" class="form-input" value="{{ old('handover_phone') }}" placeholder="SĐT người nhận bàn giao...">
                </div>
            </div>
            <div>
                <label class="form-label">Nội dung trao đổi</label>
                <textarea name="handover_note" rows="2" class="form-input" placeholder="Nội dung bàn giao, trao đổi công việc...">{{ old('handover_note') }}</textarea>
            </div>
            <div>
                <label class="form-label">Lý do <span class="text-red-500">*</span></label>
                <textarea name="reason" rows="3" class="form-input" placeholder="Lý do xin nghỉ..." required>{{ old('reason') }}</textarea>
                @error('reason') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('createLeaveModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-primary"><i class="bi bi-send"></i> Gửi đơn</button>
            </div>
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
    <form action="{{ route('leave-requests.index') }}" method="GET" class="flex-1 flex flex-col overflow-y-auto">
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
function openRejectLeaveModal(id, code) {
    document.getElementById('rejectLeaveCode').textContent = code;
    document.getElementById('rejectLeaveForm').action = '/leave-requests/' + id + '/reject';
    openModal('rejectLeaveModal');
}

// Chọn nhiều ca cụ thể (có thể thuộc nhiều ngày khác nhau trong khoảng date_from-date_to) —
// gọi AJAX lấy toàn bộ ca đã xếp trong khoảng ngày mỗi khi đổi ngày/nhân viên, người dùng gõ tìm
// + bấm chọn từng ca vào danh sách "chip" bên dưới. Không giới hạn theo cửa sổ ngày cố định.
let createLeaveShiftOptions = [];   // [{id, label, shift_type, date}] — toàn bộ ca trong khoảng ngày hiện tại
let createLeaveSelectedIds  = [];   // id các ca đã chọn, theo thứ tự chọn
let createLeaveShiftsRequestId = 0;
const createLeaveOldSelectedIds = @json(old('shift_schedule_ids', []));

function createLeaveSelectedSet() {
    return new Set(createLeaveSelectedIds.map(String));
}

function refreshCreateLeaveShifts() {
    const form = document.getElementById('createLeaveForm');
    if (!form) return;
    const hint = document.getElementById('createLeaveNoShiftHint');
    const empHidden = form.querySelector('#createLeaveEmployeeField .emp-combobox-value');
    const employeeId = empHidden ? empHidden.value : form.dataset.ownEmployeeId;
    const dateFrom = form.querySelector('[name="date_from"]').value;
    const dateTo = form.querySelector('[name="date_to"]').value || dateFrom;
    const isPartial = document.getElementById('createLeavePartialToggle').checked;

    createLeaveShiftOptions = [];
    if (!createLeaveOldSelectedIds.length) createLeaveSelectedIds = [];
    renderCreateLeaveShiftChips();
    renderCreateLeaveShiftDropdown('');

    if (!isPartial || !employeeId || !dateFrom || !dateTo) {
        if (hint) hint.classList.add('hidden');
        return;
    }

    const requestId = ++createLeaveShiftsRequestId;
    const url = form.dataset.shiftsUrl + '?employee_id=' + encodeURIComponent(employeeId)
        + '&date_from=' + encodeURIComponent(dateFrom) + '&date_to=' + encodeURIComponent(dateTo);

    fetch(url, { headers: { 'Accept': 'application/json' } })
        .then(function (res) { return res.ok ? res.json() : { options: [] }; })
        .then(function (data) {
            if (requestId !== createLeaveShiftsRequestId) return; // trả lời trễ, đã có yêu cầu mới hơn
            createLeaveShiftOptions = data.options || [];
            // Khôi phục lựa chọn cũ khi form mở lại do lỗi validate (chỉ 1 lần, ngay sau khi có data).
            if (createLeaveOldSelectedIds.length && createLeaveSelectedIds.length === 0) {
                const availableIds = createLeaveShiftOptions.map(function (o) { return o.id; });
                createLeaveSelectedIds = createLeaveOldSelectedIds.map(Number).filter(function (id) {
                    return availableIds.includes(id);
                });
                createLeaveOldSelectedIds.length = 0;
            }
            if (hint) hint.classList.toggle('hidden', createLeaveShiftOptions.length > 0);
            renderCreateLeaveShiftChips();
            renderCreateLeaveShiftDropdown(document.getElementById('createLeaveShiftSearch').value);
        })
        .catch(function () {
            if (requestId !== createLeaveShiftsRequestId) return;
        });
}

function renderCreateLeaveShiftChips() {
    const chipsWrap = document.getElementById('createLeaveShiftChips');
    const hiddenWrap = document.getElementById('createLeaveShiftHiddenInputs');
    const selected = createLeaveSelectedSet();
    const chosen = createLeaveShiftOptions.filter(function (o) { return selected.has(String(o.id)); });

    chipsWrap.innerHTML = chosen.map(function (o) {
        return '<span class="inline-flex items-center gap-1 bg-pcrm-50 dark:bg-pcrm-900/30 text-pcrm-700 dark:text-pcrm-300 text-xs font-medium pl-2 pr-1 py-1 rounded-full border border-pcrm-200 dark:border-pcrm-800">'
            + o.label
            + '<button type="button" onclick="removeCreateLeaveShift(' + o.id + ')" class="w-4 h-4 flex items-center justify-center rounded-full hover:bg-pcrm-200 dark:hover:bg-pcrm-800"><i class="bi bi-x text-xs"></i></button>'
            + '</span>';
    }).join('');
    chipsWrap.classList.toggle('hidden', chosen.length === 0);

    hiddenWrap.innerHTML = chosen.map(function (o) {
        return '<input type="hidden" name="shift_schedule_ids[]" value="' + o.id + '">';
    }).join('');
}

function renderCreateLeaveShiftDropdown(query) {
    const dropdown = document.getElementById('createLeaveShiftDropdown');
    const selected = createLeaveSelectedSet();
    const q = (query || '').trim().toLowerCase();
    const matches = createLeaveShiftOptions.filter(function (o) {
        return !selected.has(String(o.id)) && (!q || o.label.toLowerCase().includes(q));
    });

    dropdown.innerHTML = matches.length === 0
        ? '<div class="px-3 py-2 text-xs text-slate-400">Không có ca nào phù hợp</div>'
        : matches.map(function (o) {
            return '<button type="button" onclick="addCreateLeaveShift(' + o.id + ')" class="w-full text-left px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-700 flex items-center justify-between gap-2">'
                + '<span>' + o.label + '</span>'
                + (o.shift_type === 'parttime' ? '<span class="badge badge-neutral text-[10px] shrink-0">part-time</span>' : '')
                + '</button>';
        }).join('');
}

function addCreateLeaveShift(id) {
    if (!createLeaveSelectedIds.includes(id)) createLeaveSelectedIds.push(id);
    document.getElementById('createLeaveShiftSearch').value = '';
    renderCreateLeaveShiftChips();
    renderCreateLeaveShiftDropdown('');
    refreshCreateLeaveBalanceNote();
}

function removeCreateLeaveShift(id) {
    createLeaveSelectedIds = createLeaveSelectedIds.filter(function (x) { return x !== id; });
    renderCreateLeaveShiftChips();
    renderCreateLeaveShiftDropdown(document.getElementById('createLeaveShiftSearch').value);
    refreshCreateLeaveBalanceNote();
}

function toggleCreateLeavePartialMode() {
    const toggle = document.getElementById('createLeavePartialToggle');
    const wrap   = document.getElementById('createLeaveShiftPickerWrap');
    wrap.classList.toggle('hidden', !toggle.checked);
    refreshCreateLeaveShifts();
}

document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('createLeaveForm');
    if (!form) return;
    ['date_from', 'date_to'].forEach(function (name) {
        const el = form.querySelector('[name="' + name + '"]');
        if (el) el.addEventListener('change', function () {
            refreshCreateLeaveShifts();
            refreshCreateLeaveBalanceNote();
        });
    });
    const empHidden = form.querySelector('#createLeaveEmployeeField .emp-combobox-value');
    if (empHidden) empHidden.addEventListener('change', function () {
        refreshCreateLeaveShifts();
        refreshCreateLeaveBalanceNote();
    });

    document.getElementById('createLeavePartialToggle').addEventListener('change', function () {
        toggleCreateLeavePartialMode();
        refreshCreateLeaveBalanceNote();
    });

    const searchInput = document.getElementById('createLeaveShiftSearch');
    const dropdown = document.getElementById('createLeaveShiftDropdown');
    searchInput.addEventListener('input', function () {
        dropdown.classList.remove('hidden');
        renderCreateLeaveShiftDropdown(searchInput.value);
    });
    searchInput.addEventListener('focus', function () {
        dropdown.classList.remove('hidden');
        renderCreateLeaveShiftDropdown(searchInput.value);
    });
    document.addEventListener('click', function (e) {
        if (!document.getElementById('createLeaveShiftPickerWrap').contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });

    @if(old('is_partial_day'))
        toggleCreateLeavePartialMode();
    @endif
});

// Hiển thị số ngày phép năm còn lại của nhân viên đang chọn (hoặc chính mình nếu không phải
// approver) khi chọn "Nghỉ phép năm", cảnh báo trực tiếp nếu số ngày xin nghỉ vượt quá số còn lại.
function refreshCreateLeaveBalanceNote() {
    const form = document.getElementById('createLeaveForm');
    const typeSelect = document.getElementById('createLeaveType');
    const note = document.getElementById('createLeaveBalanceNote');
    const badge = document.getElementById('createLeaveBalanceBadge');
    if (!form || !typeSelect || !note || !badge) return;

    if (typeSelect.value !== 'annual') {
        note.classList.add('hidden');
        return;
    }

    const empHidden = form.querySelector('#createLeaveEmployeeField .emp-combobox-value');
    const employeeId = (empHidden && empHidden.value) ? empHidden.value : form.dataset.ownEmployeeId;
    const balances = JSON.parse(document.getElementById('createLeaveBalanceData').textContent || '{}');
    const remaining = employeeId ? balances[employeeId] : undefined;

    badge.classList.remove('badge-success', 'badge-warning', 'badge-danger');
    note.classList.remove('hidden');

    if (!employeeId) {
        badge.classList.add('badge-warning');
        badge.innerHTML = '<i class="bi bi-info-circle-fill mr-1"></i>Chọn nhân viên để xem số ngày phép năm còn lại';
        return;
    }

    if (remaining === undefined) {
        badge.classList.add('badge-danger');
        badge.innerHTML = '<i class="bi bi-exclamation-triangle-fill mr-1"></i>Không đủ điều kiện nghỉ phép năm — hãy chọn Nghỉ không lương';
        return;
    }

    const isPartial = document.getElementById('createLeavePartialToggle').checked;

    if (isPartial) {
        // Nghỉ theo ca cụ thể chỉ trừ đúng tỉ lệ các ca đã chọn (tính chính xác ở server khi gửi
        // đơn) — chỉ hiện số ngày phép còn lại, không ước tính số ngày xin ở đây để tránh sai lệch.
        badge.classList.add('badge-success');
        badge.innerHTML = '<i class="bi bi-calendar-check-fill mr-1"></i>Phép năm còn lại: ' + remaining + ' ngày (nghỉ theo ca cụ thể sẽ trừ theo tổng tỉ lệ giờ của các ca đã chọn)';
        return;
    }

    const dateFrom = form.querySelector('[name="date_from"]').value;
    const dateTo = form.querySelector('[name="date_to"]').value;
    let requestedDays = null;
    if (dateFrom && dateTo) {
        const diffMs = new Date(dateTo) - new Date(dateFrom);
        if (diffMs >= 0) requestedDays = Math.round(diffMs / 86400000) + 1;
    }

    if (requestedDays !== null && requestedDays > remaining) {
        badge.classList.add('badge-danger');
        badge.innerHTML = '<i class="bi bi-exclamation-triangle-fill mr-1"></i>Còn ' + remaining + ' ngày phép năm — không đủ cho ' + requestedDays + ' ngày đang xin. Hãy chọn loại nghỉ khác hoặc giảm số ngày.';
    } else if (remaining <= 2) {
        badge.classList.add('badge-warning');
        badge.innerHTML = '<i class="bi bi-info-circle-fill mr-1"></i>Phép năm còn lại: ' + remaining + ' ngày';
    } else {
        badge.classList.add('badge-success');
        badge.innerHTML = '<i class="bi bi-calendar-check-fill mr-1"></i>Phép năm còn lại: ' + remaining + ' ngày';
    }
}
document.addEventListener('DOMContentLoaded', function () {
    const typeSelect = document.getElementById('createLeaveType');
    if (typeSelect) {
        typeSelect.addEventListener('change', refreshCreateLeaveBalanceNote);
        refreshCreateLeaveBalanceNote();
    }
});

@if($errors->any() && old('_modal') === 'createLeaveModal')
document.addEventListener('DOMContentLoaded', function() {
    openModal('createLeaveModal');
});
@endif

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
    window.location.href = "{{ route('leave-requests.index') }}";
}
</script>
@endpush
