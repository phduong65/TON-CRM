@extends('layouts.admin')

@section('title', 'Xin nghỉ phép')
@section('page-title', 'Xin nghỉ phép')
@section('breadcrumb', 'Ca làm việc & Chấm công')

@section('page-subtitle')
    @if($isApprover)
    Danh sách đơn xin nghỉ của toàn bộ nhân viên
    @else
    Đơn xin nghỉ phép của bạn
    @endif
@endsection

@section('page-actions')
    <button onclick="openModal('createLeaveModal')" class="btn-primary h-9 text-xs font-black gap-1.5">
        <i class="bi bi-calendar-plus"></i>
        <span>Xin nghỉ phép</span>
    </button>
@endsection

@section('content')
    <div class="card">
        <x-table-toolbar :paginator="$leaveRequests">
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
                <table class="table-base min-w-[800px]">
                    <thead>
                        <tr>
                            <th class="table-th">Mã đơn</th>
                            @if($isApprover)<th class="table-th" data-mcard-title>Nhân viên</th>@endif
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
                                <div class="flex items-center gap-2.5">
                                    <x-employee-avatar :employee="$lr->employee" size="w-8 h-8" />
                                    <div class="min-w-0">
                                        <p class="font-medium">{{ $lr->employee?->name ?? '—' }}</p>
                                        <p class="text-xs text-slate-400">{{ $lr->employee?->branch?->name ?? '—' }}</p>
                                    </div>
                                </div>
                            </td>
                            @endif
                            <td class="table-td text-sm">{{ $lr->typeLabel() }}</td>
                            <td class="table-td text-sm">
                                {{ $lr->date_from->format('d/m/Y') }} – {{ $lr->date_to->format('d/m/Y') }}
                                <span class="text-slate-400 whitespace-nowrap">({{ $lr->daysCount() }} ngày)</span>
                                @if($lr->is_partial_day)
                                    <span class="badge badge-neutral ml-1">
                                        <i class="bi bi-clock-history"></i> {{ $lr->partialDayLabel() }}
                                    </span>
                                    <ul class="mt-1.5 space-y-0.5">
                                        @foreach($lr->selectedShifts() as $shiftItem)
                                            <li class="text-xs text-slate-600 dark:text-slate-300">
                                                <span class="font-semibold">{{ $shiftItem['name'] }}</span>
                                                @if($shiftItem['time'])<span class="tabular-nums"> · {{ $shiftItem['time'] }}</span>@endif
                                                <span class="text-slate-400 tabular-nums"> · {{ $shiftItem['date'] }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
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
                                    {{-- Huỷ (chính chủ, pending) / Xoá từ chối (delete-leave-requests) / Xoá đã duyệt kèm
                                         hoàn phép+khôi phục lịch (CHỈ admin: delete-approved-requests) --}}
                                    @php($canPurgeLeave = auth()->user()->can('delete-leave-requests'))
                                    @php($isOwnerLeave = $lr->employee?->user_id === auth()->id())
                                    @php($canDeleteLeave = match($lr->status) {
                                        'pending'  => $isOwnerLeave || $canPurgeLeave,
                                        'approved' => auth()->user()->can('delete-approved-requests'),
                                        default    => $canPurgeLeave,
                                    })
                                    @if($canDeleteLeave)
                                    <form action="{{ route('leave-requests.destroy', $lr) }}" method="POST" class="inline"
                                          onsubmit="return confirm('{{ $lr->status === 'approved' ? 'Xoá đơn ĐÃ DUYỆT? Hệ thống sẽ hoàn phép năm (nếu có) và khôi phục lịch đã huỷ.' : ($lr->status === 'rejected' ? 'Xoá hẳn đơn này?' : 'Huỷ đơn xin nghỉ này?') }}')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn-ghost btn-sm text-slate-500" title="{{ $lr->status === 'pending' && $isOwnerLeave && !$canPurgeLeave ? 'Huỷ đơn' : 'Xoá đơn' }}">
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

            <input type="hidden" id="createLeavePartialToggle" name="is_partial_day" value="{{ old('is_partial_day', 0) }}">

            <div id="createLeavePartialWrap" class="hidden space-y-3">
                <div id="createLeavePartialModeGroup" class="flex items-center gap-4 text-sm">
                    <label class="flex items-center gap-1.5 cursor-pointer">
                        <input type="radio" name="partial_mode" value="shifts" id="createLeaveModeShifts"
                               class="border-slate-300 text-pcrm-600 focus:ring-pcrm-500"
                               @checked(old('partial_mode', 'shifts') === 'shifts')>
                        <span id="createLeaveModeShiftsLabel">Nghỉ theo ca cụ thể</span>
                    </label>
                    <label id="createLeaveModeCustomLabel" class="hidden flex items-center gap-1.5 cursor-pointer">
                        <input type="radio" name="partial_mode" value="custom_time" id="createLeaveModeCustom"
                               class="border-slate-300 text-pcrm-600 focus:ring-pcrm-500"
                               @checked(old('partial_mode') === 'custom_time')>
                        <span>Nghỉ nửa ngày (theo giờ)</span>
                    </label>
                </div>

                <div id="createLeaveShiftPickerWrap">
                    <label class="form-label">Chọn ca cần nghỉ <span class="text-red-500">*</span></label>
                    <select id="createLeaveShiftSelect" name="shift_schedule_ids[]" multiple></select>
                    <p id="createLeaveNoShiftHint" class="hidden mt-1.5 text-xs text-amber-600 dark:text-amber-400">
                        <i class="bi bi-exclamation-triangle-fill mr-1"></i>Không tìm thấy ca đã xếp cho nhân viên này trong khoảng ngày đã chọn — vui lòng liên hệ quản lý xếp ca trước, hoặc chọn "Nghỉ cả ngày" thay vì "Chỉ nghỉ một phần".
                    </p>
                    @error('shift_schedule_ids') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div id="createLeaveCustomTimeWrap" class="hidden space-y-2">
                    <div>
                        <label class="form-label">Ca cần nghỉ nửa ngày <span class="text-red-500">*</span></label>
                        <select id="createLeaveCustomShiftSelect" name="shift_schedule_id"></select>
                        @error('shift_schedule_id') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" id="createLeaveCustomMorningBtn" class="btn-secondary text-xs px-2.5 py-1.5">Nghỉ buổi sáng</button>
                        <button type="button" id="createLeaveCustomAfternoonBtn" class="btn-secondary text-xs px-2.5 py-1.5">Nghỉ buổi chiều</button>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Từ giờ <span class="text-red-500">*</span></label>
                            <input type="time" id="createLeaveFromTime" name="from_time" class="form-input" value="{{ old('from_time') }}">
                            @error('from_time') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="form-label">Đến giờ <span class="text-red-500">*</span></label>
                            <input type="time" id="createLeaveToTime" name="to_time" class="form-input" value="{{ old('to_time') }}">
                            @error('to_time') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div id="createLeaveDurationInfo" class="hidden mt-1 rounded-lg bg-slate-50 dark:bg-slate-800/60 px-3 py-2 text-xs text-slate-600 dark:text-slate-300"></div>
                    <p id="createLeaveCustomShiftHint" class="hidden mt-1 text-xs text-amber-600 dark:text-amber-400">
                        <i class="bi bi-exclamation-triangle-fill mr-1"></i>Không tìm thấy ca đã xếp cho nhân viên này vào đúng ngày đã chọn.
                    </p>
                </div>
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
            <script type="application/json" id="createLeaveOfficeData">@json($officeFlags)</script>
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
// gọi AJAX lấy toàn bộ ca đã xếp trong khoảng ngày mỗi khi đổi ngày/nhân viên, đổ vào 1 Tom Select
// đa lựa chọn (search sẵn có, không cần tự viết dropdown/chip). Không giới hạn theo cửa sổ ngày cố định.
let createLeaveShiftOptions = [];   // [{id, label, shift_type, date, start_time, end_time}] — toàn bộ ca trong khoảng ngày hiện tại
let createLeaveShiftTS = null;       // Tom Select — chọn nhiều ca cụ thể (mode "shifts")
let createLeaveCustomShiftTS = null; // Tom Select — chọn đúng 1 ca (mode "custom_time")
let createLeaveShiftsRequestId = 0;
const createLeaveOldSelectedIds = @json(old('shift_schedule_ids', []));
const createLeaveOldShiftScheduleId = @json(old('shift_schedule_id'));
const createLeaveOfficeFlags = JSON.parse(document.getElementById('createLeaveOfficeData').textContent || '{}');

function initCreateLeaveTomSelects() {
    if (typeof TomSelect === 'undefined' || createLeaveShiftTS) return;

    createLeaveShiftTS = new TomSelect('#createLeaveShiftSelect', {
        plugins: ['remove_button'],
        placeholder: 'Gõ để tìm ca theo ngày/tên ca, hoặc bấm để xem danh sách...',
        maxItems: null,
        maxOptions: null,
        render: {
            option: function (data, escape) {
                return '<div>' + escape(data.text)
                    + (data.shift_type === 'parttime' ? ' <span class="badge badge-neutral text-[10px]">part-time</span>' : '')
                    + '</div>';
            },
        },
        onChange: function () { refreshCreateLeaveBalanceNote(); },
    });

    createLeaveCustomShiftTS = new TomSelect('#createLeaveCustomShiftSelect', {
        maxItems: 1,
        placeholder: '— Chọn ca —',
        onChange: applyCreateLeaveCustomShiftBounds,
    });
}

// Nhân viên đang được chọn trên form (approver chọn qua combobox, nhân viên thường luôn là
// chính mình) — dùng chung ở nhiều nơi: gọi AJAX lấy ca, xác định có phải khối văn phòng không.
function getCreateLeaveEmployeeId() {
    const form = document.getElementById('createLeaveForm');
    if (!form) return null;
    const empHidden = form.querySelector('[name="employee_id"]');
    return (empHidden ? empHidden.value : form.dataset.ownEmployeeId) || null;
}

// Nạp lại toàn bộ option cho Tom Select đa lựa chọn — giữ lại các lựa chọn cũ nếu ca đó vẫn còn
// hợp lệ trong danh sách mới (VD đổi date_to xa hơn không làm mất lựa chọn đã có ở date_from).
function populateCreateLeaveShiftTS(options) {
    if (!createLeaveShiftTS) return;
    const previousValues = createLeaveShiftTS.getValue(); // mảng string
    createLeaveShiftTS.clear(true);
    createLeaveShiftTS.clearOptions();
    options.forEach(function (o) {
        createLeaveShiftTS.addOption({ value: String(o.id), text: o.label, shift_type: o.shift_type });
    });
    createLeaveShiftTS.refreshOptions(false);

    const validIds = options.map(function (o) { return String(o.id); });
    let restore = previousValues.filter(function (v) { return validIds.includes(v); });
    // Khôi phục lựa chọn cũ khi form mở lại do lỗi validate (chỉ 1 lần, ngay sau khi có data).
    if (createLeaveOldSelectedIds.length && restore.length === 0 && previousValues.length === 0) {
        restore = createLeaveOldSelectedIds.map(String).filter(function (v) { return validIds.includes(v); });
        createLeaveOldSelectedIds.length = 0;
    }
    createLeaveShiftTS.setValue(restore, true);
}

function refreshCreateLeaveShifts() {
    const form = document.getElementById('createLeaveForm');
    if (!form) return;
    const hint = document.getElementById('createLeaveNoShiftHint');
    const employeeId = getCreateLeaveEmployeeId();
    const dateFrom = form.querySelector('[name="date_from"]').value;
    const dateTo = form.querySelector('[name="date_to"]').value || dateFrom;

    createLeaveShiftOptions = [];
    populateCreateLeaveShiftTS([]);
    renderCreateLeaveCustomShiftOptions();

    if (!employeeId || !dateFrom || !dateTo) {
        document.getElementById('createLeavePartialToggle').value = "0";
        document.getElementById('createLeavePartialWrap').classList.add('hidden');
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
            
            const hasShifts = createLeaveShiftOptions.length > 0;
            document.getElementById('createLeavePartialToggle').value = hasShifts ? "1" : "0";
            document.getElementById('createLeavePartialWrap').classList.toggle('hidden', !hasShifts);

            if (hint) hint.classList.toggle('hidden', hasShifts);

            if (hasShifts) {
                populateCreateLeaveShiftTS(createLeaveShiftOptions);
                renderCreateLeaveCustomShiftOptions();
                syncCreateLeavePartialModeVisibility();
            }
        })
        .catch(function () {
            if (requestId !== createLeaveShiftsRequestId) return;
        });
}

