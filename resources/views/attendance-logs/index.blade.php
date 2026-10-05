@extends('layouts.admin')

@section('title', 'Báo cáo chấm công')
@section('page-title', 'Báo cáo chấm công')
@section('breadcrumb', 'Ca làm việc & Chấm công')

@section('page-subtitle')
    Lịch sử check-in/check-out của nhân viên
    @if($isDefaultTodayView)
    <span class="badge badge-info ml-1">Hôm nay</span>
    <a href="{{ route('attendance-logs.index', ['all' => 1]) }}" class="text-xs text-pcrm-600 dark:text-pcrm-400 hover:underline ml-1">Xem tất cả</a>
    @endif
@endsection

@can('create-attendance-logs')
@section('page-actions')
    <button type="button" onclick="openModal('createAttendanceLogModal')" class="btn-primary h-9 text-xs font-black gap-1.5">
        <i class="bi bi-person-check"></i>
        <span>Chấm công hộ</span>
    </button>
@endsection
@endcan

@section('content')
    <div class="card">
        <x-table-toolbar :paginator="$logs">
            <button type="button" onclick="toggleFilterDrawer(true)" class="btn-secondary h-9 px-3 gap-1.5 text-xs font-black relative">
                <i class="bi bi-funnel"></i>
                <span>Bộ lọc</span>
                @if(request()->anyFilled(['branch_id', 'team_id', 'employee_id', 'date_from', 'date_to', 'all']))
                    <span class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-pcrm-600 rounded-full animate-pulse"></span>
                @endif
            </button>
            @can('export-attendance')
            <form action="{{ route('attendance-logs.index') }}" method="GET" id="attendanceFilterForm">
                {{-- Gửi kèm đúng bộ lọc đang xem để "Danh sách chi tiết" xuất khớp dữ liệu trên trang --}}
                @foreach (['branch_id', 'team_id', 'employee_id', 'date_from', 'date_to', 'all'] as $exportFilter)
                    @if (request()->filled($exportFilter))
                        <input type="hidden" name="{{ $exportFilter }}" value="{{ request($exportFilter) }}">
                    @endif
                @endforeach
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
            </form>
            @endcan
        </x-table-toolbar>
        <div class="card-body p-0">
            <div class="table-container border-0 rounded-none">
                <table class="table-base min-w-[900px]">
                    <thead>
                        <tr>
                            <th class="table-th">Ngày</th>
                            <th class="table-th" data-mcard-title>Nhân viên</th>
                            <th class="table-th">Ca</th>
                            <th class="table-th text-center">Check-in</th>
                            <th class="table-th text-center">Check-out</th>
                            <th class="table-th text-center">Giờ công</th>
                            <th class="table-th text-center">Công</th>
                            <th class="table-th text-center">Tăng ca</th>
                            <th class="table-th text-center">Trễ/Sớm</th>
                            <th class="table-th text-center">Phương thức</th>
                            <th class="table-th text-center">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                        @php
                            $leaveFraction = $partialLeaveIndex[$log->employee_id . '_' . $log->work_date->toDateString() . '_' . $log->shift_schedule_id] ?? null;
                            $workedHours   = $log->netWorkedHours();
                            $cong          = $log->computeCong(null, $leaveFraction);
                            $fmt           = fn($n) => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
                            // "Ghi nhận" — đã có đơn "Đi muộn về sớm"/"Thay đổi giờ vào/ra" được duyệt cho đúng
                            // lượt/ca này nhưng KHÔNG tha lỗi (full_credit=false) — trễ/sớm vẫn tính kỷ luật
                            // bình thường, badge chỉ để biết đã có đơn xin phép. "Đã tha lỗi" (full_credit=true)
                            // — không tính kỷ luật/nhắc nhở trễ-sớm (công vẫn luôn tính theo giờ thực tế, không
                            // liên quan full_credit) — đọc thẳng từ cột full_credit, không cần tra index.
                            $correctionKeys = [
                                $log->employee_id . '_' . $log->work_date->toDateString() . '_' . ($log->shift_schedule_id ?? 'any'),
                                $log->employee_id . '_' . $log->work_date->toDateString() . '_any',
                            ];
                            $hasRecordedCorrection = !$log->full_credit
                                && collect($correctionKeys)->contains(fn($k) => $approvedCorrectionIndex[$k] ?? false);
                            // Nhân viên part_time trả lương theo giờ — hiển thị "Giờ công" là chỉ số
                            // chính, ẩn "Công" (quy đổi theo giờ chuẩn ca fulltime, không áp dụng).
                            $isPartTime = $log->employee?->employment_type === 'part_time';
                        @endphp
                        <tr class="table-tr-hover">
                            <td class="table-td text-sm">{{ $log->work_date->format('d/m/Y') }}</td>
                            <td class="table-td">
                                <div class="flex items-center gap-2.5">
                                    <x-employee-avatar :employee="$log->employee" size="w-8 h-8" />
                                    <div class="min-w-0">
                                        <p class="font-medium">{{ $log->employee?->name ?? '—' }}</p>
                                        <p class="text-xs text-slate-400">{{ $log->employee?->team?->name ?? '—' }}</p>
                                    </div>
                                </div>
                            </td>
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
                            <td class="table-td text-center text-sm {{ $isPartTime ? '' : 'text-slate-500' }}">
                                @if($isPartTime)
                                    @if($workedHours !== null)
                                        <span class="badge badge-info font-semibold">{{ $fmt($workedHours) }}h</span>
                                    @else
                                        <span class="text-slate-400 text-sm">—</span>
                                    @endif
                                @else
                                    {{ $workedHours !== null ? $fmt($workedHours) . 'h' : '—' }}
                                @endif
                            </td>
                            <td class="table-td text-center">
                                @if($isPartTime)
                                    <span class="text-slate-300 dark:text-slate-600 text-sm">—</span>
                                @elseif($cong !== null)
                                    <span class="badge badge-info font-semibold" @if($leaveFraction !== null) title="Đã trừ {{ $fmt($leaveFraction) }} công nghỉ theo giờ đã duyệt" @endif>{{ $fmt($cong) }} công</span>
                                @else
                                    <span class="text-slate-400 text-sm">—</span>
                                @endif
                            </td>
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
                                @if($log->full_credit)
                                    <span class="badge badge-success block mt-1" title="Có đơn được duyệt tha lỗi — không tính kỷ luật/nhắc nhở trễ-sớm (công vẫn tính theo giờ thực tế)">Đã tha lỗi</span>
                                @elseif($hasRecordedCorrection)
                                    <span class="badge badge-neutral block mt-1" title="Đã có đơn được duyệt cho lượt chấm công này — không tha lỗi công, vẫn tính trễ/sớm theo thực tế">Ghi nhận</span>
                                @endif
                            </td>
                            <td class="table-td text-center text-xs text-slate-500">
                                {{ strtoupper($log->check_in_method ?? '—') }}
                            </td>
                            <td class="table-td text-center">
                                <div class="flex items-center justify-center gap-1">
                                    @can('edit-attendance-logs')
                                    <button type="button" onclick='openEditAttendanceLogModal({{ json_encode([
                                        "id"=>$log->id,
                                        "employee_name"=>$log->employee?->name,
                                        "work_date"=>$log->work_date->format("d/m/Y"),
                                        "check_in_at"=>$log->check_in_at?->format("H:i"),
                                        "check_out_at"=>$log->check_out_at?->format("H:i"),
                                    ]) }})'
                                            class="btn-ghost btn-sm text-amber-600 dark:text-amber-400" title="Sửa giờ chấm công">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    @endcan
                                    @can('delete-attendance-logs')
                                    <button type="button" onclick="openDeleteAttendanceLogModal({{ $log->id }}, {{ Illuminate\Support\Js::from($log->employee?->name) }}, '{{ $log->work_date->format('d/m/Y') }}')"
                                            class="btn-ghost btn-sm text-red-600 dark:text-red-400" title="Xoá bản ghi chấm công">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                    @endcan
                                    @cannot('edit-attendance-logs')
                                        @cannot('delete-attendance-logs')
                                        <span class="text-slate-300 dark:text-slate-600 text-sm">—</span>
                                        @endcannot
                                    @endcannot
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="11" class="table-td text-center py-8 text-slate-400">
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

