@extends('layouts.admin')

@section('title', 'Yêu cầu và Phê duyệt')
@section('page-title', 'Yêu cầu và Phê duyệt')
@section('breadcrumb', 'Ca làm việc & Chấm công')

@section('content')
    @php
        $typeDots = [
            'attendance_correction' => 'bg-sky-500',
            'business_trip'         => 'bg-amber-500',
            'late_early'            => 'bg-orange-500',
            'leave'                 => 'bg-pcrm-500',
            'time_change'           => 'bg-violet-500',
            'shift_swap'            => 'bg-emerald-500',
        ];
        $typeLabels = [
            'attendance_correction' => 'Lượt chấm công',
            'business_trip'         => 'Công tác/Ra ngoài',
            'late_early'            => 'Đi muộn về sớm',
            'leave'                 => 'Nghỉ phép',
            'time_change'           => 'Thay đổi giờ vào/ra',
            'shift_swap'            => 'Đổi ca làm',
        ];
        $typeColors = [
            'attendance_correction' => 'bg-sky-100 dark:bg-sky-900/30 text-sky-700 dark:text-sky-400',
            'business_trip'         => 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400',
            'late_early'            => 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400',
            'leave'                 => 'bg-pcrm-100 dark:bg-pcrm-900/30 text-pcrm-700 dark:text-pcrm-400',
            'time_change'           => 'bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-400',
            'shift_swap'            => 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400',
        ];
    @endphp

    <div class="page-header">
        <div>
            <p class="page-subtitle">
                @if($isApprover)
                    Lượt chấm công, Công tác/Ra ngoài, Đi muộn về sớm, Nghỉ phép, Thay đổi giờ vào/ra, Đổi ca làm — của toàn bộ nhân viên
                @else
                    Yêu cầu của bạn — Lượt chấm công, Công tác/Ra ngoài, Đi muộn về sớm, Nghỉ phép, Thay đổi giờ vào/ra, Đổi ca làm
                @endif
            </p>
        </div>
        <button onclick="openModal('createStaffRequestModal')" class="btn-primary">
            <i class="bi bi-plus-lg"></i>
            <span>Tạo yêu cầu</span>
        </button>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-3 mb-4">
        <a href="{{ route('staff-requests.index', request()->except(['type', 'page'])) }}"
           class="rounded-xl border px-3 py-2.5 transition-colors {{ !request()->filled('type') ? 'border-pcrm-400 dark:border-pcrm-500 bg-pcrm-50/60 dark:bg-pcrm-900/20' : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:border-slate-300 dark:hover:border-slate-600' }}">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 truncate">Tất cả</p>
            <p class="text-xl font-bold text-slate-900 dark:text-white mt-1">{{ array_sum($typeCounts->toArray()) }}</p>
        </a>
        @foreach($typeLabels as $key => $label)
            <a href="{{ route('staff-requests.index', array_merge(request()->except(['type', 'page']), ['type' => $key])) }}"
               class="rounded-xl border px-3 py-2.5 transition-colors {{ request('type') === $key ? 'border-pcrm-400 dark:border-pcrm-500 bg-pcrm-50/60 dark:bg-pcrm-900/20' : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:border-slate-300 dark:hover:border-slate-600' }}">
                <div class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full {{ $typeDots[$key] }}"></span>
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400 truncate">{{ $label }}</p>
                </div>
                <p class="text-xl font-bold text-slate-900 dark:text-white mt-1">{{ $typeCounts[$key] ?? 0 }}</p>
            </a>
        @endforeach
    </div>

    <div class="card">
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700">
            <form action="{{ route('staff-requests.index') }}" method="GET" class="flex flex-wrap items-end gap-2">
                @if($isApprover)
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
                <div class="min-w-[220px]">
                    <x-employee-combobox name="employee_id" :employees="$employees" :selected="request('employee_id')"
                        label="Nhân viên" placeholder="Tìm theo tên, mã NV..." />
                </div>
                @endif
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Loại yêu cầu</label>
                    <select name="type" class="form-input h-9 text-sm min-w-[190px]">
                        <option value="">Tất cả</option>
                        <option value="attendance_correction" @selected(request('type') === 'attendance_correction')>Lượt chấm công</option>
                        <option value="business_trip" @selected(request('type') === 'business_trip')>Công tác/Ra ngoài</option>
                        <option value="late_early" @selected(request('type') === 'late_early')>Đi muộn về sớm</option>
                        <option value="leave" @selected(request('type') === 'leave')>Nghỉ phép</option>
                        <option value="time_change" @selected(request('type') === 'time_change')>Thay đổi giờ vào/ra</option>
                        <option value="shift_swap" @selected(request('type') === 'shift_swap')>Đổi ca làm</option>
                    </select>
                </div>
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
                @if(request()->anyFilled(['branch_id', 'team_id', 'employee_id', 'type', 'status']))
                <a href="{{ route('staff-requests.index') }}" class="btn-secondary h-9 px-3 inline-flex items-center gap-1 text-sm">
                    <i class="bi bi-x text-sm"></i>
                </a>
                @endif
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-container border-0 rounded-none">
                <table class="table-base min-w-[900px]">
                    <thead>
                        <tr>
                            <th class="table-th">Mã</th>
                            @if($isApprover)<th class="table-th">Nhân viên</th>@endif
                            <th class="table-th">Loại</th>
                            <th class="table-th">Ngày/Thời gian</th>
                            <th class="table-th">Nội dung</th>
                            <th class="table-th text-center">Trạng thái</th>
                            <th class="table-th text-center">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $r)
                        <tr class="table-tr-hover">
                            <td class="table-td font-mono text-xs">{{ $r['code'] }}</td>
                            @if($isApprover)
                            <td class="table-td">
                                <p class="font-medium">{{ $r['employee']?->name ?? '—' }}</p>
                                <p class="text-xs text-slate-400">{{ $r['employee']?->branch?->name ?? '—' }}</p>
                            </td>
                            @endif
                            <td class="table-td">
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $typeColors[$r['type_key']] ?? 'badge-neutral' }}">{{ $r['type_label'] }}</span>
                            </td>
                            <td class="table-td text-sm">{{ $r['work_date_label'] }}</td>
                            <td class="table-td text-sm max-w-xs truncate" title="{{ $r['summary'] }}">{{ $r['summary'] }}</td>
                            <td class="table-td text-center">
                                <span class="badge {{ $r['status_badge'] }}">{{ $r['status_label'] }}</span>
                                @if($r['status'] === 'rejected' && $r['rejection_reason'])
                                    <p class="text-xs text-red-500 mt-1">{{ $r['rejection_reason'] }}</p>
                                @endif
                            </td>
                            <td class="table-td text-center">
                                <div class="flex items-center justify-center gap-1">
                                    @if($r['status'] === 'pending')
                                        @can($r['approve_permission'])
                                        @if($r['type_key'] === 'late_early')
                                        <button onclick="openApproveLateEarlyModal('{{ $r['approve_route'] }}', '{{ addslashes($r['code']) }}')"
                                                class="btn-ghost btn-sm text-emerald-600 dark:text-emerald-400" title="Duyệt">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                        @else
                                        <form action="{{ $r['approve_route'] }}" method="POST" class="inline"
                                              onsubmit="return confirm('Xác nhận duyệt yêu cầu {{ $r['code'] }}?')">
                                            @csrf
                                            <button type="submit" class="btn-ghost btn-sm text-emerald-600 dark:text-emerald-400" title="Duyệt">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>
                                        @endif
                                        <button onclick="openRejectStaffRequestModal('{{ $r['reject_route'] }}', '{{ addslashes($r['code']) }}')"
                                                class="btn-ghost btn-sm text-red-600 dark:text-red-400" title="Từ chối">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                        @endcan
                                        @if($r['can_manage_own'])
                                        <form action="{{ $r['destroy_route'] }}" method="POST" class="inline"
                                              onsubmit="return confirm('Huỷ yêu cầu này?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn-ghost btn-sm text-slate-500" title="Huỷ yêu cầu">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                        @endif
                                    @else
                                        <span class="text-xs text-slate-400">
                                            {{ $r['reviewer']?->name ? 'bởi ' . $r['reviewer']->name : '—' }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ $isApprover ? 7 : 6 }}" class="table-td text-center py-8 text-slate-400">
                                <i class="bi bi-inbox text-3xl mb-2 block opacity-40"></i>
                                <p>Chưa có yêu cầu nào</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($requests->hasPages())
        <div class="card-footer">
            {{ $requests->links() }}
        </div>
        @endif
    </div>