// Đổ danh sách ca (cùng nguồn dữ liệu AJAX với picker "theo ca cụ thể") vào Tom Select chọn 1 ca
// duy nhất cho mode "Nghỉ nửa ngày (theo giờ)" — chỉ nhận ca đúng ngày date_from (mode này luôn
// giới hạn 1 ngày, xem validate ở server).
function renderCreateLeaveCustomShiftOptions() {
    if (!createLeaveCustomShiftTS) return;
    const hint = document.getElementById('createLeaveCustomShiftHint');
    const form = document.getElementById('createLeaveForm');
    const dateFrom = form.querySelector('[name="date_from"]').value;
    const options = createLeaveShiftOptions.filter(function (o) { return o.date === dateFrom; });

    const previousValue = createLeaveCustomShiftTS.getValue() || String(createLeaveOldShiftScheduleId || '');
    createLeaveCustomShiftTS.clear(true);
    createLeaveCustomShiftTS.clearOptions();
    options.forEach(function (o) {
        createLeaveCustomShiftTS.addOption({ value: String(o.id), text: o.label, start: o.start_time || '', end: o.end_time || '', split: o.split_time || '', breakStart: o.break_start || '', breakMinutes: o.break_minutes || 0, shiftType: o.shift_type || 'fulltime', leaveAdjusted: !!o.leave_adjusted });
    });
    createLeaveCustomShiftTS.refreshOptions(false);

    const validIds = options.map(function (o) { return String(o.id); });
    if (previousValue && validIds.includes(String(previousValue))) {
        createLeaveCustomShiftTS.setValue(String(previousValue), true);
    }
    if (hint) hint.classList.toggle('hidden', options.length > 0);
    applyCreateLeaveCustomShiftBounds();
}