@can('create-attendance-logs')
@push('modals')
<div id="createAttendanceLogModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('createAttendanceLogModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-person-check text-pcrm-600"></i> Chấm công hộ
            </h3>
            <button onclick="closeModal('createAttendanceLogModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form action="{{ route('attendance-logs.store') }}" method="POST" class="px-4 sm:px-6 py-4 sm:py-5 space-y-4">
            @csrf
            <input type="hidden" name="_modal" value="createAttendanceLogModal">
            <div>
                <x-employee-combobox id="createAttendanceLogEmployeeCombo" name="employee_id" :employees="$employees" :selected="old('employee_id')"
                    label="Nhân viên" placeholder="Tìm theo tên, mã NV..." />
                @error('employee_id') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">Ngày <span class="text-red-500">*</span></label>
                <input type="date" id="createAttendanceLogWorkDate" name="work_date" class="form-input" value="{{ old('work_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                @error('work_date') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">Ca</label>
                <select id="createAttendanceLogShift" name="shift_schedule_id" class="form-input">
                    <option value="">Ngoài lịch (không có ca xếp)</option>
                </select>
                <p class="text-xs text-slate-400 mt-1" id="createAttendanceLogShiftHint"></p>
                @error('shift_schedule_id') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Giờ vào</label>
                    <input type="time" name="check_in_at" class="form-input" value="{{ old('check_in_at') }}">
                    @error('check_in_at') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Giờ ra</label>
                    <input type="time" name="check_out_at" class="form-input" value="{{ old('check_out_at') }}">
                    @error('check_out_at') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <p class="text-xs text-slate-400">Nhập ít nhất 1 trong 2 ô. Trễ/sớm được tính lại tự động theo ca đã chọn ở trên (nếu có). Không cần xác thực GPS/WiFi vì admin nhập tay.</p>
            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('createAttendanceLogModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-primary"><i class="bi bi-floppy"></i> Tạo chấm công</button>
            </div>
        </form>
    </div>
</div>
@endpush

@push('scripts')
<script>
// Đếm số lần gọi để bỏ qua response trả về trễ (stale) — chọn nhân viên rồi đổi ngày liên tiếp
// (hoặc ngược lại) bắn 2 request gần như đồng thời; nếu request CŨ hơn phản hồi SAU request MỚI,
// nó ghi đè lên danh sách ca đúng bằng danh sách ca của lượt gọi trước đó (bug thực tế đã gặp:
// dropdown lẫn cả ca của ngày khác vào ngày đang chọn).
let employeeShiftsRequestSeq = 0;

function loadEmployeeShiftsForManualLog() {
    const requestId = ++employeeShiftsRequestSeq;
    const combo = document.getElementById('createAttendanceLogEmployeeCombo');
    const employeeId = combo ? combo.querySelector('.emp-combobox-value').value : '';
    const workDate = document.getElementById('createAttendanceLogWorkDate').value;
    const select = document.getElementById('createAttendanceLogShift');
    const hint = document.getElementById('createAttendanceLogShiftHint');

    select.innerHTML = '<option value="">Ngoài lịch (không có ca xếp)</option>';
    hint.textContent = '';

    if (!employeeId || !workDate) {
        return Promise.resolve();
    }

    return fetch('{{ url("/attendance-logs/employee-shifts") }}?employee_id=' + encodeURIComponent(employeeId) + '&work_date=' + encodeURIComponent(workDate), {
        headers: { 'Accept': 'application/json' },
    })
    .then(res => res.json())
    .then(function (body) {
        if (requestId !== employeeShiftsRequestSeq) return; // có lượt gọi mới hơn đã bắn ra, bỏ kết quả cũ này
        select.innerHTML = '<option value="">Ngoài lịch (không có ca xếp)</option>';
        const schedules = body.data || [];
        schedules.forEach(function (s) {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.label + (s.has_attendance_log ? ' — đã có bản ghi, dùng "Sửa"' : '');
            select.appendChild(opt);
        });
        if (schedules.length === 0) {
            hint.textContent = 'Nhân viên không có ca xếp cho ngày này — sẽ tạo chấm công ngoài lịch.';
        } else if (schedules.length > 1) {
            hint.textContent = 'Nhân viên có nhiều ca vào ngày này — vui lòng chọn đúng ca cần chấm công hộ.';
            select.value = '';
        } else {
            select.value = schedules[0].id;
        }
    })
    .catch(function () {
        if (requestId !== employeeShiftsRequestSeq) return;
        hint.textContent = 'Không tải được danh sách ca, vui lòng thử lại.';
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const combo = document.getElementById('createAttendanceLogEmployeeCombo');
    const employeeHidden = combo ? combo.querySelector('.emp-combobox-value') : null;
    const workDateInput = document.getElementById('createAttendanceLogWorkDate');

    if (employeeHidden) employeeHidden.addEventListener('change', loadEmployeeShiftsForManualLog);
    if (workDateInput) workDateInput.addEventListener('change', loadEmployeeShiftsForManualLog);

    @if($errors->any() && old('_modal') === 'createAttendanceLogModal')
        openModal('createAttendanceLogModal');
        loadEmployeeShiftsForManualLog().then(function () {
            document.getElementById('createAttendanceLogShift').value = '{{ old('shift_schedule_id') }}';
        });
    @endif
});
</script>
@endpush
@endcan

@can('export-attendance')
@push('modals')
    <x-export-range-modal id="exportTimesheetModal" title="Xuất Bảng chấm công"
        :export-url="route('attendance-logs.export-timesheet')" :ref-date="now()"
        :hidden="['branch_id' => request('branch_id'), 'team_id' => request('team_id'), 'employee_id' => request('employee_id')]" />
@endpush
@endcan

@can('edit-attendance-logs')
@push('modals')
<div id="editAttendanceLogModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('editAttendanceLogModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-pencil-square text-amber-500"></i> Sửa giờ chấm công
            </h3>
            <button onclick="closeModal('editAttendanceLogModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form id="editAttendanceLogForm" method="POST" class="px-4 sm:px-6 py-4 sm:py-5 space-y-4">
            @csrf @method('PUT')
            <input type="hidden" name="_modal" value="editAttendanceLogModal">
            <input type="hidden" id="editAttendanceLogEditId" name="_edit_id">
            <input type="hidden" id="editAttendanceLogEditEmployeeName" name="_employee_name">
            <input type="hidden" id="editAttendanceLogEditWorkDate" name="_work_date">
            <p class="text-sm text-slate-500 dark:text-slate-400">
                <span id="editAttendanceLogEmployee" class="font-medium text-slate-700 dark:text-slate-200"></span>
                — ngày <span id="editAttendanceLogDate"></span>
            </p>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Giờ vào</label>
                    <input type="time" id="editAttendanceLogCheckIn" name="check_in_at" class="form-input">
                    @error('check_in_at') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Giờ ra</label>
                    <input type="time" id="editAttendanceLogCheckOut" name="check_out_at" class="form-input">
                    @error('check_out_at') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <p class="text-xs text-slate-400">Để trống 1 ô để xoá giờ đó khỏi bản ghi. Trễ/sớm sẽ được tính lại tự động theo ca đã xếp (nếu có).</p>
            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('editAttendanceLogModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-primary"><i class="bi bi-floppy"></i> Cập nhật</button>
            </div>
        </form>
    </div>
</div>
@endpush

@push('scripts')
<script>
function openEditAttendanceLogModal(data) {
    document.getElementById('editAttendanceLogEmployee').textContent = data.employee_name ?? '—';
    document.getElementById('editAttendanceLogDate').textContent = data.work_date ?? '';
    document.getElementById('editAttendanceLogCheckIn').value = data.check_in_at ?? '';
    document.getElementById('editAttendanceLogCheckOut').value = data.check_out_at ?? '';
    document.getElementById('editAttendanceLogEditId').value = data.id ?? '';
    document.getElementById('editAttendanceLogEditEmployeeName').value = data.employee_name ?? '';
    document.getElementById('editAttendanceLogEditWorkDate').value = data.work_date ?? '';
    document.getElementById('editAttendanceLogForm').action = '/attendance-logs/' + data.id;
    openModal('editAttendanceLogModal');
}

@if($errors->any() && old('_modal') === 'editAttendanceLogModal')
document.addEventListener('DOMContentLoaded', function() {
    openEditAttendanceLogModal({
        id: '{{ old("_edit_id") }}',
        employee_name: '{{ old("_employee_name") }}',
        work_date: '{{ old("_work_date") }}',
        check_in_at: '{{ old("check_in_at") }}',
        check_out_at: '{{ old("check_out_at") }}',
    });
});
@endif
</script>
@endpush
@endcan

@can('delete-attendance-logs')
@push('modals')
<div id="deleteAttendanceLogModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('deleteAttendanceLogModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-sm p-4 sm:p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center shrink-0">
                <i class="bi bi-exclamation-triangle text-red-600 dark:text-red-400"></i>
            </div>
            <div>
                <h3 class="font-semibold text-slate-900 dark:text-white">Xoá bản ghi chấm công</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">Hành động này không thể hoàn tác.</p>
            </div>
        </div>
        <p class="text-sm text-slate-700 dark:text-slate-300 mb-2">
            Xác nhận xoá lượt chấm công của <strong id="deleteAttendanceLogEmployee"></strong> ngày <strong id="deleteAttendanceLogDate"></strong>?
        </p>
        <p class="text-sm text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 rounded-lg px-3 py-2 mb-5">
            <i class="bi bi-info-circle mr-1"></i>
            Bản ghi cũng sẽ biến mất khỏi lịch sử chấm công cá nhân của nhân viên này.
        </p>
        <div class="flex gap-3">
            <button onclick="closeModal('deleteAttendanceLogModal')" class="btn-secondary flex-1">Hủy</button>
            <form id="deleteAttendanceLogForm" method="POST" class="flex-1">
                @csrf @method('DELETE')
                <button type="submit" class="w-full px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-medium transition-colors">
                    Xoá vĩnh viễn
                </button>
            </form>
        </div>
    </div>
</div>
@endpush

@push('scripts')
<script>
function openDeleteAttendanceLogModal(id, employeeName, workDate) {
    document.getElementById('deleteAttendanceLogEmployee').textContent = employeeName ?? '—';
    document.getElementById('deleteAttendanceLogDate').textContent = workDate ?? '';
    document.getElementById('deleteAttendanceLogForm').action = '/attendance-logs/' + id;
    openModal('deleteAttendanceLogModal');
}
</script>
@endpush
@endcan

@push('scripts')
<script>
function pad2(n) { return String(n).padStart(2, '0'); }
function toDateInputValue(d) { return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate()); }

function setAttendanceQuickRange(type) {
    const today = new Date();
    let from, to;

    if (type === 'today') {
        from = today; to = today;
    } else if (type === 'week') {
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
        <form action="{{ route('attendance-logs.index') }}" method="GET" class="flex-1 flex flex-col overflow-y-auto">
            @if($isDefaultTodayView)
                <input type="hidden" name="all" value="1">
            @endif
            <div class="p-5 space-y-4 flex-1">
                <div>
                    <x-employee-combobox name="employee_id" :employees="$employees" :selected="request('employee_id')"
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
                    <label class="block text-xs font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wider mb-2">Từ ngày</label>
                    <input type="date" id="dateFromInput" name="date_from" value="{{ request('date_from') ?: ($isDefaultTodayView ? now()->toDateString() : '') }}" class="form-input text-sm w-full">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wider mb-2">Đến ngày</label>
                    <input type="date" id="dateToInput" name="date_to" value="{{ request('date_to') ?: ($isDefaultTodayView ? now()->toDateString() : '') }}" class="form-input text-sm w-full">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wider mb-2">Chọn nhanh</label>
                    <div class="grid grid-cols-3 gap-1">
                        <button type="button" onclick="setAttendanceQuickRange('today')" class="py-2 px-1 text-[10px] font-bold rounded-lg bg-slate-50 border border-slate-200 text-slate-750 hover:bg-slate-100 shadow-sm">Hôm nay</button>
                        <button type="button" onclick="setAttendanceQuickRange('week')" class="py-2 px-1 text-[10px] font-bold rounded-lg bg-slate-50 border border-slate-200 text-slate-750 hover:bg-slate-100 shadow-sm">Tuần này</button>
                        <button type="button" onclick="setAttendanceQuickRange('month')" class="py-2 px-1 text-[10px] font-bold rounded-lg bg-slate-50 border border-slate-200 text-slate-750 hover:bg-slate-100 shadow-sm">Tháng này</button>
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
    window.location.href = "{{ route('attendance-logs.index') }}";
}
</script>
@endpush