@endsection

@push('modals')
<div id="createStaffRequestModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('createStaffRequestModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-plus-circle text-pcrm-600"></i> Tạo yêu cầu
            </h3>
            <button onclick="closeModal('createStaffRequestModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>

        <div class="px-4 sm:px-6 py-4 sm:py-5 space-y-4">
            <div>
                <label class="form-label">Loại yêu cầu <span class="text-red-500">*</span></label>
                <select id="srTypeSelect" class="form-input" onchange="srSwitchType(this.value)">
                    <option value="attendance_correction">Lượt chấm công</option>
                    <option value="business_trip">Công tác/Ra ngoài</option>
                    <option value="late_early">Đi muộn về sớm</option>
                    <option value="leave">Nghỉ phép</option>
                    <option value="time_change">Thay đổi giờ vào/ra</option>
                    <option value="shift_swap">Đổi ca làm</option>
                </select>
            </div>

            {{-- ── 4 loại dùng chung form → staff-requests.store ── --}}
            <form id="srStaffForm" action="{{ route('staff-requests.store') }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="type" id="srStaffType" value="attendance_correction">

                @if($isApprover)
                <div>
                    <x-employee-combobox name="employee_id" :employees="$employees"
                        label="Nhân viên" placeholder="Chọn nhân viên cần tạo yêu cầu..." />
                    @error('employee_id') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                @endif

                <div>
                    <label class="form-label">Ngày <span class="text-red-500">*</span></label>
                    <input type="date" name="work_date" class="form-input" required>
                </div>

                {{-- attendance_correction --}}
                <div class="sr-field-group" data-type="attendance_correction">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Giờ vào (nếu cần sửa)</label>
                            <input type="time" name="check_in_at" class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Giờ ra (nếu cần sửa)</label>
                            <input type="time" name="check_out_at" class="form-input">
                        </div>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Nhập ít nhất 1 trong 2 ô — hệ thống sẽ bổ sung/sửa lại lượt chấm công của ngày đã chọn.</p>
                </div>

                {{-- business_trip --}}
                <div class="sr-field-group hidden" data-type="business_trip">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Từ giờ <span class="text-red-500">*</span></label>
                            <input type="time" name="from_time" class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Đến giờ <span class="text-red-500">*</span></label>
                            <input type="time" name="to_time" class="form-input">
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="form-label">Địa điểm <span class="text-red-500">*</span></label>
                        <input type="text" name="location" class="form-input" placeholder="VD: Gặp khách tại quận 1">
                    </div>
                </div>

                {{-- late_early --}}
                <div class="sr-field-group hidden" data-type="late_early">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Loại <span class="text-red-500">*</span></label>
                            <select name="mode" class="form-input">
                                <option value="late">Đến muộn</option>
                                <option value="early">Về sớm</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Số phút <span class="text-red-500">*</span></label>
                            <input type="number" name="minutes" class="form-input" min="1" max="480">
                        </div>
                    </div>
                </div>

                {{-- time_change --}}
                <div class="sr-field-group hidden" data-type="time_change">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Giờ vào mới <span class="text-red-500">*</span></label>
                            <input type="time" name="new_check_in" class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Giờ ra mới <span class="text-red-500">*</span></label>
                            <input type="time" name="new_check_out" class="form-input">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="form-label">Lý do <span class="text-red-500">*</span></label>
                    <textarea name="reason" rows="3" class="form-input" placeholder="Lý do..." required></textarea>
                    @error('reason') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                    <button type="button" onclick="closeModal('createStaffRequestModal')" class="btn-secondary">Hủy</button>
                    <button type="submit" class="btn-primary"><i class="bi bi-send"></i> Gửi yêu cầu</button>
                </div>
            </form>

            {{-- ── Nghỉ phép — dùng đúng route/field của LeaveRequestsController ── --}}
            <form id="srLeaveForm" action="{{ route('leave-requests.store') }}" method="POST" class="space-y-4 hidden" data-own-employee-id="{{ auth()->user()->employee?->id }}">
                @csrf
                @if($isApprover)
                <div>
                    <x-employee-combobox name="employee_id" :employees="$employees"
                        label="Nhân viên" placeholder="Chọn nhân viên cần tạo đơn..." />
                </div>
                @endif
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Ngày bắt đầu <span class="text-red-500">*</span></label>
                        <input type="date" name="date_from" class="form-input" required>
                    </div>
                    <div>
                        <label class="form-label">Đến ngày <span class="text-red-500">*</span></label>
                        <input type="date" name="date_to" class="form-input" required>
                    </div>
                </div>
                <div>
                    <label class="form-label">Loại nghỉ phép <span class="text-red-500">*</span></label>
                    <select name="type" class="form-input" required>
                        <option value="annual">Nghỉ phép năm</option>
                        <option value="sick">Nghỉ ốm</option>
                        <option value="unpaid">Nghỉ không lương</option>
                        <option value="other">Khác</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Ca làm</label>
                    <select name="shift_schedule_id" class="form-input sr-leave-shift-select">
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
                </div>
                <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                    <button type="button" onclick="closeModal('createStaffRequestModal')" class="btn-secondary">Hủy</button>
                    <button type="submit" class="btn-primary"><i class="bi bi-send"></i> Gửi đơn</button>
                </div>
                <script type="application/json" class="sr-leave-shift-data">@json($shiftScheduleOptions)</script>
            </form>

            {{-- ── Đổi ca làm — cần chọn đúng ca cụ thể, tạo từ trang Xếp ca ── --}}
            <div id="srSwapNotice" class="hidden space-y-4">
                <div class="rounded-lg bg-slate-50 dark:bg-slate-700/50 px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                    <i class="bi bi-info-circle text-pcrm-500 mr-1"></i>
                    Yêu cầu đổi ca cần chọn đúng ca làm việc cụ thể của bạn và đồng nghiệp — vui lòng tạo từ trang <strong>Xếp ca</strong>.
                </div>
                <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                    <button type="button" onclick="closeModal('createStaffRequestModal')" class="btn-secondary">Đóng</button>
                    <a href="{{ route('shift-schedules.index') }}" class="btn-primary"><i class="bi bi-calendar-week"></i> Đến trang Xếp ca</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="rejectStaffRequestModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('rejectStaffRequestModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-semibold text-slate-900 dark:text-white">Từ chối yêu cầu <span id="rejectStaffRequestCode"></span></h3>
            <button onclick="closeModal('rejectStaffRequestModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form id="rejectStaffRequestForm" method="POST" class="px-4 sm:px-6 py-4 sm:py-5 space-y-4">
            @csrf
            <div>
                <label class="form-label">Lý do từ chối <span class="text-red-500">*</span></label>
                <textarea name="rejection_reason" rows="3" class="form-input" required></textarea>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('rejectStaffRequestModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-medium">Từ chối</button>
            </div>
        </form>
    </div>
</div>

<div id="approveLateEarlyModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('approveLateEarlyModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-semibold text-slate-900 dark:text-white">Duyệt yêu cầu <span id="approveLateEarlyCode"></span></h3>
            <button onclick="closeModal('approveLateEarlyModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form id="approveLateEarlyForm" method="POST" class="px-4 sm:px-6 py-4 sm:py-5 space-y-4">
            @csrf
            <div>
                <label class="form-label">Kết quả duyệt <span class="text-red-500">*</span></label>
                <select name="outcome" class="form-input" required>
                    <option value="normal">Công thường (tính đủ công, quên đi muộn/về sớm)</option>
                    <option value="actual">Trừ giờ thực tế (vẫn tính đi muộn/về sớm)</option>
                </select>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('approveLateEarlyModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-primary"><i class="bi bi-check-lg"></i> Duyệt</button>
            </div>
        </form>
    </div>
</div>
@endpush

@push('scripts')
<script>
function srSwitchType(type) {
    document.getElementById('srStaffType').value = type;

    const staffForm = document.getElementById('srStaffForm');
    const leaveForm  = document.getElementById('srLeaveForm');
    const swapNotice = document.getElementById('srSwapNotice');

    staffForm.classList.add('hidden');
    leaveForm.classList.add('hidden');
    swapNotice.classList.add('hidden');

    if (type === 'leave') {
        leaveForm.classList.remove('hidden');
    } else if (type === 'shift_swap') {
        swapNotice.classList.remove('hidden');
    } else {
        staffForm.classList.remove('hidden');
        document.querySelectorAll('.sr-field-group').forEach(function(el) {
            el.classList.toggle('hidden', el.dataset.type !== type);
        });
    }
}

function openRejectStaffRequestModal(actionUrl, code) {
    document.getElementById('rejectStaffRequestCode').textContent = code;
    document.getElementById('rejectStaffRequestForm').action = actionUrl;
    openModal('rejectStaffRequestModal');
}

function openApproveLateEarlyModal(actionUrl, code) {
    document.getElementById('approveLateEarlyCode').textContent = code;
    document.getElementById('approveLateEarlyForm').action = actionUrl;
    openModal('approveLateEarlyModal');
}

function srRefreshLeaveShifts() {
    const form = document.getElementById('srLeaveForm');
    if (!form) return;
    const select = form.querySelector('.sr-leave-shift-select');
    const dataEl = form.querySelector('.sr-leave-shift-data');
    const data = JSON.parse(dataEl.textContent || '[]');
    const empHidden = form.querySelector('.emp-combobox-value');
    const employeeId = empHidden ? empHidden.value : form.dataset.ownEmployeeId;
    const dateFrom = form.querySelector('[name="date_from"]').value;
    const dateTo = form.querySelector('[name="date_to"]').value || dateFrom;
    const currentValue = select.value;

    const matches = data.filter(function (s) {
        if (!employeeId || String(s.employee_id) !== String(employeeId)) return false;
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
    const leaveForm = document.getElementById('srLeaveForm');
    if (!leaveForm) return;
    ['date_from', 'date_to'].forEach(function (name) {
        const el = leaveForm.querySelector('[name="' + name + '"]');
        if (el) el.addEventListener('change', srRefreshLeaveShifts);
    });
    const empHidden = leaveForm.querySelector('.emp-combobox-value');
    if (empHidden) empHidden.addEventListener('change', srRefreshLeaveShifts);
});
</script>
@endpush