// Cập nhật gợi ý khung giờ ca khi chọn ca. KHÔNG dùng thuộc tính min/max native của
// <input type="time"> nữa — chúng bung popup tiếng Anh khó hiểu ("Value must be 12:00 or later")
// và chặn nhầm. Thay vào đó chỉ hiển thị khung giờ + tự tính giờ nghỉ/công (updateCreateLeaveDurationInfo),
// còn chặn giá trị ngoài ca do server validate với thông báo tiếng Việt rõ ràng.
function applyCreateLeaveCustomShiftBounds() {
    updateCreateLeaveDurationInfo();
}

// 'HH:MM' -> số phút trong ngày.
function leaveTimeToMinutes(t) {
    if (!t) return null;
    const parts = t.split(':');
    return parseInt(parts[0], 10) * 60 + parseInt(parts[1] || '0', 10);
}

// Số phút CÔNG thực trong [from, to] của ca đang chọn — trừ phần trùng giờ nghỉ giữa ca.
// Mirror của Shift::workMinutesInWindow() ở phía server.
function leaveWorkMinutesInWindow(from, to, opt) {
    const f = leaveTimeToMinutes(from), t = leaveTimeToMinutes(to);
    if (f === null || t === null || t <= f) return null;
    let minutes = t - f;
    const bs = opt.breakStart ? leaveTimeToMinutes(opt.breakStart) : null;
    const bm = parseInt(opt.breakMinutes || 0, 10);
    if (bs !== null && bm > 0) {
        const be = bs + bm;
        const os = Math.max(f, bs), oe = Math.min(t, be);
        if (oe > os) minutes -= (oe - os);
    }
    return Math.max(0, minutes);
}

