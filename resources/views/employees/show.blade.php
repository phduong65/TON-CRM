@extends('layouts.admin')

@section('title', $employee->name)
@section('page-title', $employee->name)
@section('breadcrumb', 'Nhân viên / Chi tiết')

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Profile Card -->
        <div class="lg:col-span-1">
            <div class="card text-center">
                <div class="card-body">
                    <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-pcrm-100 dark:bg-pcrm-900/50 flex items-center justify-center">
                        <span class="text-2xl font-bold text-pcrm-700 dark:text-pcrm-400">
                            {{ strtoupper(substr($employee->name, 0, 2)) }}
                        </span>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ $employee->name }}</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $employee->position ?? 'Chưa có chức vụ' }}</p>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Mã: {{ $employee->code ?? '—' }}</p>

                    <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">
                        <div class="flex justify-between text-sm mb-2">
                            <span class="text-slate-500">Chi nhánh</span>
                            <span class="font-medium">{{ $employee->branch->name ?? '—' }}</span>
                        </div>
                        <div class="flex justify-between text-sm mb-2">
                            <span class="text-slate-500">Đội nhóm</span>
                            <span class="font-medium">{{ $employee->team->name ?? '—' }}</span>
                        </div>
                        <div class="flex justify-between text-sm mb-2">
                            <span class="text-slate-500">Email</span>
                            <span class="font-medium">{{ $employee->email ?? '—' }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500">Điện thoại</span>
                            <span class="font-medium">{{ $employee->phone ?? '—' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Score Summary -->
            <div class="card mt-4">
                <div class="card-header">
                    <h4 class="font-semibold text-slate-900 dark:text-white">Thông tin điểm</h4>
                </div>
                <div class="card-body space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500">Tổng điểm</span>
                        <span class="text-lg font-bold text-pcrm-600 dark:text-pcrm-400">{{ number_format($employee->total_score) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500">Số lần vi phạm</span>
                        <span class="text-lg font-bold text-redzone-500">{{ $employee->penalties->count() }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Penalty History -->
            <div class="card">
                <div class="card-header flex items-center justify-between">
                    <h4 class="font-semibold text-slate-900 dark:text-white">Lịch sử xử phạt</h4>
                    <a href="{{ route('employees.penalties', $employee) }}" class="text-sm text-pcrm-600 dark:text-pcrm-400 hover:underline">
                        Xem tất cả <i class="ph-arrow-right text-xs"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    @if($employee->penalties->count() > 0)
                        <div class="divide-y divide-slate-100 dark:divide-slate-700">
                            @foreach($employee->penalties->take(5) as $penalty)
                            <div class="flex items-center justify-between px-6 py-3">
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $penalty->violation->name ?? 'N/A' }}</p>
                                    <p class="text-xs text-slate-400">{{ $penalty->created_at->format('d/m/Y H:i') }}</p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-sm font-semibold text-red-600 dark:text-red-400">-{{ number_format($penalty->total_points_deducted) }}đ</span>
                                    @php
                                        $s = $penalty->status;
                                        $cls = $s === 'approved' ? 'badge-success' : ($s === 'rejected' ? 'badge-danger' : 'badge-warning');
                                        $lbl = $s === 'approved' ? 'Đã duyệt' : ($s === 'rejected' ? 'Từ chối' : 'Chờ duyệt');
                                    @endphp
                                    <span class="{{ $cls }}">{{ $lbl }}</span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="p-6 text-center text-sm text-slate-400">
                            <i class="ph-check-circle text-2xl mb-2 block"></i>
                            <p>Chưa có vi phạm nào</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Score History -->
            <div class="card">
                <div class="card-header">
                    <h4 class="font-semibold text-slate-900 dark:text-white">Lịch sử điểm thưởng/phạt</h4>
                </div>
                <div class="card-body p-0">
                    @if($employee->scores->count() > 0)
                        <div class="table-container border-0 rounded-none">
                            <table class="table-base">
                                <thead>
                                    <tr>
                                        <th class="table-th">Ngày</th>
                                        <th class="table-th">Lý do</th>
                                        <th class="table-th text-right">Điểm</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($employee->scores->sortByDesc('created_at') as $score)
                                    <tr class="table-tr-hover">
                                        <td class="table-td">{{ $score->created_at->format('d/m/Y') }}</td>
                                        <td class="table-td">{{ $score->reason }}</td>
                                        <td class="table-td text-right font-semibold {{ $score->points >= 0 ? 'text-pcrm-600' : 'text-red-600' }}">
                                            {{ $score->points >= 0 ? '+' : '' }}{{ number_format($score->points) }}
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-6 text-center text-sm text-slate-400">
                            <i class="ph-coins text-2xl mb-2 block"></i>
                            <p>Chưa có ghi nhận điểm</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
