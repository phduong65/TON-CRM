@extends('layouts.admin')

@section('title', 'Nhật ký hoạt động')
@section('page-title', 'Nhật ký hoạt động')
@section('page-subtitle', 'Lịch sử thao tác của người dùng trên hệ thống')
@section('breadcrumb', 'Hệ thống / Nhật ký')

@php
$logBadges = [
    'penalty'            => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    'employee'           => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    'user'               => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    'role'               => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
    'profile'            => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    'login'              => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400',
    'appeal'             => 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
    'attendance'         => 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400',
    'leave_request'      => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
    'shift_swap_request' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400',
    'staff_request'      => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
    'branch'             => 'bg-lime-100 text-lime-700 dark:bg-lime-900/30 dark:text-lime-400',
    'report'             => 'bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-900/30 dark:text-fuchsia-400',
    'shift_schedule'     => 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400',
    'reward'             => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
    'reward_category'    => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
    'reward_type'        => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
    'shift'              => 'bg-slate-200 text-slate-700 dark:bg-slate-600/40 dark:text-slate-300',
    'team'               => 'bg-pink-100 text-pink-700 dark:bg-pink-900/30 dark:text-pink-400',
];
$logLabels = [
    'penalty'            => 'Xử phạt',
    'employee'           => 'Nhân viên',
    'user'               => 'Người dùng',
    'role'               => 'Vai trò',
    'profile'            => 'Hồ sơ',
    'login'              => 'Đăng nhập',
    'appeal'             => 'Khiếu nại',
    'attendance'         => 'Chấm công',
    'leave_request'      => 'Nghỉ phép',
    'shift_swap_request' => 'Đổi ca',
    'staff_request'      => 'Yêu cầu & Phê duyệt',
    'branch'             => 'Chi nhánh',
    'report'             => 'Báo cáo',
    'shift_schedule'     => 'Xếp ca',
    'reward'             => 'Thưởng điểm',
    'reward_category'    => 'Danh mục thưởng',
    'reward_type'        => 'Loại thưởng',
    'shift'              => 'Ca làm việc',
    'team'               => 'Đội nhóm',
];
$propLabels = [
    'code'             => 'Mã',
    'name'             => 'Tên',
    'email'            => 'Email',
    'role'             => 'Vai trò',
    'violation'        => 'Vi phạm',
    'employee_name'    => 'Nhân viên',
    'employee_code'    => 'Mã NV',
    'points_deducted'  => 'Trừ điểm',
    'money_deducted'   => 'Tiền phạt',
    'members_count'    => 'Số thành viên',
    'points'           => 'Điểm',
    'position'         => 'Chức vụ',
    'branch'           => 'Chi nhánh',
    'team'             => 'Nhóm',
    'reason'           => 'Lý do',
    'approved_by'      => 'Người duyệt',
    'new_status'       => 'Trạng thái mới',
    'password_changed' => 'Đổi mật khẩu',
    'permissions_count'=> 'Số quyền',
    'ip'               => 'Địa chỉ IP',
    'device'           => 'Thiết bị',
    'location'         => 'Vị trí GPS',
];

// IP/thiết bị là dữ liệu nhạy cảm (định danh người dùng) — chỉ admin được xem trong nhật ký,
// manager/director tuy có quyền view-activity-log vẫn không thấy 2 trường này.
$actIsAdminViewer = auth()->user()?->hasRole('admin') ?? false;
$actSensitiveKeys = ['ip', 'device'];
@endphp