// Tổng phút công của ca (span - giờ nghỉ) — mẫu số quy đổi công.
function leaveNetWorkMinutes(opt) {
    const s = leaveTimeToMinutes(opt.start), e = leaveTimeToMinutes(opt.end);
    if (s === null || e === null) return 0;
    let span = e - s;
    if (span <= 0) span += 24 * 60; // ca qua đêm
    return Math.max(0, span - parseInt(opt.breakMinutes || 0, 10));
}

// Hiển thị: khung giờ ca + giờ nghỉ thực tế (đã trừ giờ nghỉ giữa ca) + quy đổi công (ca fulltime).
function updateCreateLeaveDurationInfo() {
    const box = document.getElementById('createLeaveDurationInfo');
    const fromInput = document.getElementById('createLeaveFromTime');
    const toInput = document.getElementById('createLeaveToTime');
    if (!box || !fromInput || !toInput) return;

    const opt = getSelectedCustomShiftOption();
    if (!opt || !opt.start || !opt.end) {
        box.classList.add('hidden');
        return;
    }

    const rangeLabel = opt.leaveAdjusted ? 'Khung giờ còn lại' : 'Khung giờ ca';
    let html = '<i class="bi bi-clock-history mr-1"></i>' + rangeLabel + ': <b>' + opt.start + '–' + opt.end + '</b>';
    if (opt.leaveAdjusted) {
        html += '<br><span class="text-amber-600 dark:text-amber-400"><i class="bi bi-info-circle-fill mr-1"></i>Một phần ca này đã được duyệt nghỉ theo đơn khác — chỉ còn xin nghỉ được trong khung giờ trên. Muốn nghỉ sớm hơn, hãy xoá đơn nghỉ đã duyệt trước đó.</span>';
    }
    const from = fromInput.value, to = toInput.value;

    if (from && to) {
        const wm = leaveWorkMinutesInWindow(from, to, opt);
        if (wm === null) {
            html += ' · <span class="text-red-600 dark:text-red-400 font-semibold">Đến giờ phải sau Từ giờ</span>';
        } else {
            const h = Math.floor(wm / 60), m = wm % 60;
            const dur = h + ' giờ' + (m ? ' ' + m + ' phút' : '');
            html += ' · Nghỉ thực tế: <b class="text-pcrm-700 dark:text-pcrm-300">' + dur + '</b>';

            const net = leaveNetWorkMinutes(opt);
            if (opt.shiftType !== 'parttime' && net > 0) {
                const cong = Math.round(Math.min(1, wm / net) * 100) / 100;
                html += ' ≈ <b class="text-pcrm-700 dark:text-pcrm-300">' + cong + ' công</b>';
            }

            if (from < opt.start || to > opt.end) {
                html += '<br><span class="text-red-600 dark:text-red-400 font-semibold"><i class="bi bi-exclamation-triangle-fill mr-1"></i>Giờ nghỉ đang nằm ngoài khung giờ ca — vui lòng nhập trong ' + opt.start + '–' + opt.end + '.</span>';
            }
        }
    }

    box.innerHTML = html;
    box.classList.remove('hidden');
}

