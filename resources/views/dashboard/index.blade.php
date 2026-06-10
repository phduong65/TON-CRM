@extends('layouts.admin')

@section('title', 'Tổng quan')

@section('page-title', 'Tổng quan')
@section('breadcrumb', 'Bảng điều khiển')

@push('styles')
    <style>
        .stat-icon-wrap {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        @if ($isAdmin)
            .trend-up {
                color: #10b981;
            }

            .trend-down {
                color: #f43f5e;
            }

            .chart-container {
                position: relative;
            }
        @endif
    </style>
@endpush

@section('content')

    @if ($isAdmin)

        {{-- ─── Row 1: Stat Cards ─────────────────────────────────────────────── --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

            {{-- Nhân viên --}}
            <div class="stat-card flex flex-col gap-3">
                <div class="flex items-start justify-between">
                    <div class="stat-icon-wrap bg-pcrm-50 dark:bg-pcrm-900/30">
                        <i class="bi bi-people-fill text-2xl text-pcrm-600 dark:text-pcrm-400"></i>
                    </div>
                    <span
                        class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full bg-pcrm-50 dark:bg-pcrm-900/30 text-pcrm-600 dark:text-pcrm-400">
                        <i class="bi bi-building text-xs"></i> {{ $totalBranches ?? 0 }} CN
                    </span>
                </div>
                <div>
                    <p class="text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                        {{ $totalEmployees ?? 0 }}</p>
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mt-0.5">Tổng nhân viên</p>
                </div>
                <div class="flex items-center gap-1.5 text-xs text-slate-400">
                    <i class="bi bi-person-badge-fill text-sm"></i>
                    <span>{{ $activeEmployees ?? ($totalEmployees ?? 0) }} đang hoạt động</span>
                </div>
            </div>

            {{-- Đội nhóm --}}
            <div class="stat-card flex flex-col gap-3">
                <div class="flex items-start justify-between">
                    <div class="stat-icon-wrap bg-amber-50 dark:bg-amber-900/30">
                        <i class="bi bi-trophy-fill text-2xl text-amber-600 dark:text-amber-400"></i>
                    </div>
                    <span
                        class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400">
                        <i class="bi bi-star-fill text-xs"></i> {{ $totalViolations ?? 0 }} loại lỗi
                    </span>
                </div>
                <div>
                    <p class="text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ $totalTeams ?? 0 }}
                    </p>
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mt-0.5">Tổng đội nhóm</p>
                </div>
                <div class="flex items-center gap-1.5 text-xs text-slate-400">
                    <i class="bi bi-diagram-3-fill text-sm"></i>
                    <span>Bar · Service · Kitchen · Security</span>
                </div>
            </div>

            {{-- Vi phạm tháng này --}}
            <div class="stat-card flex flex-col gap-3">
                <div class="flex items-start justify-between">
                    <div class="stat-icon-wrap bg-red-50 dark:bg-red-900/30">
                        <i class="bi bi-hammer text-2xl text-red-600 dark:text-red-400"></i>
                    </div>
                    @php
                        $penaltyDiff = ($totalPenaltiesThisMonth ?? 0) - ($totalPenaltiesLastMonth ?? 0);
                    @endphp
                    @if (isset($totalPenaltiesLastMonth))
                        <span
                            class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full
                        {{ $penaltyDiff > 0 ? 'bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400' : 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400' }}">
                            <i class="bi {{ $penaltyDiff > 0 ? 'bi-graph-up-arrow' : 'bi-graph-down-arrow' }} text-xs"></i>
                            {{ $penaltyDiff > 0 ? '+' : '' }}{{ $penaltyDiff }} so với T.trước
                        </span>
                    @endif
                </div>
                <div>
                    <p class="text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                        {{ $totalPenaltiesThisMonth ?? 0 }}</p>
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mt-0.5">Vi phạm tháng này</p>
                </div>
                <div class="flex items-center gap-3 text-xs">
                    <span class="inline-flex items-center gap-1 text-amber-500">
                        <i class="bi bi-hourglass-split text-sm"></i> {{ $pendingPenalties ?? 0 }} chờ duyệt
                    </span>
                    <span class="inline-flex items-center gap-1 text-emerald-500">
                        <i class="bi bi-check-circle-fill text-sm"></i> {{ $approvedPenalties ?? 0 }} đã duyệt
                    </span>
                </div>
            </div>

            {{-- Redzone --}}
            <div class="stat-card flex flex-col gap-3">
                <div class="flex items-start justify-between">
                    <div class="stat-icon-wrap bg-rose-50 dark:bg-rose-900/30">
                        <i class="bi bi-exclamation-octagon-fill text-2xl text-rose-600 dark:text-rose-400"></i>
                    </div>
                    @if (($redzoneCount ?? 0) > 0)
                        <span
                            class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full bg-rose-50 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400 animate-pulse">
                            <i class="bi bi-record-circle-fill text-xs"></i> Cảnh báo
                        </span>
                    @else
                        <span
                            class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400">
                            <i class="bi bi-shield-check text-xs"></i> An toàn
                        </span>
                    @endif
                </div>
                <div>
                    <p class="text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                        {{ $redzoneCount ?? 0 }}</p>
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mt-0.5">Nhân viên Redzone</p>
                </div>
                <div class="flex items-center gap-1.5 text-xs text-slate-400">
                    <i class="bi bi-speedometer2 text-sm"></i>
                    <span>Ngưỡng ≤ {{ $redzoneThreshold ?? 50 }} điểm</span>
                </div>
            </div>
        </div>

        {{-- ─── Row 2: Charts ──────────────────────────────────────────────────── --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

            {{-- Penalty Trend Chart (2/3 width) --}}
            <div class="card lg:col-span-2">
                <div class="card-header flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-pcrm-50 dark:bg-pcrm-900/30 flex items-center justify-center">
                            <i class="bi bi-bar-chart-fill text-pcrm-600 dark:text-pcrm-400"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-slate-900 dark:text-white text-sm">Xu hướng vi phạm</h3>
                            <p class="text-xs text-slate-400">6 tháng gần nhất</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1 text-xs text-slate-400">
                            <span class="w-3 h-1.5 rounded-full bg-pcrm-500 inline-block"></span> Vi phạm
                        </span>
                        <span class="inline-flex items-center gap-1 text-xs text-slate-400">
                            <span class="w-3 h-1.5 rounded-full bg-emerald-500 inline-block"></span> Đã duyệt
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="height: 220px;">
                        <canvas id="penaltyTrendChart"></canvas>
                    </div>
                </div>
            </div>

            {{-- Violations Distribution Chart (1/3 width) --}}
            <div class="card">
                <div class="card-header flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center">
                        <i class="bi bi-pie-chart-fill text-amber-600 dark:text-amber-400"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-slate-900 dark:text-white text-sm">Phân bổ vi phạm</h3>
                        <p class="text-xs text-slate-400">Theo loại lỗi</p>
                    </div>
                </div>
                <div class="card-body flex flex-col items-center">
                    <div class="chart-container" style="height: 160px; width: 160px;">
                        <canvas id="violationDistChart"></canvas>
                    </div>
                    <div class="mt-4 w-full space-y-2" id="violationLegend"></div>
                </div>
            </div>
        </div>

        {{-- ─── Row 3: Recent Penalties + Redzone ─────────────────────────────── --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

            {{-- Recent Penalties --}}
            <div class="card">
                <div class="card-header flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i class="bi bi-file-text-fill text-slate-500 dark:text-slate-400"></i>
                        <h3 class="font-semibold text-slate-900 dark:text-white">Xử phạt gần đây</h3>
                    </div>
                    @can('view-penalties')
                        <a href="{{ route('penalties.index') }}"
                            class="text-sm text-pcrm-600 dark:text-pcrm-400 hover:underline flex items-center gap-1">
                            Xem tất cả <i class="bi bi-arrow-right text-xs"></i>
                        </a>
                    @endcan
                </div>
                <div class="card-body p-0">
                    @if (isset($recentPenalties) && count($recentPenalties) > 0)
                        <div class="table-container border-0 rounded-none">
                            <table class="table-base">
                                <thead>
                                    <tr>
                                        <th class="table-th">Nhân viên</th>
                                        <th class="table-th">Lỗi vi phạm</th>
                                        <th class="table-th">Trạng thái</th>
                                        <th class="table-th">Ngày</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentPenalties as $penalty)
                                        <tr class="table-tr-hover">
                                            <td class="table-td">
                                                <div class="flex items-center gap-2">
                                                    <div
                                                        class="w-7 h-7 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-xs font-semibold text-slate-600 dark:text-slate-300">
                                                        {{ strtoupper(substr($penalty->employee->name ?? 'N', 0, 1)) }}
                                                    </div>
                                                    <span
                                                        class="font-medium text-sm">{{ $penalty->employee->name ?? 'N/A' }}</span>
                                                </div>
                                            </td>
                                            <td class="table-td max-w-[160px] truncate text-sm">
                                                {{ $penalty->violation->name ?? 'N/A' }}</td>
                                            <td class="table-td">
                                                @php
                                                    $statusMap = [
                                                        'pending' => [
                                                            'label' => 'Chờ duyệt',
                                                            'class' => 'badge-warning',
                                                        ],
                                                        'approved' => [
                                                            'label' => 'Đã duyệt',
                                                            'class' => 'badge-success',
                                                        ],
                                                        'rejected' => ['label' => 'Từ chối', 'class' => 'badge-danger'],
                                                    ];
                                                    $status = $statusMap[$penalty->status] ?? [
                                                        'label' => $penalty->status,
                                                        'class' => 'badge-neutral',
                                                    ];
                                                @endphp
                                                <span class="{{ $status['class'] }}">{{ $status['label'] }}</span>
                                            </td>
                                            <td class="table-td text-sm text-slate-500">
                                                {{ $penalty->created_at->format('d/m') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-8 text-center">
                            <div
                                class="w-14 h-14 mx-auto mb-3 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                                <i class="bi bi-check-circle-fill text-2xl text-slate-400"></i>
                            </div>
                            <p class="text-sm text-slate-500 dark:text-slate-400">Chưa có xử phạt nào gần đây</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Redzone Widget --}}
            <div class="card">
                <div class="card-header flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i class="bi bi-exclamation-triangle-fill text-rose-500"></i>
                        <h3 class="font-semibold text-slate-900 dark:text-white">Redzone — Cảnh báo</h3>
                    </div>
                    <a href="{{ route('redzone.index') }}"
                        class="text-sm text-pcrm-600 dark:text-pcrm-400 hover:underline flex items-center gap-1">
                        Xem tất cả <i class="bi bi-arrow-right text-xs"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    @if (isset($redzoneEmployees) && count($redzoneEmployees) > 0)
                        <div class="divide-y divide-slate-100 dark:divide-slate-700">
                            @foreach ($redzoneEmployees as $emp)
                                <div
                                    class="flex items-center justify-between px-5 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-9 h-9 rounded-full bg-rose-50 dark:bg-rose-900/30 flex items-center justify-center text-rose-600 dark:text-rose-400 font-bold text-xs ring-2 ring-rose-100 dark:ring-rose-900/50">
                                            {{ strtoupper(substr($emp->name, 0, 2)) }}
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-slate-900 dark:text-white">
                                                {{ $emp->name }}</p>
                                            <p class="text-xs text-slate-400 flex items-center gap-1">
                                                <i class="bi bi-people text-xs"></i>
                                                {{ $emp->team->name ?? 'Chưa có team' }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="w-16 h-1.5 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                                            <div class="h-full rounded-full bg-rose-500"
                                                style="width: {{ min(100, max(0, $emp->total_score ?? 0)) }}%"></div>
                                        </div>
                                        <span
                                            class="badge badge-danger font-bold text-xs min-w-[44px] text-center">{{ number_format($emp->total_score ?? 0) }}đ</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="p-8 text-center">
                            <div
                                class="w-14 h-14 mx-auto mb-3 rounded-full bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center">
                                <i class="bi bi-shield-fill-check text-2xl text-emerald-500"></i>
                            </div>
                            <p class="text-sm font-medium text-emerald-600 dark:text-emerald-400">Không có nhân viên trong
                                vùng đỏ</p>
                            <p class="text-xs text-slate-400 mt-1">Tất cả đang ở trạng thái tốt</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ─── Row 4: Top Rankings ─────────────────────────────────────────────── --}}
        <div class="card">
            <div class="card-header flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="bi bi-award-fill text-amber-500 text-lg"></i>
                    <h3 class="font-semibold text-slate-900 dark:text-white">Nhân viên xuất sắc tháng này</h3>
                </div>
                <a href="{{ route('rankings.index') }}"
                    class="text-sm text-pcrm-600 dark:text-pcrm-400 hover:underline flex items-center gap-1">
                    Xem bảng xếp hạng <i class="bi bi-arrow-right text-xs"></i>
                </a>
            </div>
            <div class="card-body p-0">
                @if (isset($topEmployees) && count($topEmployees) > 0)
                    <div class="table-container border-0 rounded-none">
                        <table class="table-base">
                            <thead>
                                <tr>
                                    <th class="table-th w-12">#</th>
                                    <th class="table-th">Nhân viên</th>
                                    <th class="table-th">Đội nhóm</th>
                                    <th class="table-th">Chi nhánh</th>
                                    <th class="table-th text-right">Điểm</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($topEmployees as $index => $emp)
                                    <tr
                                        class="table-tr-hover {{ $index < 3 ? 'bg-amber-50/30 dark:bg-amber-900/5' : '' }}">
                                        <td class="table-td text-center">
                                            @if ($index === 0)
                                                <span
                                                    class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-amber-100 dark:bg-amber-900/40">
                                                    <i class="bi bi-trophy-fill text-amber-500 text-base"></i>
                                                </span>
                                            @elseif($index === 1)
                                                <span
                                                    class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-slate-100 dark:bg-slate-700">
                                                    <i class="bi bi-trophy text-slate-400 text-base"></i>
                                                </span>
                                            @elseif($index === 2)
                                                <span
                                                    class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-orange-100 dark:bg-orange-900/40">
                                                    <i class="bi bi-trophy text-orange-400 text-base"></i>
                                                </span>
                                            @else
                                                <span
                                                    class="text-sm font-medium text-slate-400 dark:text-slate-500">{{ $index + 1 }}</span>
                                            @endif
                                        </td>
                                        <td class="table-td">
                                            <div class="flex items-center gap-2">
                                                <div
                                                    class="w-7 h-7 rounded-full bg-pcrm-100 dark:bg-pcrm-900/30 flex items-center justify-center text-xs font-semibold text-pcrm-600 dark:text-pcrm-400">
                                                    {{ strtoupper(substr($emp->name, 0, 1)) }}
                                                </div>
                                                <span
                                                    class="font-medium text-sm text-slate-900 dark:text-white">{{ $emp->name }}</span>
                                            </div>
                                        </td>
                                        <td class="table-td text-sm text-slate-500">{{ $emp->team->name ?? '—' }}</td>
                                        <td class="table-td text-sm text-slate-500">{{ $emp->branch->name ?? '—' }}</td>
                                        <td class="table-td text-right">
                                            <span
                                                class="font-bold text-pcrm-600 dark:text-pcrm-400">{{ number_format($emp->total_score ?? 0) }}</span>
                                            <span class="text-xs text-slate-400 ml-0.5">pts</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-8 text-center">
                        <div
                            class="w-14 h-14 mx-auto mb-3 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                            <i class="bi bi-trophy text-2xl text-slate-400"></i>
                        </div>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Chưa có dữ liệu xếp hạng</p>
                    </div>
                @endif
            </div>
        </div>
    @else
        {{-- ══════════════════════════════════════════════════════════════════════ --}}
        {{-- PERSONAL DASHBOARD — non-admin                                        --}}
        {{-- ══════════════════════════════════════════════════════════════════════ --}}

        @php
            $now = \Carbon\Carbon::now('Asia/Ho_Chi_Minh');
            $hour = $now->hour;
            $greeting = match (true) {
                $hour >= 5 && $hour < 11 => 'Chào buổi sáng',
                $hour >= 11 && $hour < 13 => 'Chào buổi trưa',
                $hour >= 13 && $hour < 18 => 'Chào buổi chiều',
                default => 'Chào buổi tối',
            };
            $greetIcon = match (true) {
                $hour >= 5 && $hour < 11 => 'bi-sun-fill text-amber-300',
                $hour >= 11 && $hour < 13 => 'bi-brightness-high-fill text-yellow-200',
                $hour >= 13 && $hour < 18 => 'bi-cloud-sun-fill text-sky-200',
                default => 'bi-moon-stars-fill text-indigo-200',
            };
            $userInitials = strtoupper(mb_substr(auth()->user()->name, 0, 2));
            $roleName = ucfirst(auth()->user()->getRoleNames()->first() ?? 'Nhân viên');
            $days = ['Chủ nhật', 'Thứ hai', 'Thứ ba', 'Thứ tư', 'Thứ năm', 'Thứ sáu', 'Thứ bảy'];
            $dateLabel = $days[$now->dayOfWeek] . ', ' . $now->format('d/m/Y');
        @endphp

        {{-- ─── Hero Welcome Banner ────────────────────────────────────────────── --}}
        <div class="rounded-2xl overflow-hidden mb-6 shadow-lg dash-hero"
            style="background: linear-gradient(135deg, #059669 0%, #047857 45%, #064e3b 100%);">
            <div class="relative px-6 py-8 md:px-10 md:py-10 overflow-hidden">

                {{-- Decorative blobs --}}
                <div class="absolute inset-0 pointer-events-none">
                    <div class="absolute -top-14 -right-14 w-60 h-60 rounded-full"
                        style="background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%)"></div>
                    <div class="absolute -bottom-12 left-1/4 w-48 h-48 rounded-full"
                        style="background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%)"></div>
                    <div class="absolute top-1/2 right-1/3 w-28 h-28 rounded-full"
                        style="background: radial-gradient(circle, rgba(255,255,255,0.06) 0%, transparent 70%)"></div>
                </div>

                <div class="relative flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6">

                    {{-- Left: text info --}}
                    <div class="flex-1 min-w-0">
                        <p class="text-white/60 text-sm font-medium mb-1 flex items-center gap-1.5">
                            <i class="bi {{ $greetIcon }}"></i>
                            {{ $greeting }},
                        </p>
                        <h1 class="text-white text-2xl md:text-3xl font-black tracking-tight truncate leading-snug">
                            {{ auth()->user()->name }}
                        </h1>

                        <div class="flex flex-wrap items-center gap-2 mt-3">
                            <span
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium text-white/90"
                                style="background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.2);">
                                <i class="bi bi-shield-fill text-xs" style="color:rgba(255,255,255,0.7)"></i>
                                {{ $roleName }}
                            </span>
                            @if ($employee?->team)
                                <span
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium text-white/90"
                                    style="background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.2);">
                                    <i class="bi bi-people-fill text-xs" style="color:rgba(255,255,255,0.7)"></i>
                                    {{ $employee->team->name }}
                                </span>
                            @endif
                            @if ($employee?->position)
                                <span
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium text-white/90"
                                    style="background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.2);">
                                    <i class="bi bi-briefcase-fill text-xs" style="color:rgba(255,255,255,0.7)"></i>
                                    {{ $employee->position }}
                                </span>
                            @endif
                            @if ($employee?->branch)
                                <span
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium text-white/90"
                                    style="background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.2);">
                                    <i class="bi bi-building text-xs" style="color:rgba(255,255,255,0.7)"></i>
                                    {{ $employee->branch->name }}
                                </span>
                            @endif
                        </div>

                        <p class="mt-4 flex items-center gap-1.5 text-xs" style="color:rgba(255,255,255,0.45)">
                            <i class="bi bi-calendar3"></i> {{ $dateLabel }}
                        </p>
                    </div>

                    {{-- Right: avatar + status --}}
                    <div class="flex flex-col items-end gap-5 flex-shrink-0">


                        {{-- Avatar circle --}}
                        <div class="flex flex-row justify-end items-center gap-2">
                            @if ($employee)
                                <div class="w-32 h-32 p-3 rounded-full dash-avatar flex items-center justify-center font-black text-xl md:text-2xl text-white select-none"
                                    style="background:rgba(255,255,255,0.2);border:3px solid rgba(255,255,255,0.45);">
                                    <div class="text-center">
                                        <p class="text-white font-black text-2xl leading-none tracking-tight">
                                            {{ number_format($myTotalScore) }}</p>
                                        <p class="text-xs" style="color:rgba(255,255,255,0.6)">điểm</p>
                                         {{-- Status badge --}}
                        @if ($employee)
                            <div class="hidden sm:flex flex-col items-center gap-2 self-center">
                                @if ($isInRedzone)
                                    <div class="flex flex-col items-center gap-1.5">
                                        <span
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-white text-xs font-bold dash-redzone-blink"
                                            style="background:rgba(244,63,94,0.85);border:1px solid rgba(251,113,133,0.5);">
                                            <i class="bi bi-exclamation-octagon-fill text-xs"></i> Redzone
                                        </span>
                                        <span class="text-xs text-center" style="color:rgba(255,255,255,0.55)">Cần cải
                                            thiện</span>
                                    </div>
                                @else
                                    <div class="flex flex-col items-center gap-1.5">
                                        <span
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-white text-xs font-bold"
                                            style="background:rgba(16,185,129,0.75);border:1px solid rgba(52,211,153,0.5);">
                                            <i class="bi bi-shield-fill-check text-xs"></i> An toàn
                                        </span>
                                        <span class="text-xs text-center" style="color:rgba(255,255,255,0.55)">Trạng thái
                                            tốt</span>
                                    </div>
                                @endif
                            </div>
                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>



                    </div>
                </div>
            </div>
        </div>

        @if ($employee)

            {{-- ─── Personal Stat Cards ──────────────────────────────────────────── --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">

                {{-- Điểm của tôi --}}
                <div class="stat-card flex flex-col gap-3 dash-card-1">
                    <div class="flex items-start justify-between">
                        <div
                            class="stat-icon-wrap {{ $isInRedzone ? 'bg-rose-50 dark:bg-rose-900/30' : 'bg-emerald-50 dark:bg-emerald-900/30' }}">
                            <i
                                class="bi bi-star-fill text-2xl {{ $isInRedzone ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}"></i>
                        </div>
                        <span
                            class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full
                        {{ $isInRedzone ? 'bg-rose-50 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400' : 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400' }}">
                            <i
                                class="bi {{ $isInRedzone ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill' }} text-xs"></i>
                            {{ $isInRedzone ? 'Redzone' : 'An toàn' }}
                        </span>
                    </div>
                    <div>
                        <p class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                            {{ number_format($myTotalScore) }}</p>
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mt-0.5">Tổng điểm của tôi</p>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between text-xs text-slate-400">
                            <span>0</span>
                            <span class="text-slate-500">Ngưỡng: {{ $redzoneThreshold }}</span>
                            <span>100</span>
                        </div>
                        <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                            <div class="h-full rounded-full dash-score-bar {{ $isInRedzone ? 'bg-rose-500' : 'bg-emerald-500' }}"
                                style="width: {{ min(100, max(0, $myTotalScore)) }}%"></div>
                        </div>
                    </div>
                </div>

                {{-- Vi phạm tháng này --}}
                <div class="stat-card flex flex-col gap-3 dash-card-2">
                    <div class="flex items-start justify-between">
                        <div
                            class="stat-icon-wrap {{ $myPenaltiesCount > 0 ? 'bg-amber-50 dark:bg-amber-900/30' : 'bg-slate-50 dark:bg-slate-800' }}">
                            <i
                                class="bi bi-hammer text-2xl {{ $myPenaltiesCount > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400' }}"></i>
                        </div>
                        <span
                            class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400">
                            T.{{ $now->month }}/{{ $now->year }}
                        </span>
                    </div>
                    <div>
                        <p class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                            {{ $myPenaltiesCount }}</p>
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mt-0.5">Vi phạm của tôi</p>
                    </div>
                    <div class="flex items-center gap-1.5 text-xs text-slate-400">
                        <i class="bi bi-calendar-check text-sm"></i>
                        <span>{{ $myPenaltiesCount === 0 ? 'Không có vi phạm tháng này' : 'Vi phạm trong tháng' }}</span>
                    </div>
                </div>

                {{-- Xếp hạng --}}
                <div class="stat-card flex flex-col gap-3 dash-card-3">
                    <div class="flex items-start justify-between">
                        <div class="stat-icon-wrap bg-pcrm-50 dark:bg-pcrm-900/30">
                            <i class="bi bi-trophy-fill text-2xl text-pcrm-600 dark:text-pcrm-400"></i>
                        </div>
                        @if ($myRank && $myRank <= 3)
                            <span
                                class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400">
                                <i class="bi bi-star-fill text-xs"></i> Top {{ $myRank }}
                            </span>
                        @else
                            <span
                                class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400">
                                / {{ $totalEmployees }} NV
                            </span>
                        @endif
                    </div>
                    <div>
                        <p class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                            {{ $myRank ? '#' . $myRank : '—' }}
                        </p>
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mt-0.5">Xếp hạng toàn công ty</p>
                    </div>
                    <div class="flex items-center gap-1.5 text-xs text-slate-400">
                        <i class="bi bi-people-fill text-sm"></i>
                        <span>Trong số {{ $totalEmployees }} nhân viên</span>
                    </div>
                </div>
            </div>

            {{-- ─── Recent Penalties + Team Leaderboard ────────────────────────── --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                {{-- Vi phạm gần đây của tôi --}}
                <div class="card dash-sect-l">
                    <div class="card-header flex items-center gap-2">
                        <i class="bi bi-file-earmark-text-fill text-slate-500 dark:text-slate-400"></i>
                        <h3 class="font-semibold text-slate-900 dark:text-white">Vi phạm gần đây của tôi</h3>
                    </div>
                    <div class="card-body p-0">
                        @if ($myRecentPenalties->count() > 0)
                            <div class="table-container border-0 rounded-none">
                                <table class="table-base">
                                    <thead>
                                        <tr>
                                            <th class="table-th">Lỗi vi phạm</th>
                                            <th class="table-th text-center">Trừ điểm</th>
                                            <th class="table-th">Trạng thái</th>
                                            <th class="table-th">Ngày</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($myRecentPenalties as $penalty)
                                            <tr class="table-tr-hover">
                                                <td
                                                    class="table-td max-w-[160px] truncate text-sm font-medium text-slate-900 dark:text-white">
                                                    {{ $penalty->violation->name ?? 'N/A' }}
                                                </td>
                                                <td class="table-td text-center">
                                                    <span class="text-rose-600 dark:text-rose-400 font-bold text-sm">
                                                        -{{ $penalty->violation->points_deducted ?? 0 }}
                                                    </span>
                                                </td>
                                                <td class="table-td">
                                                    @php
                                                        $statusMap = [
                                                            'pending' => [
                                                                'label' => 'Chờ duyệt',
                                                                'class' => 'badge-warning',
                                                            ],
                                                            'approved' => [
                                                                'label' => 'Đã duyệt',
                                                                'class' => 'badge-success',
                                                            ],
                                                            'rejected' => [
                                                                'label' => 'Từ chối',
                                                                'class' => 'badge-danger',
                                                            ],
                                                        ];
                                                        $st = $statusMap[$penalty->status] ?? [
                                                            'label' => $penalty->status,
                                                            'class' => 'badge-neutral',
                                                        ];
                                                    @endphp
                                                    <span class="{{ $st['class'] }}">{{ $st['label'] }}</span>
                                                </td>
                                                <td class="table-td text-sm text-slate-500">
                                                    {{ $penalty->created_at->format('d/m') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="p-10 text-center">
                                <div
                                    class="w-14 h-14 mx-auto mb-3 rounded-full bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center">
                                    <i class="bi bi-check-circle-fill text-2xl text-emerald-500"></i>
                                </div>
                                <p class="text-sm font-medium text-emerald-600 dark:text-emerald-400">Không có vi phạm nào
                                </p>
                                <p class="text-xs text-slate-400 mt-1">Tiếp tục duy trì phong độ tốt!</p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Bảng xếp hạng đội --}}
                <div class="card dash-sect-r">
                    <div class="card-header flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="bi bi-bar-chart-steps text-amber-500"></i>
                            <h3 class="font-semibold text-slate-900 dark:text-white">
                                Xếp hạng — {{ $employee->team->name ?? 'Đội của tôi' }}
                            </h3>
                        </div>
                        <a href="{{ route('rankings.index') }}"
                            class="text-sm text-pcrm-600 dark:text-pcrm-400 hover:underline flex items-center gap-1">
                            Xem tất cả <i class="bi bi-arrow-right text-xs"></i>
                        </a>
                    </div>
                    <div class="card-body">
                        @if ($teamLeaderboard->count() > 0)
                            <div class="leaderboard-list">
                                @foreach ($teamLeaderboard as $idx => $member)
                                    @php $isMe = $member->id === $employee->id; @endphp
                                    <div class="leaderboard-card {{ $idx < 3 ? 'leaderboard-card-top' : '' }}"
                                        style="animation-delay:{{ 0.35 + $idx * 0.07 }}s;
                                        {{ $isMe ? 'outline:2px solid var(--color-pcrm-500);outline-offset:-2px;border-radius:0.75rem;' : '' }}">

                                        {{-- Rank --}}
                                        <div class="leaderboard-card-rank leaderboard-medal">
                                            @if ($idx === 0)
                                                <i class="bi bi-trophy-fill text-amber-500 text-base"></i>
                                            @elseif($idx === 1)
                                                <i class="bi bi-trophy text-slate-400 text-base"></i>
                                            @elseif($idx === 2)
                                                <i class="bi bi-trophy text-orange-400 text-base"></i>
                                            @else
                                                <span
                                                    class="text-sm text-slate-400 font-medium">{{ $idx + 1 }}</span>
                                            @endif
                                        </div>

                                        {{-- Avatar --}}
                                        <div class="w-9 h-9 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-bold"
                                            style="{{ $isMe
                                                ? 'background-color:var(--color-pcrm-100);color:var(--color-pcrm-700);outline:2px solid var(--color-pcrm-400);'
                                                : 'background-color:rgb(241 245 249);color:rgb(71 85 105);' }}">
                                            {{ strtoupper(mb_substr($member->name, 0, 2)) }}
                                        </div>

                                        {{-- Name --}}
                                        <div class="flex-1 min-w-0">
                                            <p
                                                class="text-sm font-semibold truncate {{ $isMe ? 'text-pcrm-700 dark:text-pcrm-300' : 'text-slate-800 dark:text-slate-200' }}">
                                                {{ $member->name }}
                                                @if ($isMe)
                                                    <span
                                                        class="text-xs font-normal ml-1 text-pcrm-500 dark:text-pcrm-400">(bạn)</span>
                                                @endif
                                            </p>
                                            <p class="text-xs text-slate-400 truncate">{{ $member->position ?? '—' }}</p>
                                        </div>

                                        {{-- Score --}}
                                        <div class="leaderboard-card-score">
                                            <p
                                                class="font-extrabold text-sm {{ $isMe ? 'text-pcrm-600 dark:text-pcrm-400' : 'text-slate-700 dark:text-slate-300' }}">
                                                {{ number_format($member->total_score) }}
                                            </p>
                                            <p class="text-xs text-slate-400">pts</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="py-10 text-center">
                                <div
                                    class="w-14 h-14 mx-auto mb-3 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                                    <i class="bi bi-people text-2xl text-slate-400"></i>
                                </div>
                                <p class="text-sm text-slate-500 dark:text-slate-400">Chưa được phân công vào đội nhóm</p>
                            </div>
                        @endif
                    </div>
                </div>

            </div>
        @else
            {{-- Tài khoản chưa liên kết nhân viên --}}
            <div class="card">
                <div class="card-body py-14 text-center">
                    <div
                        class="w-16 h-16 mx-auto mb-4 rounded-full bg-amber-50 dark:bg-amber-900/20 flex items-center justify-center">
                        <i class="bi bi-person-x text-3xl text-amber-500"></i>
                    </div>
                    <h3 class="font-semibold text-slate-900 dark:text-white mb-1.5">Tài khoản chưa liên kết</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 max-w-sm mx-auto">
                        Tài khoản của bạn chưa được liên kết với hồ sơ nhân viên.<br>
                        Vui lòng liên hệ quản trị viên để được hỗ trợ.
                    </p>
                </div>
            </div>

        @endif

    @endif

@endsection

@if ($isAdmin)
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
        <script>
            (function() {
                var isDark = document.documentElement.classList.contains('dark');

                // ── Color palette ──────────────────────────────────────────────────────
                var colors = {
                    primary: isDark ? 'rgba(99,102,241,0.9)' : 'rgba(79,70,229,0.85)',
                    primaryLight: isDark ? 'rgba(99,102,241,0.15)' : 'rgba(79,70,229,0.1)',
                    success: isDark ? 'rgba(52,211,153,0.85)' : 'rgba(16,185,129,0.85)',
                    successLight: isDark ? 'rgba(52,211,153,0.1)' : 'rgba(16,185,129,0.08)',
                    grid: isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.05)',
                    tick: isDark ? '#94a3b8' : '#64748b',
                    tooltip: {
                        bg: isDark ? '#1e293b' : '#ffffff',
                        border: isDark ? '#334155' : '#e2e8f0',
                        text: isDark ? '#f1f5f9' : '#0f172a',
                    }
                };

                var pieColors = [
                    '#6366f1', '#f59e0b', '#10b981', '#f43f5e',
                    '#8b5cf6', '#06b6d4', '#84cc16', '#ec4899'
                ];

                // ── Data from server (fallback to demo if not provided) ───────────────
                var trendData = @json($penaltyTrend ?? null);
                var distData = @json($violationDist ?? null);

                // Demo data when controller doesn't pass chart data yet
                if (!trendData) {
                    var months = [];
                    for (var i = 5; i >= 0; i--) {
                        var d = new Date();
                        d.setMonth(d.getMonth() - i);
                        months.push(d.toLocaleDateString('vi-VN', {
                            month: 'short',
                            year: '2-digit'
                        }));
                    }
                    trendData = {
                        labels: months,
                        total: [0, 0, 0, 0, 0, 0],
                        approved: [0, 0, 0, 0, 0, 0],
                    };
                }
                if (!distData) {
                    distData = {
                        labels: ['Không có dữ liệu'],
                        values: [1]
                    };
                }

                // ── Chart defaults ─────────────────────────────────────────────────────
                Chart.defaults.font.family = "'Be Vietnam Pro', sans-serif";
                Chart.defaults.font.size = 12;

                var tooltipPlugin = {
                    backgroundColor: colors.tooltip.bg,
                    borderColor: colors.tooltip.border,
                    borderWidth: 1,
                    titleColor: colors.tooltip.text,
                    bodyColor: colors.tooltip.text,
                    cornerRadius: 8,
                    padding: 10,
                    boxPadding: 4,
                };

                // ── Chart 1: Penalty Trend (bar + line) ───────────────────────────────
                var ctx1 = document.getElementById('penaltyTrendChart');
                if (ctx1) {
                    new Chart(ctx1, {
                        data: {
                            labels: trendData.labels,
                            datasets: [{
                                    type: 'bar',
                                    label: 'Tổng vi phạm',
                                    data: trendData.total,
                                    backgroundColor: colors.primary,
                                    borderRadius: 6,
                                    borderSkipped: false,
                                    order: 2,
                                },
                                {
                                    type: 'line',
                                    label: 'Đã duyệt',
                                    data: trendData.approved,
                                    borderColor: colors.success,
                                    backgroundColor: colors.successLight,
                                    borderWidth: 2.5,
                                    pointRadius: 4,
                                    pointBackgroundColor: colors.success,
                                    tension: 0.4,
                                    fill: true,
                                    order: 1,
                                },
                            ],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: {
                                mode: 'index',
                                intersect: false
                            },
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: tooltipPlugin,
                            },
                            scales: {
                                x: {
                                    grid: {
                                        display: false
                                    },
                                    ticks: {
                                        color: colors.tick
                                    },
                                    border: {
                                        display: false
                                    },
                                },
                                y: {
                                    beginAtZero: true,
                                    grid: {
                                        color: colors.grid
                                    },
                                    ticks: {
                                        color: colors.tick,
                                        stepSize: 1,
                                        precision: 0,
                                    },
                                    border: {
                                        display: false,
                                        dash: [4, 4]
                                    },
                                },
                            },
                        },
                    });
                }

                // ── Chart 2: Violation Distribution (doughnut) ───────────────────────
                var ctx2 = document.getElementById('violationDistChart');
                if (ctx2) {
                    var usedColors = distData.labels.map(function(_, i) {
                        return pieColors[i % pieColors.length];
                    });

                    new Chart(ctx2, {
                        type: 'doughnut',
                        data: {
                            labels: distData.labels,
                            datasets: [{
                                data: distData.values,
                                backgroundColor: usedColors,
                                borderWidth: 2,
                                borderColor: isDark ? '#1e293b' : '#ffffff',
                                hoverOffset: 6,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '68%',
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: tooltipPlugin,
                            },
                        },
                    });

                    // Custom legend
                    var legendEl = document.getElementById('violationLegend');
                    if (legendEl) {
                        distData.labels.forEach(function(label, i) {
                            var total = distData.values.reduce(function(a, b) {
                                return a + b;
                            }, 0);
                            var pct = total > 0 ? Math.round(distData.values[i] / total * 100) : 0;
                            var item = document.createElement('div');
                            item.className = 'flex items-center justify-between gap-2';
                            item.innerHTML =
                                '<div class="flex items-center gap-2 min-w-0">' +
                                '<span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:' +
                                usedColors[i] + '"></span>' +
                                '<span class="text-xs text-slate-600 dark:text-slate-400 truncate">' + label +
                                '</span>' +
                                '</div>' +
                                '<span class="text-xs font-semibold text-slate-700 dark:text-slate-300 flex-shrink-0">' +
                                pct + '%</span>';
                            legendEl.appendChild(item);
                        });
                    }
                }
            })();
        </script>
    @endpush
@endif