@section('content')
    <div class="card">
        {{-- Category tabs --}}
        <div class="px-4 pt-3 pb-2 overflow-x-auto">
            @php
                $actActiveEvent = request('event');
                $actTabQuery    = request()->except(['event', 'page']);
            @endphp
            <div class="flex items-center gap-1.5 w-max">
                <a href="{{ route('activity.log', $actTabQuery) }}"
                   class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap transition-colors
                          {{ !$actActiveEvent ? 'bg-pcrm-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-700/60 dark:text-slate-300 dark:hover:bg-slate-700' }}">
                    Tất cả
                </a>
                @foreach($eventTypes as $evt)
                    <a href="{{ route('activity.log', array_merge($actTabQuery, ['event' => $evt])) }}"
                       class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap transition-colors
                              {{ $actActiveEvent === $evt ? 'bg-pcrm-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-700/60 dark:text-slate-300 dark:hover:bg-slate-700' }}">
                        {{ $logLabels[$evt] ?? $evt }}
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Filter bar --}}
        <div class="px-4 pb-3 border-b border-slate-100 dark:border-slate-700">
            @php
                $actFilterActive = request()->anyFilled(['search', 'event', 'date_from', 'date_to']);
            @endphp
            <form action="{{ route('activity.log') }}" method="GET">
                <input type="hidden" name="event" value="{{ request('event') }}">
                <div class="flex flex-wrap gap-2 items-end">
                    <div class="relative flex-1 basis-full sm:basis-0 min-w-[180px]">
                        <i class="bi bi-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                        <input type="text" name="search" value="{{ request('search') }}"
                               class="form-input pl-7 h-9 text-sm w-full" placeholder="Mô tả hoặc người thực hiện...">
                    </div>
                    <div class="flex flex-wrap gap-2 items-end flex-1 sm:flex-none">
                        <div class="w-[calc(50%-0.25rem)] sm:w-auto">
                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Từ ngày</label>
                            <input type="date" name="date_from" value="{{ request('date_from') }}"
                                   class="form-input h-9 text-sm w-full">
                        </div>
                        <div class="w-[calc(50%-0.25rem)] sm:w-auto">
                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Đến ngày</label>
                            <input type="date" name="date_to" value="{{ request('date_to') }}"
                                   class="form-input h-9 text-sm w-full">
                        </div>
                        <button type="submit" class="btn-secondary h-9 px-4 text-sm gap-1.5 shrink-0">
                            <i class="bi bi-funnel text-xs"></i> Lọc
                        </button>
                        @if($actFilterActive)
                        <a href="{{ route('activity.log') }}" class="btn-secondary h-9 px-3 text-sm inline-flex items-center gap-1 shrink-0">
                            <i class="bi bi-x text-sm"></i>
                        </a>
                        @endif
                        <span class="text-xs text-slate-400 dark:text-slate-500 ml-auto shrink-0 pb-2">{{ $activities->total() }} kết quả</span>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-container border-0 rounded-none">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th class="table-th w-32">Thời gian</th>
                            <th class="table-th w-36">Người thực hiện</th>
                            <th class="table-th w-24">Phân loại</th>
                            <th class="table-th">Hành động & Chi tiết</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($activities as $log)
                        <tr class="table-tr-hover align-top">
                            <td class="table-td text-xs text-slate-500 whitespace-nowrap">
                                <span class="block">{{ $log->created_at->format('d/m/Y') }}</span>
                                <span class="text-slate-400">{{ $log->created_at->format('H:i:s') }}</span>
                            </td>
                            <td class="table-td">
                                <span class="text-sm font-medium text-slate-800 dark:text-slate-200">
                                    {{ $log->causer?->name ?? 'Hệ thống' }}
                                </span>
                            </td>
                            <td class="table-td">
                                @php
                                    $bc = $logBadges[$log->log_name] ?? 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400';
                                    $bl = $logLabels[$log->log_name] ?? $log->log_name;
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $bc }}">
                                    {{ $bl }}
                                </span>
                            </td>
                            <td class="table-td whitespace-normal min-w-[240px]">
                                <p class="text-xs text-slate-700 dark:text-slate-300 leading-snug">
                                    {{ $log->description }}
                                </p>
                                @if($log->properties->isNotEmpty())
                                <div class="mt-1.5 flex flex-wrap gap-1">
                                    @foreach($log->properties as $key => $value)
                                        @continue(in_array($key, $actSensitiveKeys) && !$actIsAdminViewer)
                                        @if(!is_array($value) && !is_null($value) && $value !== '' && $value !== 0 && $value !== '0')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-slate-100 dark:bg-slate-700/60 text-slate-500 dark:text-slate-400">
                                            <span class="font-semibold text-slate-600 dark:text-slate-300">{{ $propLabels[$key] ?? $key }}:</span>
                                            {{ $value }}
                                        </span>
                                        @endif
                                    @endforeach
                                </div>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="table-td text-center py-8 text-slate-400">
                                <i class="bi bi-clipboard2-x text-3xl mb-2 block"></i>
                                <p>Chưa có hoạt động nào được ghi lại</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($activities->hasPages())
        <div class="card-footer">
            {{ $activities->links() }}
        </div>
        @endif
    </div>
@endsection