// Option (ca) đang chọn ở picker "nghỉ nửa ngày theo giờ" — chứa start/end/split để nút
// "Nghỉ buổi sáng/chiều" tính khung giờ theo đúng ca. Trả null nếu chưa chọn ca.
function getSelectedCustomShiftOption() {
    const value = createLeaveCustomShiftTS && createLeaveCustomShiftTS.getValue();
    return value ? createLeaveCustomShiftTS.options[value] : null;
}

function setCreateLeaveCustomTimeRange(fromTime, toTime) {
    const fromInput = document.getElementById('createLeaveFromTime');
    const toInput = document.getElementById('createLeaveToTime');
    const value = createLeaveCustomShiftTS && createLeaveCustomShiftTS.getValue();
    const opt = value ? createLeaveCustomShiftTS.options[value] : null;
    if (!opt) return;
    const start = opt.start || '00:00';
    const end = opt.end || '23:59';
    // Kẹp trong khoảng [start, end] của ca — VD ca 09:00–12:00 (chỉ nửa buổi sáng) thì nút
    // "Nghỉ buổi chiều" vẫn cho ra khung giờ hợp lệ thay vì 13:00 nằm ngoài ca.
    fromInput.value = fromTime < start ? start : (fromTime > end ? end : fromTime);
    toInput.value = toTime > end ? end : (toTime < start ? start : toTime);
    updateCreateLeaveDurationInfo();
}

