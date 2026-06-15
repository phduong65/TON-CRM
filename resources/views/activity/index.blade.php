@extends('layouts.admin')

@section('title', 'Nhật ký hoạt động')
@section('page-title', 'Nhật ký hoạt động')
@section('breadcrumb', 'Hệ thống / Nhật ký')

@php
$logBadges = [
    'penalty'  => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    'employee' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    'user'     => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    'role'     => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
    'profile'  => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
];
$logLabels = [
    'penalty'  => 'Xử phạt',
    'employee' => 'Nhân viên',
    'user'     => 'Người dùng',
    'role'     => 'Vai trò',
    'profile'  => 'Hồ sơ',
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
];
@endphp

@section('content')
    <div class="card">
        {{-- Filter bar --}}
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700">
            <form action="{{ route('activity.log') }}" method="GET" class="flex flex-wrap gap-2 items-end">
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Tìm kiếm</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                           class="form-input h-9 text-sm w-52" placeholder="Mô tả hoặc người thực hiện...">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Phân loại</label>
                    <select name="event" class="form-input h-9 text-sm">
                        <option value="">Tất cả</option>
                        @foreach($eventTypes as $evt)
                            <option value="{{ $evt }}" @selected(request('event') === $evt)>
                                {{ $logLabels[$evt] ?? $evt }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Từ ngày</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-input h-9 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Đến ngày</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-input h-9 text-sm">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="btn-primary h-9 px-4 text-sm">
                        <i class="bi bi-funnel text-xs"></i> Lọc
                    </button>
                    @if(request()->anyFilled(['search', 'event', 'date_from', 'date_to']))
                    <a href="{{ route('activity.log') }}" class="btn-secondary h-9 px-4 text-sm inline-flex items-center gap-1">
                        <i class="bi bi-x-circle text-xs"></i> Xóa lọc
                    </a>
                    @endif
                </div>
                <div class="ml-auto flex items-end">
                    <p class="text-xs text-slate-400 dark:text-slate-500">{{ $activities->total() }} kết quả</p>
                </div>
            </form>
        </div>

        <div class="card-header border-t-0">
            <p class="text-sm text-slate-500 dark:text-slate-400">Tất cả các thay đổi và hành động trong hệ thống — thời gian theo múi giờ Hồ Chí Minh (UTC+7)</p>
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
                            <td class="table-td">
                                <p class="text-xs text-slate-700 dark:text-slate-300 leading-snug">
                                    {{ $log->description }}
                                </p>
                                @if($log->properties->isNotEmpty())
                                <div class="mt-1.5 flex flex-wrap gap-1">
                                    @foreach($log->properties as $key => $value)
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
