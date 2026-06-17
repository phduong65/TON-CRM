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
            <a href="{{ route('employees.index') }}" class="stat-card flex flex-col gap-3 group">
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
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5 text-xs text-slate-400">
                        <i class="bi bi-person-badge-fill text-sm"></i>
                        <span>{{ $activeEmployees ?? ($totalEmployees ?? 0) }} đang hoạt động</span>
                    </div>
                    <span
                        class="text-xs text-pcrm-500 dark:text-pcrm-400 opacity-0 group-hover:opacity-100 transition-opacity flex items-center gap-0.5">
                        Xem <i class="bi bi-arrow-right text-xs"></i>
                    </span>
                </div>
            </a>

            {{-- Service Charge --}}
            <div class="stat-card flex flex-col gap-3">
                <div class="flex items-start justify-between">
                    <div class="stat-icon-wrap bg-amber-50 dark:bg-amber-900/30">
                        <i class="bi bi-currency-dollar text-2xl text-amber-600 dark:text-amber-400"></i>
                    </div>
                    <span
                        class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400">
                        <i class="bi bi-gear-fill text-xs"></i> Cài đặt
                    </span>
                </div>
                <div>
                    <p class="text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                        {{ number_format($serviceCharge ?? 0, 0, ',', '.') }}
                    </p>
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mt-0.5">Service Charge (VNĐ)</p>
                </div>
                <div class="flex items-center gap-1.5 text-xs text-slate-400">
                    <i class="bi bi-info-circle text-sm"></i>
                    <span>Phí dịch vụ theo cấu hình hệ thống</span>
                </div>
            </div>

            {{-- Vi phạm tháng này --}}
            <a href="{{ route('penalties.index') }}" class="stat-card flex flex-col gap-3 group">
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
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3 text-xs">
                        <span class="inline-flex items-center gap-1 text-amber-500">
                            <i class="bi bi-hourglass-split text-sm"></i> {{ $pendingPenalties ?? 0 }} chờ duyệt
                        </span>
                        <span class="inline-flex items-center gap-1 text-emerald-500">
                            <i class="bi bi-check-circle-fill text-sm"></i> {{ $approvedPenalties ?? 0 }} đã duyệt
                        </span>
                    </div>
                    <span
                        class="text-xs text-red-500 dark:text-red-400 opacity-0 group-hover:opacity-100 transition-opacity flex items-center gap-0.5">
                        Xem <i class="bi bi-arrow-right text-xs"></i>
                    </span>
                </div>
            </a>

            {{-- Redzone --}}
            <a href="{{ route('redzone.index') }}" class=" hidden stat-card flex flex-col gap-3 group">
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
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5 text-xs text-slate-400">
                        <i class="bi bi-speedometer2 text-sm"></i>
                        <span>Ngưỡng ≤ {{ $redzoneThreshold ?? 50 }} điểm</span>
                    </div>
                    <span
                        class="text-xs text-rose-500 dark:text-rose-400 opacity-0 group-hover:opacity-100 transition-opacity flex items-center gap-0.5">
                        Xem <i class="bi bi-arrow-right text-xs"></i>
                    </span>
                </div>
            </a>

            {{-- Thưởng điểm tháng này --}}
            @can('view-rewards')
                <a href="{{ route('rewards.index') }}" class="stat-card flex flex-col gap-3 group">
                    <div class="flex items-start justify-between">
                        <div class="stat-icon-wrap bg-emerald-50 dark:bg-emerald-900/30">
                            <i class="bi bi-gift-fill text-2xl text-emerald-600 dark:text-emerald-400"></i>
                        </div>
                        @if (($pendingRewards ?? 0) > 0)
                            <span
                                class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 animate-pulse">
                                <i class="bi bi-hourglass-split text-xs"></i> {{ $pendingRewards }} chờ duyệt
                            </span>
                        @endif
                    </div>
                    <div>
                        <p class="text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                            {{ $totalRewardsThisMonth ?? 0 }}</p>
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mt-0.5">Thưởng điểm tháng này</p>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5 text-xs text-slate-400">
                            <i class="bi bi-star-fill text-sm text-emerald-400"></i>
                            <span>{{ $pendingRewards ?? 0 }} chờ duyệt</span>
                        </div>
                        <span
                            class="text-xs text-emerald-500 dark:text-emerald-400 opacity-0 group-hover:opacity-100 transition-opacity flex items-center gap-0.5">
                            Xem <i class="bi bi-arrow-right text-xs"></i>
                        </span>
                    </div>
                </a>
            @endcan
        </div>


        {{-- ─── Row 3: Charts ──────────────────────────────────────────────────── --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

            {{-- Penalty & Reward Trend Chart (2/3 width) --}}
            <div class="card lg:col-span-2">
                <div class="card-header flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-pcrm-50 dark:bg-pcrm-900/30 flex items-center justify-center">
                            <i class="bi bi-bar-chart-fill text-pcrm-600 dark:text-pcrm-400"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-slate-900 dark:text-white text-sm">Xu hướng thưởng / phạt</h3>
                            <p class="text-xs text-slate-400">6 tháng gần nhất</p>
                        </div>
                    </div>
                    {{-- <div class="flex items-center gap-3 ">
                        <span class=" inline-flex items-center gap-1 text-xs text-slate-400">
                            <span class="w-3 h-1.5 rounded-full bg-pcrm-500 inline-block"></span> Vi phạm
                        </span>
                        <span class="inline-flex items-center gap-1 text-xs text-slate-400">
                            <span class="w-3 h-1.5 rounded-full bg-emerald-500 inline-block"></span> Thưởng
                        </span>
                        <span class="inline-flex items-center gap-1 text-xs text-slate-400">
                            <span class="w-5 h-0.5 rounded-full bg-amber-400 inline-block"></span> Đã duyệt phạt
                        </span>
                    </div> --}}
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

        {{-- ─── Analytics: KPI Metrics Strip ──────────────────────────────────── --}}
        @php $nowLabel = 'T.' . $now->month . '/' . $now->year; @endphp
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 mb-6">

            {{-- Điểm trung bình --}}
            <div class="card p-4 flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Điểm TB
                        tháng này</p>
                    <span
                        class="w-8 h-8 rounded-xl flex items-center justify-center
                        {{ $avgScore >= 80 ? 'bg-emerald-50 dark:bg-emerald-900/30' : ($avgScore >= 60 ? 'bg-amber-50 dark:bg-amber-900/30' : 'bg-red-50 dark:bg-red-900/30') }}">
                        <i
                            class="bi bi-speedometer2 text-base
                            {{ $avgScore >= 80 ? 'text-emerald-600 dark:text-emerald-400' : ($avgScore >= 60 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400') }}"></i>
                    </span>
                </div>
                <div class="flex items-end gap-1.5">
                    @php $scoreLabel = $avgScore >= 90 ? ['Xuất sắc','text-emerald-600 dark:text-emerald-400'] : ($avgScore >= 80 ? ['Tốt','text-emerald-500'] : ($avgScore >= 70 ? ['Khá','text-amber-500'] : ($avgScore >= 60 ? ['Trung bình','text-orange-500'] : ['Cần cải thiện','text-red-500']))); @endphp
                    <span
                        class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">{{ $avgScore }}</span>
                    <span class="text-sm text-slate-400 mb-0.5">/ 100</span>
                    <span class="text-xs font-bold ml-auto {{ $scoreLabel[1] }}">{{ $scoreLabel[0] }}</span>
                </div>
                <div class="space-y-1.5">
                    <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                        <div class="h-full rounded-full {{ $avgScore >= 80 ? 'bg-emerald-500' : ($avgScore >= 60 ? 'bg-amber-500' : 'bg-red-500') }}"
                            style="width: {{ $avgScore }}%"></div>
                    </div>
                    <p class="text-xs text-slate-400">{{ $nowLabel }} · Toàn công ty ({{ $totalEmployees }} NV)</p>
                </div>
            </div>

            {{-- Tổng điểm bị trừ --}}
            <div class="card p-4 flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Điểm trừ
                        tháng này</p>
                    <span class="w-8 h-8 rounded-xl bg-red-50 dark:bg-red-900/30 flex items-center justify-center">
                        <i class="bi bi-dash-circle-fill text-red-500 text-base"></i>
                    </span>
                </div>
                <div class="flex items-end gap-1.5">
                    <span class="text-3xl font-black text-red-600 dark:text-red-400 tracking-tight">
                        -{{ number_format($totalPointsDeductedThisMonth) }}
                    </span>
                    <span class="text-sm text-slate-400 mb-0.5">pts</span>
                </div>
                <div>
                    <p class="text-xs text-slate-400">Từ {{ $approvedPenalties }} phiếu phạt đã được duyệt</p>
                    <div class="flex items-center gap-1.5 mt-2">
                        <span
                            class="inline-flex items-center gap-1 text-xs {{ $pendingPenalties > 0 ? 'text-amber-600 dark:text-amber-400 font-semibold' : 'text-slate-400' }}">
                            <i class="bi bi-hourglass-split text-xs"></i>
                            {{ $pendingPenalties }} phiếu đang chờ duyệt
                        </span>
                    </div>
                </div>
            </div>

            {{-- Tỷ lệ phê duyệt --}}
            <div class="card p-4 flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tỷ lệ phê
                        duyệt</p>
                    <span class="w-8 h-8 rounded-xl bg-pcrm-50 dark:bg-pcrm-900/30 flex items-center justify-center">
                        <i class="bi bi-check2-all text-pcrm-600 dark:text-pcrm-400 text-base"></i>
                    </span>
                </div>
                <div class="flex items-end gap-1.5">
                    <span
                        class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">{{ $approvalRate }}</span>
                    <span class="text-sm text-slate-400 mb-0.5">%</span>
                </div>
                <div class="space-y-1.5">
                    <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                        <div class="h-full rounded-full bg-pcrm-500" style="width: {{ $approvalRate }}%"></div>
                    </div>
                    <p class="text-xs text-slate-400">{{ $approvedPenalties }} duyệt / {{ $totalPenaltiesThisMonth }}
                        tổng phiếu</p>
                </div>
            </div>

            {{-- Tái phạm --}}
            <div class="card p-4 flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tái phạm
                    </p>
                    <span class="w-8 h-8 rounded-xl bg-orange-50 dark:bg-orange-900/30 flex items-center justify-center">
                        <i class="bi bi-arrow-repeat text-orange-600 dark:text-orange-400 text-base"></i>
                    </span>
                </div>
                <div class="flex items-end gap-1.5">
                    <span
                        class="text-3xl font-black {{ $repeatOffendersCount > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-slate-900 dark:text-white' }} tracking-tight">
                        {{ $repeatOffendersCount }}
                    </span>
                    <span class="text-sm text-slate-400 mb-0.5">nhân viên</span>
                </div>
                <div>
                    <p class="text-xs text-slate-400">Bị phạt ≥ 2 lần trong {{ $nowLabel }}</p>
                    @if ($repeatOffendersCount > 0)
                        <span
                            class="inline-flex items-center gap-1 mt-2 text-xs font-semibold text-orange-600 dark:text-orange-400">
                            <i class="bi bi-exclamation-triangle-fill text-xs"></i> Cần chú ý
                        </span>
                    @else
                        <span
                            class="inline-flex items-center gap-1 mt-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                            <i class="bi bi-shield-check text-xs"></i> Ổn định
                        </span>
                    @endif
                </div>
            </div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="mb-6">

            {{-- Zone Distribution --}}
            <div class="card">
                <div class="card-header flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                        <i class="bi bi-circle-half text-slate-600 dark:text-slate-400"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-slate-900 dark:text-white text-sm">Phân bổ Zone nhân viên</h3>
                        <p class="text-xs text-slate-400">{{ $nowLabel }} · Theo điểm hiệu suất</p>
                    </div>
                </div>
                <div class="card-body flex flex-col items-center gap-5">
                    <div class="relative" style="width:160px;height:160px;">
                        <canvas id="zonePieChart"></canvas>
                        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                            <p class="text-2xl font-black text-slate-800 dark:text-white leading-none">
                                {{ $totalEmployees }}</p>
                            <p class="text-xs text-slate-400 mt-0.5">nhân viên</p>
                        </div>
                    </div>
                    <div class="w-full grid grid-cols-2 gap-2">
                        @php
                            $zoneItems = [
                                [
                                    'label' => 'Greenzone',
                                    'sub' => '≥ 90 pts',
                                    'val' => $zoneDist['values'][0],
                                    'dot' => 'bg-emerald-500',
                                    'text' => 'text-emerald-700 dark:text-emerald-400',
                                ],
                                [
                                    'label' => 'Yellowzone',
                                    'sub' => '≥ 80 pts',
                                    'val' => $zoneDist['values'][1],
                                    'dot' => 'bg-yellow-400',
                                    'text' => 'text-yellow-700 dark:text-yellow-400',
                                ],
                                [
                                    'label' => 'Orangezone',
                                    'sub' => '≥ 70 pts',
                                    'val' => $zoneDist['values'][2],
                                    'dot' => 'bg-orange-500',
                                    'text' => 'text-orange-700 dark:text-orange-400',
                                ],
                                [
                                    'label' => 'Redzone',
                                    'sub' => '< 70 pts',
                                    'val' => $zoneDist['values'][3],
                                    'dot' => 'bg-red-500',
                                    'text' => 'text-red-700 dark:text-red-400',
                                ],
                            ];
                        @endphp
                        @foreach ($zoneItems as $zi)
                            <div class="flex items-center gap-2 px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-700/50">
                                <span class="w-2.5 h-2.5 rounded-full {{ $zi['dot'] }} flex-shrink-0"></span>
                                <div class="min-w-0 flex justify-between w-full">
                                    <p class="text-xs font-bold {{ $zi['text'] }}">{{ $zi['label'] }}</p>
                                    <p class="text-xs text-slate-400">{{ $zi['val'] }} NV · {{ $zi['sub'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

        </div>

        {{-- ─── Analytics: Avg Score Trend ────────────────────────────────────────── --}}
        <div class="mb-6">

            {{-- Avg Score Trend --}}
            <div class="card">
                <div class="card-header flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-pcrm-50 dark:bg-pcrm-900/30 flex items-center justify-center">
                        <i class="bi bi-graph-up text-pcrm-600 dark:text-pcrm-400"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-slate-900 dark:text-white text-sm">Xu hướng điểm TB</h3>
                        <p class="text-xs text-slate-400">6 tháng gần nhất</p>
                    </div>
                </div>
                <div class="card-body flex flex-col gap-4">
                    <div class="chart-container" style="height: 160px;">
                        <canvas id="avgScoreTrendChart"></canvas>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex flex-col gap-0.5 p-4 rounded-xl bg-slate-50 dark:bg-slate-700/50">
                            <p class="text-xs text-slate-400">Tháng này</p>
                            <p
                                class="text-xl font-black {{ $avgScore >= 80 ? 'text-emerald-600 dark:text-emerald-400' : ($avgScore >= 60 ? 'text-amber-600' : 'text-red-600') }}">
                                {{ $avgScore }}<span class="text-xs font-normal text-slate-400 ml-0.5">pts</span>
                            </p>
                        </div>
                        <div class="flex flex-col gap-0.5 p-3 rounded-xl bg-slate-50 dark:bg-slate-700/50">
                            <p class="text-xs text-slate-400">Mục tiêu</p>
                            <p class="text-xl font-black text-pcrm-600 dark:text-pcrm-400">
                                90<span class="text-xs font-normal text-slate-400 ml-0.5">pts</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>
        {{-- ─── Analytics: Daily Activity ───────────────────────────────────────── --}}
        <div class="card mb-6">
            <div class="card-header flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                        <i class="bi bi-calendar3-week text-slate-600 dark:text-slate-400"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-slate-900 dark:text-white text-sm">Hoạt động theo ngày</h3>
                        <p class="text-xs text-slate-400">Phiếu phạt & Thưởng tạo trong tháng
                            {{ $now->month }}/{{ $now->year }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center gap-1 text-xs text-slate-400">
                        <span class="w-3 h-1.5 rounded-full bg-pcrm-500 inline-block"></span> Vi phạm
                    </span>
                    <span class="inline-flex items-center gap-1 text-xs text-slate-400">
                        <span class="w-3 h-1.5 rounded-full bg-emerald-500 inline-block"></span> Thưởng
                    </span>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-container" style="height: 190px;">
                    <canvas id="dailyActivityChart"></canvas>
                </div>
            </div>
        </div>

        {{-- ─── Analytics: Weekday Pattern + Penalty Funnel ────────────────────── --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

            {{-- Weekday Distribution (1/3) --}}
            <div class="card">
                <div class="card-header flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-violet-50 dark:bg-violet-900/30 flex items-center justify-center">
                        <i class="bi bi-calendar-week text-violet-600 dark:text-violet-400"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-slate-900 dark:text-white text-sm">Vi phạm theo thứ</h3>
                        <p class="text-xs text-slate-400">Phân bổ ngày trong tuần</p>
                    </div>
                </div>
                <div class="card-body flex justify-center">
                    <div style="width:190px;height:190px;">
                        <canvas id="weekdayChart"></canvas>
                    </div>
                </div>
            </div>

            {{-- Penalty Funnel (2/3) --}}
            <div class="card lg:col-span-2">
                <div class="card-header flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-pcrm-50 dark:bg-pcrm-900/30 flex items-center justify-center">
                        <i class="bi bi-funnel-fill text-pcrm-600 dark:text-pcrm-400"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-slate-900 dark:text-white text-sm">Trạng thái phiếu phạt</h3>
                        <p class="text-xs text-slate-400">Tổng hợp toàn thời gian</p>
                    </div>
                </div>
                <div class="card-body">
                    @php
                        $fTotal = $penaltyFunnel['total'];
                        $fApprovedPct = $fTotal > 0 ? round(($penaltyFunnel['approved'] / $fTotal) * 100, 1) : 0;
                        $fPendingPct = $fTotal > 0 ? round(($penaltyFunnel['pending'] / $fTotal) * 100, 1) : 0;
                        $fRejectedPct = $fTotal > 0 ? round(($penaltyFunnel['rejected'] / $fTotal) * 100, 1) : 0;
                    @endphp
                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center flex-shrink-0">
                                <i class="bi bi-file-text-fill text-slate-500 dark:text-slate-400 text-sm"></i>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Tổng phiếu
                                        phạt</span>
                                    <span
                                        class="text-sm font-black text-slate-800 dark:text-white">{{ number_format($fTotal) }}</span>
                                </div>
                                <div class="h-2.5 rounded-full bg-slate-200 dark:bg-slate-600 overflow-hidden">
                                    <div class="h-full flex">
                                        <div class="bg-emerald-500 h-full" style="width:{{ $fApprovedPct }}%"></div>
                                        <div class="bg-amber-400 h-full" style="width:{{ $fPendingPct }}%"></div>
                                        <div class="bg-red-500 h-full" style="width:{{ $fRejectedPct }}%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div
                                class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center flex-shrink-0">
                                <i class="bi bi-check-circle-fill text-emerald-500 text-sm"></i>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Đã phê
                                        duyệt</span>
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="text-sm font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($penaltyFunnel['approved']) }}</span>
                                        <span class="text-xs text-slate-400 w-12 text-right">{{ $fApprovedPct }}%</span>
                                    </div>
                                </div>
                                <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                                    <div class="h-full rounded-full bg-emerald-500" style="width:{{ $fApprovedPct }}%">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div
                                class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center flex-shrink-0">
                                <i class="bi bi-hourglass-split text-amber-500 text-sm"></i>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Chờ phê
                                        duyệt</span>
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="text-sm font-bold text-amber-600 dark:text-amber-400">{{ number_format($penaltyFunnel['pending']) }}</span>
                                        <span class="text-xs text-slate-400 w-12 text-right">{{ $fPendingPct }}%</span>
                                    </div>
                                </div>
                                <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                                    <div class="h-full rounded-full bg-amber-400" style="width:{{ $fPendingPct }}%">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div
                                class="w-8 h-8 rounded-xl bg-red-50 dark:bg-red-900/30 flex items-center justify-center flex-shrink-0">
                                <i class="bi bi-x-circle-fill text-red-500 text-sm"></i>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Từ chối</span>
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="text-sm font-bold text-red-600 dark:text-red-400">{{ number_format($penaltyFunnel['rejected']) }}</span>
                                        <span class="text-xs text-slate-400 w-12 text-right">{{ $fRejectedPct }}%</span>
                                    </div>
                                </div>
                                <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                                    <div class="h-full rounded-full bg-red-500" style="width:{{ $fRejectedPct }}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @if ($fTotal > 0)
                        <div
                            class="mt-5 flex items-center justify-between text-xs pt-4 border-t border-slate-100 dark:border-slate-700">
                            <span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ $fApprovedPct }}% đã
                                duyệt</span>
                            <span class="font-semibold text-amber-500">{{ $fPendingPct }}% đang chờ</span>
                            <span class="font-semibold text-red-500">{{ $fRejectedPct }}% từ chối</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ─── Row 3: Recent Penalties + Recent Rewards + Redzone ───────────── --}}
        @php $canViewRewards = auth()->user()->can('view-rewards'); @endphp
        <div class="grid grid-cols-1 {{ $canViewRewards ? 'lg:grid-cols-3' : 'lg:grid-cols-2' }} gap-6 mb-6">

            {{-- Recent Penalties --}}
            <div class="card">
                <div class="card-header flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i class="bi bi-file-text-fill text-slate-500 dark:text-slate-400"></i>
                        <h3 class="font-semibold text-slate-900 dark:text-white text-sm">Xử phạt gần đây</h3>
                    </div>
                    @can('view-penalties')
                        <a href="{{ route('penalties.index') }}"
                            class="text-xs text-pcrm-600 dark:text-pcrm-400 hover:underline flex items-center gap-1">
                            Xem tất cả <i class="bi bi-arrow-right text-xs"></i>
                        </a>
                    @endcan
                </div>
                <div class="card-body p-0">
                    @if (isset($recentPenalties) && count($recentPenalties) > 0)
                        <div class="divide-y divide-slate-100 dark:divide-slate-700/50">
                            @foreach ($recentPenalties as $penalty)
                                @php
                                    $statusMap = [
                                        'pending' => ['label' => 'Chờ duyệt', 'class' => 'badge-warning'],
                                        'approved' => ['label' => 'Đã duyệt', 'class' => 'badge-success'],
                                        'rejected' => ['label' => 'Từ chối', 'class' => 'badge-danger'],
                                    ];
                                    $pStatus = $statusMap[$penalty->status] ?? [
                                        'label' => $penalty->status,
                                        'class' => 'badge-neutral',
                                    ];
                                @endphp
                                <div
                                    class="flex items-center justify-between px-5 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors gap-3">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <div
                                            class="w-7 h-7 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-xs font-semibold text-slate-600 dark:text-slate-300 flex-shrink-0">
                                            {{ strtoupper(substr($penalty->employee->name ?? 'N', 0, 1)) }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-slate-800 dark:text-slate-200 truncate">
                                                {{ $penalty->employee->name ?? 'N/A' }}</p>
                                            <p class="text-xs text-slate-400 truncate">
                                                {{ $penalty->violation->name ?? 'N/A' }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        <span class="{{ $pStatus['class'] }} text-xs">{{ $pStatus['label'] }}</span>
                                        <span
                                            class="text-xs text-slate-400">{{ $penalty->created_at->format('d/m') }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="p-8 text-center">
                            <div
                                class="w-12 h-12 mx-auto mb-3 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                                <i class="bi bi-check-circle-fill text-xl text-slate-400"></i>
                            </div>
                            <p class="text-sm text-slate-500 dark:text-slate-400">Chưa có xử phạt nào gần đây</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Recent Rewards --}}
            @can('view-rewards')
                <div class="card">
                    <div class="card-header flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="bi bi-gift-fill text-emerald-500"></i>
                            <h3 class="font-semibold text-slate-900 dark:text-white text-sm">Thưởng điểm gần đây</h3>
                        </div>
                        <a href="{{ route('rewards.index') }}"
                            class="text-xs text-pcrm-600 dark:text-pcrm-400 hover:underline flex items-center gap-1">
                            Xem tất cả <i class="bi bi-arrow-right text-xs"></i>
                        </a>
                    </div>
                    <div class="card-body p-0">
                        @if (isset($recentRewards) && $recentRewards->count() > 0)
                            <div class="divide-y divide-slate-100 dark:divide-slate-700/50">
                                @foreach ($recentRewards as $reward)
                                    @php
                                        $sm = [
                                            'pending' => ['badge-warning', 'Chờ duyệt'],
                                            'approved' => ['badge-success', 'Đã duyệt'],
                                            'rejected' => ['badge-danger', 'Từ chối'],
                                        ];
                                        [$sc, $sl] = $sm[$reward->status] ?? ['badge-neutral', $reward->status];
                                    @endphp
                                    <div
                                        class="flex items-center justify-between px-5 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors gap-3">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <div
                                                class="w-7 h-7 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center text-xs font-semibold text-emerald-700 dark:text-emerald-400 flex-shrink-0">
                                                {{ strtoupper(substr($reward->employee->name ?? 'N', 0, 1)) }}
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-slate-800 dark:text-slate-200 truncate">
                                                    {{ $reward->employee?->name ?? 'N/A' }}</p>
                                                <p class="text-xs text-slate-400 truncate">
                                                    {{ $reward->rewardType?->name ?? '—' }}</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2 flex-shrink-0">
                                            <span
                                                class="text-sm font-bold text-emerald-600 dark:text-emerald-400">+{{ $reward->total_points_awarded }}</span>
                                            <span class="{{ $sc }} text-xs">{{ $sl }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="p-8 text-center">
                                <div
                                    class="w-12 h-12 mx-auto mb-3 rounded-full bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center">
                                    <i class="bi bi-gift text-xl text-emerald-400"></i>
                                </div>
                                <p class="text-sm text-slate-500 dark:text-slate-400">Chưa có thưởng nào gần đây</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endcan

            {{-- Redzone Widget --}}
            <div class="card">
                <div class="card-header flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i class="bi bi-exclamation-triangle-fill text-rose-500"></i>
                        <h3 class="font-semibold text-slate-900 dark:text-white text-sm">Redzone — Cảnh báo</h3>
                    </div>
                    <a href="{{ route('redzone.index') }}"
                        class="text-xs text-pcrm-600 dark:text-pcrm-400 hover:underline flex items-center gap-1">
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
                                            class="w-14 h-1.5 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                                            <div class="h-full rounded-full bg-rose-500"
                                                style="width: {{ min(100, max(0, $emp->total_score ?? 0)) }}%"></div>
                                        </div>
                                        <span
                                            class="badge badge-danger font-bold text-xs min-w-[40px] text-center">{{ number_format($emp->total_score ?? 0) }}đ</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="p-8 text-center">
                            <div
                                class="w-12 h-12 mx-auto mb-3 rounded-full bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center">
                                <i class="bi bi-shield-fill-check text-xl text-emerald-500"></i>
                            </div>
                            <p class="text-sm font-medium text-emerald-600 dark:text-emerald-400">Không có nhân viên trong
                                vùng đỏ</p>
                            <p class="text-xs text-slate-400 mt-1">Tất cả đang ở trạng thái tốt</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ─── Analytics: Top Risk Employees ─────────────────────────────────── --}}
        @if ($topViolators->isNotEmpty())
            <div class="card mb-6">
                <div class="card-header flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-red-50 dark:bg-red-900/30 flex items-center justify-center">
                            <i class="bi bi-shield-exclamation text-red-600 dark:text-red-400"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-slate-900 dark:text-white text-sm">Top nhân viên vi phạm nhiều
                                nhất</h3>
                            <p class="text-xs text-slate-400">Phiếu phạt đã duyệt · {{ $nowLabel }}</p>
                        </div>
                    </div>
                    @can('view-penalties')
                        <a href="{{ route('penalties.index') }}"
                            class="text-xs text-pcrm-600 dark:text-pcrm-400 hover:underline flex items-center gap-1">
                            Xem tất cả phiếu phạt <i class="bi bi-arrow-right text-xs"></i>
                        </a>
                    @endcan
                </div>
                <div class="card-body p-0">
                    <div class="table-container border-0 rounded-none">
                        <table class="table-base">
                            <thead>
                                <tr>
                                    <th class="table-th w-10 text-center">#</th>
                                    <th class="table-th">Nhân viên</th>
                                    <th class="table-th">Đội nhóm</th>
                                    <th class="table-th text-center">Số phiếu phạt</th>
                                    <th class="table-th text-center">Điểm bị trừ</th>
                                    <th class="table-th text-center">Điểm tháng này</th>
                                    <th class="table-th text-center">Mức rủi ro</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($topViolators as $i => $viol)
                                    @php
                                        $emp = $viol->employee;
                                        $currentScore = $emp
                                            ? optional(
                                                    $emp
                                                        ->monthlyScores()
                                                        ->where('month', $now->month)
                                                        ->where('year', $now->year)
                                                        ->first(),
                                                )->final_score ?? 100
                                            : 100;
                                        [$riskLabel, $riskClass] = match (true) {
                                            $viol->penalty_count >= 5 => ['Cực cao', 'badge-danger'],
                                            $viol->penalty_count >= 3 => [
                                                'Cao',
                                                'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
                                            ],
                                            $viol->penalty_count >= 2 => ['TB', 'badge-warning'],
                                            default => ['Thấp', 'badge-neutral'],
                                        };
                                    @endphp
                                    <tr class="table-tr-hover">
                                        <td class="table-td text-center">
                                            @if ($i === 0)
                                                <i class="bi bi-trophy-fill text-amber-500"></i>
                                            @elseif($i === 1)
                                                <i class="bi bi-trophy text-slate-400"></i>
                                            @elseif($i === 2)
                                                <i class="bi bi-trophy text-orange-400"></i>
                                            @else
                                                <span
                                                    class="text-sm text-slate-400 font-medium">{{ $i + 1 }}</span>
                                            @endif
                                        </td>
                                        <td class="table-td">
                                            <div class="flex items-center gap-2.5">
                                                <div
                                                    class="w-8 h-8 rounded-full bg-red-50 dark:bg-red-900/30 flex items-center justify-center text-xs font-bold text-red-600 dark:text-red-400 flex-shrink-0">
                                                    {{ $emp ? strtoupper(mb_substr($emp->name, 0, 2)) : '??' }}
                                                </div>
                                                <div class="min-w-0">
                                                    <p
                                                        class="text-sm font-semibold text-slate-800 dark:text-slate-200 truncate">
                                                        {{ $emp?->name ?? 'N/A' }}</p>
                                                    <p class="text-xs text-slate-400">{{ $emp?->code ?? '' }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="table-td">
                                            <span
                                                class="text-sm text-slate-600 dark:text-slate-400">{{ $emp?->team?->name ?? '—' }}</span>
                                        </td>
                                        <td class="table-td text-center">
                                            <span
                                                class="text-base font-black text-red-600 dark:text-red-400">{{ $viol->penalty_count }}</span>
                                        </td>
                                        <td class="table-td text-center">
                                            <span class="text-sm font-bold text-orange-600 dark:text-orange-400">
                                                -{{ number_format($viol->total_deducted ?? 0) }} pts
                                            </span>
                                        </td>
                                        <td class="table-td text-center">
                                            <div class="flex flex-col items-center gap-1">
                                                <span
                                                    class="text-sm font-bold {{ $currentScore < 70 ? 'text-red-600 dark:text-red-400' : ($currentScore < 80 ? 'text-orange-500' : ($currentScore < 90 ? 'text-amber-500' : 'text-emerald-600 dark:text-emerald-400')) }}">
                                                    {{ $currentScore }}
                                                </span>
                                                <div
                                                    class="w-14 h-1.5 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                                                    <div class="h-full rounded-full {{ $currentScore < 70 ? 'bg-red-500' : ($currentScore < 80 ? 'bg-orange-500' : 'bg-emerald-500') }}"
                                                        style="width:{{ min(100, $currentScore) }}%"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="table-td text-center">
                                            <span
                                                class="{{ $riskClass }} text-xs font-semibold">{{ $riskLabel }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        {{-- ─── Row 4: Top Rankings ─────────────────────────────────────────────── --}}
        <div class="card">
            <div class="card-header flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="bi bi-award-fill text-amber-500 text-lg"></i>
                    <h3 class="font-semibold text-slate-900 dark:text-white">Nhân viên xuất sắc tháng này</h3>
                </div>
                <div class="flex items-center gap-3">
                    <span class="hidden sm:inline-flex items-center gap-1 text-xs text-slate-400">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span> An toàn
                    </span>
                    <span class="hidden sm:inline-flex items-center gap-1 text-xs text-slate-400">
                        <span class="w-2 h-2 rounded-full bg-rose-500 inline-block"></span> Redzone
                    </span>
                    <a href="{{ route('rankings.index') }}"
                        class="text-sm text-pcrm-600 dark:text-pcrm-400 hover:underline flex items-center gap-1">
                        Xem tất cả <i class="bi bi-arrow-right text-xs"></i>
                    </a>
                </div>
            </div>
            <div class="card-body">
                @if (isset($topEmployees) && count($topEmployees) > 0)
                    <div class="grid grid-cols-1 sm:grid-cols-1 gap-3">
                        @foreach ($topEmployees as $index => $emp)
                            @php
                                $score = $emp->total_score ?? 0;
                                $isRedzone = $score < $redzoneThreshold;
                                $scorePct = min(100, max(0, $score));
                            @endphp
                            <div class="leaderboard-card {{ $index < 3 ? 'leaderboard-card-top' : '' }}"
                                style="animation-delay:{{ 0.05 + $index * 0.04 }}s">

                                {{-- Rank --}}
                                <div class="leaderboard-card-rank leaderboard-medal w-7 text-center">
                                    @if ($index === 0)
                                        <i class="bi bi-trophy-fill text-amber-500"></i>
                                    @elseif ($index === 1)
                                        <i class="bi bi-trophy text-slate-400"></i>
                                    @elseif ($index === 2)
                                        <i class="bi bi-trophy text-orange-400"></i>
                                    @else
                                        <span
                                            class="text-sm font-semibold text-slate-400 dark:text-slate-500">{{ $index + 1 }}</span>
                                    @endif
                                </div>

                                {{-- Avatar --}}
                                <div
                                    class="w-9 h-9 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-bold
                                    {{ $isRedzone
                                        ? 'bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300 ring-2 ring-rose-300 dark:ring-rose-700'
                                        : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300' }}">
                                    {{ strtoupper(mb_substr($emp->name, 0, 2)) }}
                                </div>

                                {{-- Info --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <p class="text-sm font-semibold truncate text-slate-800 dark:text-slate-200">
                                            {{ $emp->name }}</p>
                                        @if ($isRedzone)
                                            <span
                                                class="inline-flex items-center gap-0.5 text-xs font-bold px-1.5 py-0.5 rounded-full bg-rose-100 dark:bg-rose-900/40 text-rose-600 dark:text-rose-400 flex-shrink-0">
                                                <i class="bi bi-record-circle-fill" style="font-size:0.55rem"></i> RZ
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-slate-400 truncate mt-0.5">
                                        {{ $emp->team->name ?? '—' }}
                                        @if ($emp->branch->name ?? null)
                                            <span class="mx-1 opacity-40">·</span>{{ $emp->branch->name }}
                                        @endif
                                    </p>
                                </div>

                                {{-- Score + zone bar --}}
                                <div class="leaderboard-card-score">
                                    <p
                                        class="font-extrabold text-sm {{ $isRedzone ? 'text-rose-600 dark:text-rose-400' : 'text-slate-700 dark:text-slate-300' }}">
                                        {{ number_format($score) }}
                                        <span class="text-xs font-normal text-slate-400 ml-0.5">pts</span>
                                    </p>
                                    <div
                                        class="w-14 h-1.5 rounded-full bg-slate-100 dark:bg-slate-700 mt-1.5 overflow-hidden">
                                        <div class="h-full rounded-full transition-all {{ $isRedzone ? 'bg-rose-500' : 'bg-emerald-500' }}"
                                            style="width:{{ $scorePct }}%"></div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-10 text-center">
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

            $heroGradient = match (true) {
                ($myTotalScore ?? 100) >= 90 => 'linear-gradient(135deg, #059669 0%, #047857 45%, #064e3b 100%)',
                ($myTotalScore ?? 100) >= 80 => 'linear-gradient(135deg, #ca8a04 0%, #a16207 45%, #713f12 100%)',
                ($myTotalScore ?? 100) >= 70 => 'linear-gradient(135deg, #ea580c 0%, #c2410c 45%, #7c2d12 100%)',
                default                       => 'linear-gradient(135deg, #e11d48 0%, #be123c 45%, #881337 100%)',
            };
        @endphp

        {{-- ─── Hero Welcome Banner ────────────────────────────────────────────── --}}
        <div class="rounded-2xl overflow-hidden mb-6 shadow-lg dash-hero"
            style="background: {{ $heroGradient }};">
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
                                <div class="w-48 h-48 p-3 rounded-full dash-avatar flex items-center justify-center font-black text-xl md:text-2xl text-white select-none"
                                    style="background:rgba(255,255,255,0.2);border:3px solid rgba(255,255,255,0.45);">
                                    <div class="text-center">
                                        <p class="text-white font-black text-6xl leading-none tracking-tight">
                                            {{ number_format($myTotalScore) }}</p>
                                        <p class="text-xs" style="color:rgba(255,255,255,0.6)">điểm</p>
                                        {{-- Status badge --}}
                                        @if ($employee)
                                            @php
                                                [$zoneBadgeLabel, $zoneBadgeIcon, $zoneBadgeBg, $zoneBadgeBorder, $zoneSubLabel, $zoneBadgeClass] = match (true) {
                                                    $myTotalScore >= 90 => ['Greenzone', 'bi-shield-fill-check', 'rgba(16,185,129,0.75)', 'rgba(52,211,153,0.5)', 'Xuất sắc', ''],
                                                    $myTotalScore >= 80 => ['Yellowzone', 'bi-star-fill', 'rgba(202,138,4,0.8)', 'rgba(234,179,8,0.5)', 'Tốt', ''],
                                                    $myTotalScore >= 70 => ['Orangezone', 'bi-exclamation-triangle-fill', 'rgba(234,88,12,0.8)', 'rgba(249,115,22,0.5)', 'Cần chú ý', ''],
                                                    default             => ['Redzone', 'bi-exclamation-octagon-fill', 'rgba(244,63,94,0.85)', 'rgba(251,113,133,0.5)', 'Cần cải thiện', 'dash-redzone-blink'],
                                                };
                                            @endphp
                                            <div class="hidden sm:flex flex-col items-center gap-2 self-center mt-2">
                                                <div class="flex flex-col items-center gap-1.5">
                                                    <span
                                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-white text-xs font-bold {{ $zoneBadgeClass }}"
                                                        style="background:{{ $zoneBadgeBg }};border:1px solid {{ $zoneBadgeBorder }};">
                                                        <i class="bi {{ $zoneBadgeIcon }} text-xs"></i> {{ $zoneBadgeLabel }}
                                                    </span>
                                                </div>
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
                                    @php
                                        $isMe = $member->id === $employee->id;
                                        $memberScore = $member->total_score ?? 0;
                                        $memberRedzone = $memberScore < $redzoneThreshold;
                                        $memberPct = min(100, max(0, $memberScore));
                                    @endphp
                                    <div class="leaderboard-card {{ $idx < 3 ? 'leaderboard-card-top' : '' }}"
                                        style="animation-delay:{{ 0.35 + $idx * 0.07 }}s;
                                        {{ $isMe ? 'outline:2px solid var(--color-pcrm-500);outline-offset:-2px;border-radius:0.75rem;' : '' }}">

                                        {{-- Rank --}}
                                        <div class="leaderboard-card-rank leaderboard-medal w-7 text-center">
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
                                        <div class="w-9 h-9 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-bold
                                        {{ $isMe
                                            ? 'ring-2 ring-offset-1 dark:ring-offset-slate-800'
                                            : ($memberRedzone
                                                ? 'bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300 ring-2 ring-rose-300 dark:ring-rose-700'
                                                : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300') }}"
                                            style="{{ $isMe ? 'background-color:var(--color-pcrm-100);color:var(--color-pcrm-700);outline:2px solid var(--color-pcrm-400);' : '' }}">
                                            {{ strtoupper(mb_substr($member->name, 0, 2)) }}
                                        </div>

                                        {{-- Name + zone badge --}}
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <p
                                                    class="text-sm font-semibold truncate {{ $isMe ? 'text-pcrm-700 dark:text-pcrm-300' : 'text-slate-800 dark:text-slate-200' }}">
                                                    {{ $member->name }}
                                                    @if ($isMe)
                                                        <span
                                                            class="text-xs font-normal ml-0.5 text-pcrm-500 dark:text-pcrm-400">(bạn)</span>
                                                    @endif
                                                </p>
                                                @if ($memberRedzone)
                                                    <span
                                                        class="inline-flex items-center gap-0.5 text-xs font-bold px-1.5 py-0.5 rounded-full bg-rose-100 dark:bg-rose-900/40 text-rose-600 dark:text-rose-400 flex-shrink-0">
                                                        <i class="bi bi-record-circle-fill" style="font-size:0.55rem"></i>
                                                        RZ
                                                    </span>
                                                @endif
                                            </div>
                                            <p class="text-xs text-slate-400 truncate mt-0.5">
                                                {{ $member->position ?? '—' }}</p>
                                        </div>

                                        {{-- Score + zone bar --}}
                                        <div class="leaderboard-card-score">
                                            <p
                                                class="font-extrabold text-sm
                                            {{ $isMe ? 'text-pcrm-600 dark:text-pcrm-400' : ($memberRedzone ? 'text-rose-600 dark:text-rose-400' : 'text-slate-700 dark:text-slate-300') }}">
                                                {{ number_format($memberScore) }}
                                            </p>
                                            <div
                                                class="w-12 h-1.5 rounded-full bg-slate-100 dark:bg-slate-700 mt-1.5 overflow-hidden">
                                                <div class="h-full rounded-full transition-all {{ $memberRedzone ? 'bg-rose-500' : ($isMe ? 'bg-pcrm-500' : 'bg-emerald-500') }}"
                                                    style="width:{{ $memberPct }}%"></div>
                                            </div>
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
        <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js">
        </script>
        <script>
            (function() {
                var isDark = document.documentElement.classList.contains('dark');

                // ── Color palette ──────────────────────────────────────────────────────
                var colors = {
                    primary: isDark ? 'rgba(99,102,241,0.85)' : 'rgba(79,70,229,0.8)',
                    primaryLight: isDark ? 'rgba(99,102,241,0.15)' : 'rgba(79,70,229,0.1)',
                    reward: isDark ? 'rgba(52,211,153,0.85)' : 'rgba(16,185,129,0.8)',
                    rewardLight: isDark ? 'rgba(52,211,153,0.12)' : 'rgba(16,185,129,0.08)',
                    approved: isDark ? 'rgba(251,191,36,0.9)' : 'rgba(245,158,11,0.9)',
                    approvedLight: isDark ? 'rgba(251,191,36,0.1)' : 'rgba(245,158,11,0.08)',
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
                        rewards: [0, 0, 0, 0, 0, 0],
                    };
                } else if (!trendData.rewards) {
                    trendData.rewards = trendData.labels.map(function() {
                        return 0;
                    });
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

                // ── Chart 1: Penalty & Reward Trend (grouped bar + line) ──────────────
                var ctx1 = document.getElementById('penaltyTrendChart');
                if (ctx1) {
                    new Chart(ctx1, {
                        data: {
                            labels: trendData.labels,
                            datasets: [{
                                    type: 'bar',
                                    label: 'Vi phạm',
                                    data: trendData.total,
                                    backgroundColor: colors.primary,
                                    borderRadius: 5,
                                    borderSkipped: false,
                                    order: 3,
                                },
                                {
                                    type: 'bar',
                                    label: 'Thưởng',
                                    data: trendData.rewards,
                                    backgroundColor: colors.reward,
                                    borderRadius: 5,
                                    borderSkipped: false,
                                    order: 3,
                                },
                                {
                                    type: 'line',
                                    label: 'Đã duyệt phạt',
                                    data: trendData.approved,
                                    borderColor: colors.approved,
                                    backgroundColor: colors.approvedLight,
                                    borderWidth: 2,
                                    pointRadius: 3.5,
                                    pointBackgroundColor: colors.approved,
                                    tension: 0.4,
                                    fill: false,
                                    order: 1,
                                    borderDash: [4, 3],
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
                                tooltip: Object.assign({}, tooltipPlugin, {
                                    callbacks: {
                                        label: function(ctx) {
                                            var label = ctx.dataset.label || '';
                                            var value = ctx.parsed.y;
                                            if (label === 'Vi phạm') return ' Vi phạm: ' + value;
                                            if (label === 'Thưởng') return ' Thưởng: ' + value;
                                            if (label === 'Đã duyệt phạt') return ' Đã duyệt phạt: ' +
                                                value;
                                            return ' ' + label + ': ' + value;
                                        }
                                    }
                                }),
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

    @push('scripts')
        <script>
            (function() {
                var isDark = document.documentElement.classList.contains('dark');
                var gridClr = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.05)';
                var tickClr = isDark ? '#94a3b8' : '#64748b';
                var ttBase = {
                    backgroundColor: isDark ? '#1e293b' : '#ffffff',
                    borderColor: isDark ? '#334155' : '#e2e8f0',
                    borderWidth: 1,
                    titleColor: isDark ? '#f1f5f9' : '#0f172a',
                    bodyColor: isDark ? '#f1f5f9' : '#0f172a',
                    cornerRadius: 8,
                    padding: 10,
                    boxPadding: 4,
                };

                // ── Zone Distribution Doughnut ────────────────────────────────────
                var zoneData = @json($zoneDist);
                var zonePieEl = document.getElementById('zonePieChart');
                if (zonePieEl && zoneData) {
                    new Chart(zonePieEl, {
                        type: 'doughnut',
                        data: {
                            labels: zoneData.labels,
                            datasets: [{
                                data: zoneData.values,
                                backgroundColor: zoneData.colors,
                                borderWidth: 2,
                                borderColor: isDark ? '#1e293b' : '#ffffff',
                                hoverOffset: 6
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '72%',
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: ttBase
                            },
                        },
                    });
                }

                // ── Avg Score Trend Line ──────────────────────────────────────────
                var stData = @json($avgScoreTrend);
                var stEl = document.getElementById('avgScoreTrendChart');
                if (stEl && stData) {
                    new Chart(stEl, {
                        type: 'line',
                        data: {
                            labels: stData.labels,
                            datasets: [{
                                label: 'Điểm TB',
                                data: stData.values,
                                borderColor: isDark ? 'rgba(99,102,241,0.9)' : 'rgba(79,70,229,0.9)',
                                backgroundColor: isDark ? 'rgba(99,102,241,0.15)' : 'rgba(79,70,229,0.08)',
                                borderWidth: 2.5,
                                pointRadius: 4,
                                pointBackgroundColor: isDark ? '#6366f1' : '#4f46e5',
                                tension: 0.4,
                                fill: true,
                                spanGaps: true,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: Object.assign({}, ttBase, {
                                    callbacks: {
                                        label: function(ctx) {
                                            return ' Điểm TB: ' + ctx.parsed.y + ' pts';
                                        }
                                    }
                                }),
                            },
                            scales: {
                                x: {
                                    grid: {
                                        display: false
                                    },
                                    ticks: {
                                        color: tickClr,
                                        font: {
                                            size: 11
                                        }
                                    },
                                    border: {
                                        display: false
                                    }
                                },
                                y: {
                                    min: 0,
                                    max: 100,
                                    grid: {
                                        color: gridClr
                                    },
                                    ticks: {
                                        color: tickClr
                                    },
                                    border: {
                                        display: false,
                                        dash: [4, 4]
                                    }
                                },
                            },
                        },
                    });
                }

                // ── Daily Activity Area Chart ─────────────────────────────────────
                var daData = @json($dailyActivity);
                var daEl = document.getElementById('dailyActivityChart');
                if (daEl && daData) {
                    new Chart(daEl, {
                        type: 'line',
                        data: {
                            labels: daData.labels.map(function(d) {
                                return 'N' + d;
                            }),
                            datasets: [{
                                    label: 'Vi phạm',
                                    data: daData.penalties,
                                    borderColor: isDark ? 'rgba(99,102,241,0.85)' : 'rgba(79,70,229,0.8)',
                                    backgroundColor: isDark ? 'rgba(99,102,241,0.12)' : 'rgba(79,70,229,0.07)',
                                    borderWidth: 2,
                                    pointRadius: 2.5,
                                    pointBackgroundColor: isDark ? '#6366f1' : '#4f46e5',
                                    tension: 0.3,
                                    fill: true
                                },
                                {
                                    label: 'Thưởng',
                                    data: daData.rewards,
                                    borderColor: isDark ? 'rgba(52,211,153,0.85)' : 'rgba(16,185,129,0.8)',
                                    backgroundColor: isDark ? 'rgba(52,211,153,0.10)' : 'rgba(16,185,129,0.07)',
                                    borderWidth: 2,
                                    pointRadius: 2.5,
                                    pointBackgroundColor: isDark ? '#34d399' : '#10b981',
                                    tension: 0.3,
                                    fill: true
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
                                tooltip: ttBase
                            },
                            scales: {
                                x: {
                                    grid: {
                                        display: false
                                    },
                                    ticks: {
                                        color: tickClr,
                                        maxTicksLimit: 15,
                                        maxRotation: 0,
                                        font: {
                                            size: 10
                                        }
                                    },
                                    border: {
                                        display: false
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    grid: {
                                        color: gridClr
                                    },
                                    ticks: {
                                        color: tickClr,
                                        precision: 0,
                                        stepSize: 1
                                    },
                                    border: {
                                        display: false
                                    }
                                },
                            },
                        },
                    });
                }

                // ── Weekday Polar Area ────────────────────────────────────────────
                var wdData = @json($weekdayDist);
                var wdEl = document.getElementById('weekdayChart');
                if (wdEl && wdData) {
                    new Chart(wdEl, {
                        type: 'polarArea',
                        data: {
                            labels: wdData.labels,
                            datasets: [{
                                data: wdData.values,
                                backgroundColor: [
                                    'rgba(99,102,241,0.65)', 'rgba(244,63,94,0.65)',
                                    'rgba(245,158,11,0.65)',
                                    'rgba(16,185,129,0.65)', 'rgba(59,130,246,0.65)',
                                    'rgba(168,85,247,0.65)',
                                    'rgba(239,68,68,0.75)',
                                ],
                                borderWidth: 1.5,
                                borderColor: isDark ? '#1e293b' : '#ffffff',
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'bottom',
                                    labels: {
                                        color: tickClr,
                                        boxWidth: 10,
                                        font: {
                                            size: 10
                                        },
                                        padding: 8
                                    }
                                },
                                tooltip: ttBase,
                            },
                            scales: {
                                r: {
                                    ticks: {
                                        display: false
                                    },
                                    grid: {
                                        color: gridClr
                                    },
                                    pointLabels: {
                                        color: tickClr,
                                        font: {
                                            size: 11
                                        }
                                    }
                                },
                            },
                        },
                    });
                }
            })();
        </script>
    @endpush
@endif