function toggleCreateLeavePartialMode() {
    const toggle = document.getElementById('createLeavePartialToggle');
    const wrap   = document.getElementById('createLeavePartialWrap');
    wrap.classList.toggle('hidden', !toggle.checked);
    refreshCreateLeaveShifts();
}

// Chỉ nhân viên khối văn phòng (is_office) VÀ đang chọn đúng 1 ngày (date_from = date_to) mới
// được nghỉ nửa ngày theo giờ cụ thể (NV nhà hàng/bếp/bar làm trọn ca, không có khái niệm nửa
// ca) — ẩn lựa chọn và tự chuyển về mode "theo ca cụ thể" nếu không còn hợp lệ (VD vừa đổi sang
// nhân viên không thuộc văn phòng, hoặc mở rộng khoảng ngày ra nhiều hơn 1 ngày).
function syncCreateLeavePartialModeVisibility() {
    const toggle = document.getElementById('createLeavePartialToggle');
    if (!toggle || toggle.value !== '1') return;

    const form = document.getElementById('createLeaveForm');
    const dateFrom = form.querySelector('[name="date_from"]').value;
    const dateTo = form.querySelector('[name="date_to"]').value || dateFrom;
    const employeeId = getCreateLeaveEmployeeId();
    const isOffice = !!(employeeId && createLeaveOfficeFlags[employeeId]);
    const isSingleDay = !!dateFrom && dateFrom === dateTo;
    const customAllowed = isOffice && isSingleDay;

    const modeShiftsLabel = document.getElementById('createLeaveModeShiftsLabel');
    if (modeShiftsLabel) {
        modeShiftsLabel.textContent = customAllowed ? 'Nghỉ cả ngày (theo ca)' : 'Nghỉ theo ca cụ thể';
    }

    const modeGroup = document.getElementById('createLeavePartialModeGroup');
    if (modeGroup) {
        modeGroup.classList.toggle('hidden', !customAllowed);
    }

    document.getElementById('createLeaveModeCustomLabel').classList.toggle('hidden', !customAllowed);

    const modeShifts = document.getElementById('createLeaveModeShifts');
    const modeCustom = document.getElementById('createLeaveModeCustom');
    if (!customAllowed && modeCustom.checked) {
        modeShifts.checked = true;
    }

    const useCustom = customAllowed && modeCustom.checked;
    document.getElementById('createLeaveShiftPickerWrap').classList.toggle('hidden', useCustom);
    document.getElementById('createLeaveCustomTimeWrap').classList.toggle('hidden', !useCustom);
}

