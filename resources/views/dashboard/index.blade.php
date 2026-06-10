@extends('layouts.admin')

@section('title', 'Tổng quan')

@section('page-title', 'Tổng quan')
@section('breadcrumb', 'Bảng điều khiển')

@section('content')
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="stat-card">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 rounded-lg bg-pcrm-50 dark:bg-pcrm-900/30 flex items-center justify-center">
                    <i class="ph-users-three text-xl text-pcrm-600 dark:text-pcrm-400"></i>
                </div>
            </div>
            <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ $totalEmployees ?? 0 }}</p>
            <p class="text-sm text-slate-500 dark:text-slate-400">Tổng nhân viên</p>
        </div>

        <div class="stat-card">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 rounded-lg bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center">
                    <i class="ph-trophy text-xl text-amber-600 dark:text-amber-400"></i>
                </div>
            </div>
            <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ $totalTeams ?? 0 }}</p>
            <p class="text-sm text-slate-500 dark:text-slate-400">Tổng đội nhóm</p>
        </div>

        <div class="stat-card">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 rounded-lg bg-red-50 dark:bg-red-900/30 flex items-center justify-center">
                    <i class="ph-gavel text-xl text-red-600 dark:text-red-400"></i>
                </div>
            </div>
            <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ $totalPenaltiesThisMonth ?? 0 }}</p>
            <p class="text-sm text-slate-500 dark:text-slate-400">Vi phạm tháng này</p>
        </div>

        <div class="stat-card">
            <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 rounded-lg bg-rose-50 dark:bg-rose-900/30 flex items-center justify-center">
                    <i class="ph-warning-octagon text-xl text-rose-600 dark:text-rose-400"></i>
                </div>
            </div>
            <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ $redzoneCount ?? 0 }}</p>
            <p class="text-sm text-slate-500 dark:text-slate-400">Redzone</p>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Recent Penalties -->
        <div class="card">
            <div class="card-header flex items-center justify-between">
                <h3 class="font-semibold text-slate-900 dark:text-white">Xử phạt gần đây</h3>
                @can('view-penalties')
                <a href="{{ route('penalties.index') }}" class="text-sm text-pcrm-600 dark:text-pcrm-400 hover:underline flex items-center gap-1">
                    Xem tất cả <i class="ph-arrow-right text-xs"></i>
                </a>
                @endcan
            </div>
            <div class="card-body p-0">
                @if(isset($recentPenalties) && count($recentPenalties) > 0)
                    <div class="table-container border-0 rounded-none">
                        <table class="table-base">
                            <thead>
                                <tr>
                                    <th class="table-th">Nhân viên</th>
                                    <th class="table-th">Lỗi</th>
                                    <th class="table-th">Trạng thái</th>
                                    <th class="table-th">Ngày</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentPenalties as $penalty)
                                <tr class="table-tr-hover">
                                    <td class="table-td font-medium">{{ $penalty->employee->name ?? 'N/A' }}</td>
                                    <td class="table-td max-w-[200px] truncate">{{ $penalty->violation->name ?? 'N/A' }}</td>
                                    <td class="table-td">
                                        @php
                                            $statusMap = [
                                                'pending' => ['label' => 'Chờ duyệt', 'class' => 'badge-warning'],
                                                'approved' => ['label' => 'Đã duyệt', 'class' => 'badge-success'],
                                                'rejected' => ['label' => 'Từ chối', 'class' => 'badge-danger'],
                                            ];
                                            $status = $statusMap[$penalty->status] ?? ['label' => $penalty->status, 'class' => 'badge-neutral'];
                                        @endphp
                                        <span class="{{ $status['class'] }}">{{ $status['label'] }}</span>
                                    </td>
                                    <td class="table-td text-sm text-slate-500">{{ $penalty->created_at->format('d/m/Y') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-6 text-center">
                        <div class="w-16 h-16 mx-auto mb-3 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                            <i class="ph-check-circle text-2xl text-slate-400"></i>
                        </div>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Chưa có xử phạt nào gần đây</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Redzone Widget -->
        <div class="card">
            <div class="card-header flex items-center justify-between">
                <h3 class="font-semibold text-slate-900 dark:text-white">Redzone — Nhân viên cảnh báo</h3>
                <a href="{{ route('redzone.index') }}" class="text-sm text-pcrm-600 dark:text-pcrm-400 hover:underline flex items-center gap-1">
                    Xem tất cả <i class="ph-arrow-right text-xs"></i>
                </a>
            </div>
            <div class="card-body p-0">
                @if(isset($redzoneEmployees) && count($redzoneEmployees) > 0)
                    <div class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach($redzoneEmployees as $emp)
                        <div class="flex items-center justify-between px-6 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-redzone-50 dark:bg-redzone-900/30 flex items-center justify-center text-redzone-600 dark:text-redzone-400 font-semibold text-xs">
                                    {{ strtoupper(substr($emp->name, 0, 2)) }}
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $emp->name }}</p>
                                    <p class="text-xs text-slate-400">{{ $emp->team->name ?? 'Chưa có team' }}</p>
                                </div>
                            </div>
                            <span class="badge badge-danger font-bold">{{ number_format($emp->total_score ?? 0) }}đ</span>
                        </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-6 text-center">
                        <div class="w-16 h-16 mx-auto mb-3 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                            <i class="ph-smiley text-2xl text-slate-400"></i>
                        </div>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Không có nhân viên nào trong vùng đỏ</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Bottom Section: Top Rankings -->
    <div class="card">
        <div class="card-header flex items-center justify-between">
            <h3 class="font-semibold text-slate-900 dark:text-white">Xếp hạng nhân viên xuất sắc</h3>
            <a href="{{ route('rankings.index') }}" class="text-sm text-pcrm-600 dark:text-pcrm-400 hover:underline flex items-center gap-1">
                Xem tất cả <i class="ph-arrow-right text-xs"></i>
            </a>
        </div>
        <div class="card-body p-0">
            @if(isset($topEmployees) && count($topEmployees) > 0)
                <div class="table-container border-0 rounded-none">
                    <table class="table-base">
                        <thead>
                            <tr>
                                <th class="table-th w-12">#</th>
                                <th class="table-th">Nhân viên</th>
                                <th class="table-th">Đội nhóm</th>
                                <th class="table-th text-right">Điểm</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($topEmployees as $index => $emp)
                            <tr class="table-tr-hover">
                                <td class="table-td">
                                    @if($index < 3)
                                        <span class="rank-badge rank-{{ $index + 1 }}">
                                            <i class="ph-trophy {{ $index === 0 ? 'text-rank-500' : ($index === 1 ? 'text-slate-400' : 'text-amber-500') }}"></i>
                                        </span>
                                    @else
                                        <span class="text-sm font-medium text-slate-500 dark:text-slate-400 ml-2">{{ $index + 1 }}</span>
                                    @endif
                                </td>
                                <td class="table-td font-medium">{{ $emp->name }}</td>
                                <td class="table-td text-slate-500">{{ $emp->team->name ?? '—' }}</td>
                                <td class="table-td text-right font-semibold text-pcrm-600 dark:text-pcrm-400">{{ number_format($emp->total_score ?? 0) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-6 text-center">
                    <div class="w-16 h-16 mx-auto mb-3 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                        <i class="ph-trophy text-2xl text-slate-400"></i>
                    </div>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Chưa có dữ liệu xếp hạng</p>
                </div>
            @endif
        </div>
    </div>
@endsection