document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('createLeaveForm');
    if (!form) return;

    initCreateLeaveTomSelects();

    ['date_from', 'date_to'].forEach(function (name) {
        const el = form.querySelector('[name="' + name + '"]');
        if (el) el.addEventListener('change', function () {
            refreshCreateLeaveShifts();
            refreshCreateLeaveBalanceNote();
        });
    });
    const empHidden = form.querySelector('[name="employee_id"]');
    if (empHidden) empHidden.addEventListener('change', function () {
        refreshCreateLeaveShifts();
        refreshCreateLeaveBalanceNote();
    });

    ['createLeaveModeShifts', 'createLeaveModeCustom'].forEach(function (id) {
        document.getElementById(id).addEventListener('change', function () {
            // Đổi mode thì xoá lựa chọn của mode kia — tránh gửi kèm dữ liệu thừa (VD đã chọn vài
            // ca ở mode "theo ca cụ thể" rồi đổi sang "nửa ngày theo giờ").
            if (this.value === 'custom_time') {
                if (createLeaveShiftTS) createLeaveShiftTS.clear(true);
            } else {
                if (createLeaveCustomShiftTS) createLeaveCustomShiftTS.clear(true);
                document.getElementById('createLeaveFromTime').value = '';
                document.getElementById('createLeaveToTime').value = '';
            }
            syncCreateLeavePartialModeVisibility();
            refreshCreateLeaveBalanceNote();
        });
    });

    ['createLeaveFromTime', 'createLeaveToTime'].forEach(function (id) {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', updateCreateLeaveDurationInfo);
    });

    document.getElementById('createLeaveCustomMorningBtn').addEventListener('click', function () {
        // Nghỉ buổi sáng = từ đầu ca đến điểm chia nửa giờ công (split_time, đã tính giờ nghỉ giữa
        // ca). VD ca 09:00–18:00 nghỉ 12:00–13:00 → 09:00–14:00 (= 4h công = nửa ngày).
        const opt = getSelectedCustomShiftOption();
        if (!opt) return;
        setCreateLeaveCustomTimeRange(opt.start || '00:00', opt.split || opt.end || '12:00');
    });
    document.getElementById('createLeaveCustomAfternoonBtn').addEventListener('click', function () {
        // Nghỉ buổi chiều = từ điểm chia nửa giờ công đến cuối ca. VD 14:00–18:00 (= 4h công).
        const opt = getSelectedCustomShiftOption();
        if (!opt) return;
        setCreateLeaveCustomTimeRange(opt.split || opt.start || '13:00', opt.end || '23:59');
    });

    refreshCreateLeaveShifts();
    refreshCreateLeaveBalanceNote();
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

    const empHidden = form.querySelector('[name="employee_id"]');
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

    const isPartial = document.getElementById('createLeavePartialToggle').value === '1';

    if (isPartial) {
        // Nghỉ theo ca cụ thể / nghỉ nửa ngày theo giờ đều chỉ trừ đúng tỉ lệ (tính chính xác ở
        // server khi gửi đơn) — chỉ hiện số ngày phép còn lại, không ước tính số ngày xin ở đây để
        // tránh sai lệch.
        const isCustomTime = document.getElementById('createLeaveModeCustom').checked
            && !document.getElementById('createLeaveModeCustomLabel').classList.contains('hidden');
        badge.classList.add('badge-success');
        badge.innerHTML = '<i class="bi bi-calendar-check-fill mr-1"></i>Phép năm còn lại: ' + remaining + ' ngày ('
            + (isCustomTime ? 'nghỉ nửa ngày sẽ trừ theo đúng tỉ lệ giờ đã chọn' : 'nghỉ theo ca cụ thể sẽ trừ theo tổng tỉ lệ giờ của các ca đã chọn') + ')';
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
