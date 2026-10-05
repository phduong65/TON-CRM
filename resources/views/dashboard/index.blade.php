@extends('layouts.admin')

@section('title', 'Bảng điều khiển — TON-HR')

@if ($isAdmin)
    @section('page-title', 'Tổng quan vận hành')
    @section('page-title-class', 'text-[28px] sm:text-[32px] font-bold')
    @section('page-subtitle', 'Theo dõi tình hình nhân sự, chấm công và kỷ luật tại các nhà hàng F&B')

    @section('page-actions')
        @php
            $dashWeekdays = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];
        @endphp
        <div class="dash-date-chip" aria-label="Dữ liệu tính đến hôm nay">
            <i class="bi bi-calendar3 text-lg text-slate-500 dark:text-slate-400" aria-hidden="true"></i>
            <div class="leading-tight">
                <p class="text-sm font-semibold text-slate-900 dark:text-white">Hôm nay</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $dashWeekdays[$now->dayOfWeek] }}, {{ $now->format('d/m/Y') }}</p>
            </div>
        </div>
        @can('create-penalties')
            <a href="{{ route('penalties.index') }}" class="btn-primary dash-cta">
                <i class="bi bi-plus-lg"></i>
                <span>Tạo phiếu phạt</span>
            </a>
        @elseif(auth()->user()->can('create-shift-schedules'))
            <a href="{{ route('shift-schedules.index') }}" class="btn-primary dash-cta">
                <i class="bi bi-calendar-plus"></i>
                <span>Xếp ca làm</span>
            </a>
        @endcan
    @endsection
@else
    @section('page-title', 'Việc cần xử lý')
    @section('page-subtitle', 'Ưu tiên phiếu chờ duyệt và cảnh báo trong phạm vi bạn quản lý')
    @section('breadcrumb', 'Tổng quan')
@endif

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

    {{-- Seasonal theme greeting banner --}}
    @include('themes.slots.dashboard-banner')

    @if ($isAdmin)

        {{-- ─── Dashboard Tabs ──────────────────────────────────────────────────── --}}
        <div class="dash-tabs flex items-center gap-1 text-sm font-medium mb-6 overflow-x-auto overflow-y-hidden border-b border-slate-200 dark:border-slate-700" role="tablist" aria-label="Nội dung bảng điều khiển">
            <button type="button" onclick="switchDashTab('overview')" data-dash-tab="overview"
                role="tab" aria-selected="true" aria-controls="dash-panel-overview"
                class="dash-tab-btn inline-flex items-center gap-1.5 px-3 py-2.5 border-b-2 transition-colors whitespace-nowrap border-pcrm-600 text-pcrm-700 dark:text-pcrm-300">
                <i class="bi bi-grid-1x2-fill text-[11px]"></i> Tổng quan
            </button>
            <button type="button" onclick="switchDashTab('reward-penalty')" data-dash-tab="reward-penalty"
                role="tab" aria-selected="false" aria-controls="dash-panel-reward-penalty"
                class="dash-tab-btn inline-flex items-center gap-1.5 px-3 py-2.5 border-b-2 transition-colors whitespace-nowrap border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white">
                <i class="bi bi-hammer text-[11px]"></i> Thưởng phạt
            </button>
            @can('view-attendance')
                <button type="button" onclick="switchDashTab('attendance')" data-dash-tab="attendance"
                    role="tab" aria-selected="false" aria-controls="dash-panel-attendance"
                    class="dash-tab-btn inline-flex items-center gap-1.5 px-3 py-2.5 border-b-2 transition-colors whitespace-nowrap border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white">
                    <i class="bi bi-clock-history text-[11px]"></i> Chấm công
                </button>
            @endcan
        </div>
        <script>
            function switchDashTab(tab) {
                var available = Array.from(document.querySelectorAll('[data-dash-tab]')).map(function(btn) {
                    return btn.getAttribute('data-dash-tab');
                });
                if (!available.includes(tab)) tab = 'overview';
                document.querySelectorAll('[data-dash-tab-panel]').forEach(function(el) {
                    var isTarget = el.getAttribute('data-dash-tab-panel') === tab;
                    el.classList.toggle('hidden', !isTarget);
                    el.style.display = isTarget ? '' : 'none';
                });
                document.querySelectorAll('.dash-tab-btn').forEach(function(btn) {
                    var isActive = btn.getAttribute('data-dash-tab') === tab;
                    btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
                    btn.classList.toggle('border-pcrm-600', isActive);
                    btn.classList.toggle('text-pcrm-700', isActive);
                    btn.classList.toggle('dark:text-pcrm-300', isActive);
                    btn.classList.toggle('border-transparent', !isActive);
                    btn.classList.toggle('text-slate-500', !isActive);
                    btn.classList.toggle('dark:text-slate-400', !isActive);
                });
                var url = new URL(window.location.href);
                if (url.searchParams.get('tab') !== tab) {
                    url.searchParams.set('tab', tab);
                    history.replaceState({ dashTab: tab }, '', url);
                }
                if (window.Chart) {
                    requestAnimationFrame(function() {
                        document.querySelectorAll('[data-dash-tab-panel="' + tab + '"] canvas').forEach(function(canvas) {
                            var chart = Chart.getChart(canvas);
                            if (chart) chart.resize();
                        });
                    });
                }
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function () {
                    switchDashTab(new URL(window.location.href).searchParams.get('tab') || 'overview');
                });
            } else {
                switchDashTab(new URL(window.location.href).searchParams.get('tab') || 'overview');
            }
            window.addEventListener('popstate', function () {
                switchDashTab(new URL(window.location.href).searchParams.get('tab') || 'overview');
            });
        </script>

        <div id="dash-panel-overview" role="tabpanel" data-dash-tab-panel="overview">
            {{-- ─── Layer 2: Hàng KPI vận hành ─────────────────────────────────── --}}
            <div class="dashboard-overview-kpis grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">

                {{-- KPI 1: Nhân viên hoạt động --}}
                <a href="{{ route('employees.index') }}" class="stat-card dash-kpi group">
                    <span class="dash-kpi-icon bg-[#E9EEFF] text-[#2F55E7] dark:bg-[#2F55E7]/20 dark:text-[#809ff9]" aria-hidden="true">
                        <i class="bi bi-people-fill"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <p class="dash-kpi-label">Nhân viên hoạt động</p>
                            <i class="bi bi-chevron-right dash-kpi-chevron" aria-hidden="true"></i>
                        </div>
                        <div class="dash-kpi-value-row">
                            <p class="dash-kpi-value">{{ $totalEmployees ?? 0 }}</p>
                            @if (($newEmployeesThisMonth ?? 0) > 0)
                                <span class="dash-delta dash-delta-up" title="Nhân viên mới trong tháng này">
                                    <i class="bi bi-arrow-up" aria-hidden="true"></i> {{ $newEmployeesThisMonth }} tháng này
                                </span>
                            @endif
                        </div>
                        <p class="dash-kpi-sub">Trên tổng {{ $allEmployeesCount ?? ($totalEmployees ?? 0) }} nhân viên</p>
                    </div>
                </a>

                {{-- KPI 2: Chấm công hôm nay --}}
                @can('view-attendance')
                    @php
                        $attDelta = ($attendanceRateToday !== null && $attendanceRateYesterday !== null)
                            ? $attendanceRateToday - $attendanceRateYesterday
                            : null;
                    @endphp
                    <a href="{{ route('attendance-logs.index') }}" class="stat-card dash-kpi group">
                        <span class="dash-kpi-icon bg-[#e8f5ef] text-[#168A63] dark:bg-[#168A63]/20 dark:text-[#34d399]" aria-hidden="true">
                            <i class="bi bi-check-circle-fill"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <p class="dash-kpi-label">Chấm công hôm nay</p>
                                <i class="bi bi-chevron-right dash-kpi-chevron" aria-hidden="true"></i>
                            </div>
                            <div class="dash-kpi-value-row">
                                <p class="dash-kpi-value">{{ $attendanceRateToday !== null ? $attendanceRateToday . '%' : '—' }}</p>
                                @if ($attDelta !== null && $attDelta !== 0)
                                    <span class="dash-delta {{ $attDelta > 0 ? 'dash-delta-up' : 'dash-delta-down' }}" title="So với hôm qua">
                                        <i class="bi {{ $attDelta > 0 ? 'bi-arrow-up' : 'bi-arrow-down' }}" aria-hidden="true"></i> {{ abs($attDelta) }}%
                                    </span>
                                @endif
                            </div>
                            <p class="dash-kpi-sub">{{ $checkedInTodayCount ?? 0 }}/{{ $scheduledTodayCount ?? 0 }} ca đã chấm công</p>
                        </div>
                    </a>
                @else
                    <div class="stat-card dash-kpi">
                        <span class="dash-kpi-icon bg-[#e8f5ef] text-[#168A63] dark:bg-[#168A63]/20 dark:text-[#34d399]" aria-hidden="true">
                            <i class="bi bi-diagram-3-fill"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="dash-kpi-label">Tổng đội nhóm</p>
                            <div class="dash-kpi-value-row">
                                <p class="dash-kpi-value">{{ $totalTeams ?? 0 }}</p>
                            </div>
                            <p class="dash-kpi-sub">{{ $totalBranches ?? 0 }} chi nhánh</p>
                        </div>
                    </div>
                @endcan

                {{-- KPI 3: Phiếu chờ duyệt --}}
                @can('view-penalties')
                    <a href="{{ route('penalties.index', ['status' => 'pending']) }}" class="stat-card dash-kpi group">
                        <span class="dash-kpi-icon bg-[#fef3dc] text-[#C98219] dark:bg-[#C98219]/20 dark:text-[#fbbf24]" aria-hidden="true">
                            <i class="bi bi-file-earmark-text-fill"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <p class="dash-kpi-label">Phiếu chờ duyệt</p>
                                <i class="bi bi-chevron-right dash-kpi-chevron" aria-hidden="true"></i>
                            </div>
                            <div class="dash-kpi-value-row">
                                <p class="dash-kpi-value">{{ str_pad((string) ($pendingPenaltiesTotal ?? 0), 2, '0', STR_PAD_LEFT) }}</p>
                                @if (($pendingCreatedToday ?? 0) > 0)
                                    <span class="dash-delta dash-delta-down" title="Phiếu mới tạo hôm nay">
                                        <i class="bi bi-arrow-up" aria-hidden="true"></i> {{ $pendingCreatedToday }}
                                    </span>
                                @endif
                            </div>
                            <p class="dash-kpi-sub">Cần phê duyệt sớm</p>
                        </div>
                    </a>
                @endcan

                {{-- KPI 4: Nhân viên Redzone --}}
                <a href="{{ route('redzone.index') }}" class="stat-card dash-kpi group">
                    <span class="dash-kpi-icon bg-[#fde8ea] text-[#C94758] dark:bg-[#C94758]/20 dark:text-[#f87171]" aria-hidden="true">
                        <i class="bi bi-exclamation-circle-fill"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <p class="dash-kpi-label">Nhân viên Redzone</p>
                            <i class="bi bi-chevron-right dash-kpi-chevron" aria-hidden="true"></i>
                        </div>
                        <div class="dash-kpi-value-row">
                            <p class="dash-kpi-value">{{ str_pad((string) ($redzoneCount ?? 0), 2, '0', STR_PAD_LEFT) }}</p>
                        </div>
                        <p class="dash-kpi-sub">Điểm dưới ngưỡng {{ $redzoneThreshold ?? 50 }}</p>
                    </div>
                </a>
            </div>

            {{-- ─── Layer 3: Biểu đồ chấm công tuần & xu hướng kỷ luật ─────────── --}}
            <div class="dashboard-overview-insights grid grid-cols-1 lg:grid-cols-12 gap-4">

                @can('view-attendance')
                    @php
                        $rateValues = array_filter($attendanceRateWeek['rates'] ?? [], fn($v) => $v !== null);
                        $rateAvg = count($rateValues) ? round(array_sum($rateValues) / count($rateValues)) : null;
                    @endphp
                    <section class="card dash-panel lg:col-span-7" aria-labelledby="dashAttRateTitle">
                        <div class="dash-panel-head">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <i class="bi bi-bar-chart-fill text-lg text-[#2F55E7] dark:text-[#809ff9]" aria-hidden="true"></i>
                                <h2 id="dashAttRateTitle" class="dash-panel-title">Tỷ lệ chấm công theo ngày trong tuần</h2>
                            </div>
                            <span class="dash-period-chip">Tuần này</span>
                        </div>
                        <div class="px-5 pb-5">
                            <div class="chart-container" style="height: 230px;">
                                <canvas id="attendanceRateWeekChart" role="img"
                                    aria-label="Tỷ lệ chấm công tuần này{{ $rateAvg !== null ? ', trung bình ' . $rateAvg . '%' : ', chưa có ca được xếp' }}"></canvas>
                            </div>
                        </div>
                    </section>
                @endcan

                @php
                    $trendPenTotal = array_sum($disciplineTrend30['penalties'] ?? []);
                    $trendRewTotal = array_sum($disciplineTrend30['rewards'] ?? []);
                @endphp
                <section class="card dash-panel {{ auth()->user()->can('view-attendance') ? 'lg:col-span-5' : 'lg:col-span-12' }}" aria-labelledby="dashTrendTitle">
                    <div class="dash-panel-head">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <i class="bi bi-graph-up text-lg text-[#2F55E7] dark:text-[#809ff9]" aria-hidden="true"></i>
                            <h2 id="dashTrendTitle" class="dash-panel-title">Xu hướng kỷ luật<span class="hidden 2xl:inline"> (thưởng/phạt)</span></h2>
                        </div>
                        <span class="dash-period-chip">30 ngày qua</span>
                    </div>
                    <div class="px-5 pb-5">
                        <div class="flex items-center gap-5 text-xs text-slate-600 dark:text-slate-300 mb-3">
                            <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-[#E5484D]"></span> Phiếu phạt ({{ $trendPenTotal }})</span>
                            <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-[#1F9D5C]"></span> Phiếu thưởng ({{ $trendRewTotal }})</span>
                        </div>
                        <div class="chart-container" style="height: 195px;">
                            <canvas id="disciplineTrend30Chart" role="img"
                                aria-label="30 ngày qua: {{ $trendPenTotal }} phiếu phạt, {{ $trendRewTotal }} phiếu thưởng"></canvas>
                        </div>
                    </div>
                </section>
            </div>

            {{-- ─── Layer 4: Phiếu phạt chờ duyệt (khối chính) ─────────────────── --}}
            @can('view-penalties')
            <section class="dashboard-overview-queue card dash-panel" aria-labelledby="dashQueueTitle">
                <div class="dash-panel-head">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <i class="bi bi-file-earmark-text text-lg text-[#2F55E7] dark:text-[#809ff9]" aria-hidden="true"></i>
                        <h2 id="dashQueueTitle" class="dash-panel-title">Phiếu phạt chờ duyệt gần đây
                            <span class="sr-only">({{ $pendingPenaltiesTotal ?? 0 }} phiếu)</span></h2>
                    </div>
                    <a href="{{ route('penalties.index', ['status' => 'pending']) }}" class="dash-link">
                        Xem tất cả <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </a>
                </div>
                <div class="px-3 pb-3 sm:px-4 sm:pb-4">
                    @if (isset($pendingApprovalQueue) && $pendingApprovalQueue->count() > 0)
                        <div class="dash-table-wrap hidden sm:block">
                            <table class="w-full text-sm">
                                <caption class="sr-only">Phiếu phạt đang chờ duyệt, cũ nhất trước</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Mã phiếu</th>
                                        <th scope="col">Nhân viên / Đội</th>
                                        <th scope="col" class="min-w-[130px]">Vi phạm</th>
                                        <th scope="col">Điểm trừ</th>
                                        <th scope="col">Ngày</th>
                                        <th scope="col" class="dash-col-status">Trạng thái</th>
                                        <th scope="col" class="text-center">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($pendingApprovalQueue as $penalty)
                                        @php
                                            $statusMap = [
                                                'pending'  => ['label' => 'Chờ duyệt', 'class' => 'badge-warning', 'icon' => 'bi-clock'],
                                                'approved' => ['label' => 'Đã duyệt', 'class' => 'badge-success', 'icon' => 'bi-check-circle-fill'],
                                                'rejected' => ['label' => 'Từ chối', 'class' => 'badge-danger', 'icon' => 'bi-x-circle-fill'],
                                            ];
                                            $pStatus = $statusMap[$penalty->status] ?? ['label' => $penalty->status, 'class' => 'badge-neutral', 'icon' => 'bi-info-circle'];
                                            $qEmp = $penalty->employee;
                                        @endphp
                                        <tr>
                                            <td class="font-mono text-[11px] text-slate-600 dark:text-slate-400 whitespace-nowrap">{{ $penalty->code ?? '#' . $penalty->id }}</td>
                                            <td>
                                                <div class="flex items-center gap-2.5 min-w-0 max-w-[175px]">
                                                    <x-employee-avatar :employee="$qEmp" size="w-8 h-8" />
                                                    <div class="min-w-0">
                                                        <p class="text-[13px] font-semibold text-slate-900 dark:text-white truncate">{{ $qEmp->name ?? '—' }}</p>
                                                        <p class="text-xs text-slate-500 dark:text-slate-400 truncate">
                                                            {{ $qEmp->team->name ?? 'Chưa gán đội' }}@if ($qEmp?->branch) - {{ $qEmp->branch->name }}@endif
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-[13px] text-slate-700 dark:text-slate-300">
                                                <span class="line-clamp-2">{{ $penalty->violation->name ?? 'Vi phạm nội bộ' }}</span>
                                            </td>
                                            <td>
                                                <span class="dash-points">-{{ $penalty->total_points_deducted }}</span>
                                            </td>
                                            <td class="text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                                <span class="block">{{ $penalty->created_at->format('d/m/Y') }}</span>
                                                <span>{{ $penalty->created_at->format('H:i') }}</span>
                                            </td>
                                            <td class="dash-col-status">
                                                <span class="{{ $pStatus['class'] }}"><i class="bi {{ $pStatus['icon'] }}" aria-hidden="true"></i> {{ $pStatus['label'] }}</span>
                                            </td>
                                            <td class="text-center">
                                                <a href="{{ route('penalties.show', $penalty) }}" class="dash-row-action"
                                                   aria-label="Xem chi tiết phiếu {{ $penalty->code ?? '#' . $penalty->id }}" title="Xem chi tiết">
                                                    <i class="bi bi-three-dots" aria-hidden="true"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="divide-y divide-slate-100 dark:divide-slate-700 sm:hidden">
                            @foreach ($pendingApprovalQueue as $penalty)
                                <article class="py-3 px-1 space-y-2">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ $penalty->code ?? '#' . $penalty->id }}</span>
                                        <span class="badge-warning"><i class="bi bi-clock" aria-hidden="true"></i> Chờ duyệt</span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $penalty->employee->name ?? '—' }}</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">
                                            {{ $penalty->employee->team->name ?? 'Chưa gán đội' }}@if ($penalty->employee?->branch) - {{ $penalty->employee->branch->name }}@endif
                                        </p>
                                    </div>
                                    <div class="flex items-start justify-between gap-3 text-sm">
                                        <span class="min-w-0 text-slate-700 dark:text-slate-300">{{ $penalty->violation->name ?? 'Vi phạm nội bộ' }}</span>
                                        <span class="dash-points shrink-0">-{{ $penalty->total_points_deducted }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400">
                                        <span>{{ $penalty->created_at->format('d/m/Y H:i') }}</span>
                                        <a href="{{ route('penalties.show', $penalty) }}" class="btn-ghost btn-sm text-pcrm-600 dark:text-pcrm-400">Xem chi tiết</a>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div class="p-8 text-center">
                            <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-emerald-50 dark:bg-emerald-950/30 flex items-center justify-center">
                                <i class="bi bi-shield-check text-xl text-emerald-600 dark:text-emerald-400" aria-hidden="true"></i>
                            </div>
                            <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Không có phiếu chờ duyệt</p>
                            <p class="text-xs text-slate-400 mt-0.5">Các phiếu mới cần xem xét sẽ xuất hiện tại đây</p>
                        </div>
                    @endif
                </div>
            </section>
            @endcan



            {{-- ─── Layer 6: Thông báo & việc cần làm ─────────────────────────── --}}
            <div class="dashboard-overview-notifications grid grid-cols-1 gap-6">

                {{-- Khối 2: Thông báo & Đăng ký chờ duyệt --}}
                <div class="card flex flex-col">
                    <div class="card-header flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 rounded-xl bg-[#E9EEFF] text-[#2F55E7] dark:bg-[#2F55E7]/20 dark:text-[#809ff9] flex items-center justify-center shrink-0">
                                <i class="bi bi-bell-fill text-base"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold text-slate-900 dark:text-white text-sm">Thông báo &amp; Việc cần làm</h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    @if (($unreadNotificationsCount ?? 0) > 0)
                                        {{ $unreadNotificationsCount }} thông báo chưa đọc
                                    @else
                                        Đã cập nhật mới nhất
                                    @endif
                                </p>
                            </div>
                        </div>
                        <a href="{{ route('notifications.index') }}" class="text-xs font-semibold text-pcrm-600 dark:text-pcrm-400 hover:underline flex items-center gap-1">
                            Xem tất cả <i class="bi bi-arrow-right text-xs"></i>
                        </a>
                    </div>
                    <div class="card-body p-0 flex-1">
                        {{-- Banner đăng ký chờ duyệt nếu có --}}
                        @can('manage-users')
                            @if(($pendingUsersCount ?? 0) > 0)
                                <div class="px-5 py-3 bg-[#FEF8EE] dark:bg-amber-950/30 border-b border-amber-200/60 dark:border-amber-900/40 flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-2.5">
                                        <i class="bi bi-person-check-fill text-[#C98219] text-base shrink-0"></i>
                                        <span class="text-xs font-medium text-amber-900 dark:text-amber-200">
                                            Có <strong>{{ $pendingUsersCount }}</strong> tài khoản đăng ký đang chờ phê duyệt
                                        </span>
                                    </div>
                                    <a href="{{ route('users.index', ['status' => 'pending']) }}" class="text-xs font-bold text-[#C98219] hover:underline whitespace-nowrap">
                                        Duyệt ngay
                                    </a>
                                </div>
                            @endif
                        @endcan

                        @if (isset($recentNotifications) && $recentNotifications->isNotEmpty())
                            <div class="divide-y divide-slate-100 dark:divide-slate-700/50">
                                @foreach ($recentNotifications->take(4) as $notif)
                                    @php $isUnread = $notif->isUnread(); @endphp
                                    <a href="{{ route('notifications.show', $notif) }}"
                                       class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                                        <span class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 {{ $notif->typeColor() }}">
                                            <i class="bi {{ $notif->typeIcon() }} text-sm"></i>
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm truncate {{ $isUnread ? 'font-semibold text-slate-900 dark:text-white' : 'font-medium text-slate-500 dark:text-slate-400' }}">
                                                {{ $notif->title }}
                                            </p>
                                            @if ($notif->body)
                                                <p class="text-xs text-slate-400 truncate mt-0.5">{{ $notif->body }}</p>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-2 shrink-0">
                                            @if ($isUnread)
                                                <span class="w-1.5 h-1.5 rounded-full bg-[#2F55E7]"></span>
                                            @endif
                                            <span class="text-xs text-slate-400">{{ $notif->created_at->diffForHumans(null, true, true) }}</span>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <div class="p-8 text-center flex flex-col items-center justify-center h-full">
                                <div class="w-12 h-12 mb-3 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                                    <i class="bi bi-bell-slash text-xl text-slate-400"></i>
                                </div>
                                <p class="text-sm text-slate-500 dark:text-slate-400">Chưa có thông báo nào</p>
                            </div>
                        @endif
                    </div>
                </div>

            </div>

            {{-- ─── Thao tác nhanh ───────────────────────────────────────────────── --}}
            <div class="dashboard-overview-quick-actions card mb-6">
                <div class="card-header flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                        <i class="bi bi-lightning-charge-fill text-slate-600 dark:text-slate-400"></i>
                    </div>
                    <h3 class="font-semibold text-slate-900 dark:text-white text-sm">Thao tác nhanh</h3>
                </div>
                <div class="card-body">
                    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2 sm:gap-3">
                        @can('create-penalties')
                            <a href="{{ route('penalties.index') }}"
                                class="flex flex-col items-center gap-1.5 sm:gap-2 p-2 sm:p-3 rounded-xl border border-slate-100 dark:border-slate-700 hover:border-red-300 dark:hover:border-red-700 hover:bg-red-50/50 dark:hover:bg-red-900/10 transition-colors text-center">
                                <span class="w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-red-50 dark:bg-red-900/30 flex items-center justify-center shrink-0">
                                    <i class="bi bi-hammer text-red-600 dark:text-red-400"></i>
                                </span>
                                <span class="text-[11px] sm:text-xs font-medium text-slate-600 dark:text-slate-300 leading-tight">Tạo phiếu phạt</span>
                            </a>
                        @endcan
                        @can('create-rewards')
                            <a href="{{ route('rewards.index') }}"
                                class="flex flex-col items-center gap-1.5 sm:gap-2 p-2 sm:p-3 rounded-xl border border-slate-100 dark:border-slate-700 hover:border-emerald-300 dark:hover:border-emerald-700 hover:bg-emerald-50/50 dark:hover:bg-emerald-900/10 transition-colors text-center">
                                <span class="w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center shrink-0">
                                    <i class="bi bi-gift-fill text-emerald-600 dark:text-emerald-400"></i>
                                </span>
                                <span class="text-[11px] sm:text-xs font-medium text-slate-600 dark:text-slate-300 leading-tight">Tạo thưởng điểm</span>
                            </a>
                        @endcan
                        @can('create-employees')
                            <a href="{{ route('employees.index') }}"
                                class="flex flex-col items-center gap-1.5 sm:gap-2 p-2 sm:p-3 rounded-xl border border-slate-100 dark:border-slate-700 hover:border-pcrm-300 dark:hover:border-pcrm-700 hover:bg-pcrm-50/50 dark:hover:bg-pcrm-900/10 transition-colors text-center">
                                <span class="w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-pcrm-50 dark:bg-pcrm-900/30 flex items-center justify-center shrink-0">
                                    <i class="bi bi-person-plus-fill text-pcrm-600 dark:text-pcrm-400"></i>
                                </span>
                                <span class="text-[11px] sm:text-xs font-medium text-slate-600 dark:text-slate-300 leading-tight">Thêm nhân viên</span>
                            </a>
                        @endcan
                        @can('create-shift-schedules')
                            <a href="{{ route('shift-schedules.index') }}"
                                class="flex flex-col items-center gap-1.5 sm:gap-2 p-2 sm:p-3 rounded-xl border border-slate-100 dark:border-slate-700 hover:border-violet-300 dark:hover:border-violet-700 hover:bg-violet-50/50 dark:hover:bg-violet-900/10 transition-colors text-center">
                                <span class="w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-violet-50 dark:bg-violet-900/30 flex items-center justify-center shrink-0">
                                    <i class="bi bi-calendar2-week-fill text-violet-600 dark:text-violet-400"></i>
                                </span>
                                <span class="text-[11px] sm:text-xs font-medium text-slate-600 dark:text-slate-300 leading-tight">Xếp ca làm</span>
                            </a>
                        @endcan
                        @can('create-attendance-logs')
                            <a href="{{ route('attendance-logs.index') }}"
                                class="flex flex-col items-center gap-1.5 sm:gap-2 p-2 sm:p-3 rounded-xl border border-slate-100 dark:border-slate-700 hover:border-sky-300 dark:hover:border-sky-700 hover:bg-sky-50/50 dark:hover:bg-sky-900/10 transition-colors text-center">
                                <span class="w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-sky-50 dark:bg-sky-900/30 flex items-center justify-center shrink-0">
                                    <i class="bi bi-clock-history text-sky-600 dark:text-sky-400"></i>
                                </span>
                                <span class="text-[11px] sm:text-xs font-medium text-slate-600 dark:text-slate-300 leading-tight">Chấm công hộ</span>
                            </a>
                        @endcan
                        @can('view-reports')
                            <a href="{{ route('reports.index') }}"
                                class="flex flex-col items-center gap-1.5 sm:gap-2 p-2 sm:p-3 rounded-xl border border-slate-100 dark:border-slate-700 hover:border-amber-300 dark:hover:border-amber-700 hover:bg-amber-50/50 dark:hover:bg-amber-900/10 transition-colors text-center">
                                <span class="w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center shrink-0">
                                    <i class="bi bi-file-earmark-bar-graph-fill text-amber-600 dark:text-amber-400"></i>
                                </span>
                                <span class="text-[11px] sm:text-xs font-medium text-slate-600 dark:text-slate-300 leading-tight">Xem báo cáo</span>
                            </a>
                        @endcan
                        <a href="{{ route('redzone.index') }}"
                            class="flex flex-col items-center gap-1.5 sm:gap-2 p-2 sm:p-3 rounded-xl border border-slate-100 dark:border-slate-700 hover:border-rose-300 dark:hover:border-rose-700 hover:bg-rose-50/50 dark:hover:bg-rose-900/10 transition-colors text-center">
                            <span class="w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-rose-50 dark:bg-rose-900/30 flex items-center justify-center shrink-0">
                                <i class="bi bi-exclamation-octagon-fill text-rose-600 dark:text-rose-400"></i>
                            </span>
                            <span class="text-[11px] sm:text-xs font-medium text-slate-600 dark:text-slate-300 leading-tight">Xem Redzone</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div id="dash-panel-reward-penalty" role="tabpanel" data-dash-tab-panel="reward-penalty" class="hidden">

            {{-- ─── Analytics: KPI Metrics Strip ──────────────────────────────────── --}}
            @php $nowLabel = 'T.' . $now->month . '/' . $now->year; @endphp
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 mb-6">

                {{-- Điểm trung bình --}}
                <div class="card p-4 flex flex-col gap-3">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Điểm
                            TB
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
                        <p class="text-xs text-slate-400">{{ $nowLabel }} · Toàn công ty ({{ $totalEmployees }} NV)
                        </p>
                    </div>
                </div>

                {{-- Tổng điểm bị trừ --}}
                <div class="card p-4 flex flex-col gap-3">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Điểm
                            trừ
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
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tỷ lệ
                            phê
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
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tái
                            phạm
                        </p>
                        <span
                            class="w-8 h-8 rounded-xl bg-orange-50 dark:bg-orange-900/30 flex items-center justify-center">
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
                            <div
                                class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                                <i class="bi bi-circle-half text-slate-600 dark:text-slate-400"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold text-slate-900 dark:text-white text-sm">Phân bổ Zone nhân viên
                                </h3>
                                <p class="text-xs text-slate-400">{{ $nowLabel }} · Theo điểm hiệu suất</p>
                            </div>
                        </div>
                        <div class="card-body flex flex-col items-center gap-5">
                            <div class="relative" style="width:160px;height:160px;">
                                <canvas id="zonePieChart"></canvas>
                                <div
                                    class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
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
                                    <div
                                        class="flex items-center gap-2 px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-700/50">
                                        <span class="w-2.5 h-2.5 rounded-full {{ $zi['dot'] }} flex-shrink-0"></span>
                                        <div class="min-w-0 flex justify-between w-full">
                                            <p class="text-xs font-bold {{ $zi['text'] }}">{{ $zi['label'] }}</p>
                                            <p class="text-xs text-slate-400">{{ $zi['val'] }} NV ·
                                                {{ $zi['sub'] }}</p>
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
                            <div
                                class="w-8 h-8 rounded-lg bg-pcrm-50 dark:bg-pcrm-900/30 flex items-center justify-center">
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
                                        {{ $avgScore }}<span
                                            class="text-xs font-normal text-slate-400 ml-0.5">pts</span>
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
                        <div
                            class="w-8 h-8 rounded-lg bg-violet-50 dark:bg-violet-900/30 flex items-center justify-center">
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
                                            <span
                                                class="text-xs text-slate-400 w-12 text-right">{{ $fApprovedPct }}%</span>
                                        </div>
                                    </div>
                                    <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                                        <div class="h-full rounded-full bg-emerald-500"
                                            style="width:{{ $fApprovedPct }}%">
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
                                            <span
                                                class="text-xs text-slate-400 w-12 text-right">{{ $fPendingPct }}%</span>
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
                                            <span
                                                class="text-xs text-slate-400 w-12 text-right">{{ $fRejectedPct }}%</span>
                                        </div>
                                    </div>
                                    <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                                        <div class="h-full rounded-full bg-red-500" style="width:{{ $fRejectedPct }}%">
                                        </div>
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
                                            <x-employee-avatar :employee="$penalty->employee" size="w-7 h-7" />
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
                                                <x-employee-avatar :employee="$reward->employee" size="w-7 h-7"
                                                    fallback="bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400" />
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
                                            <x-employee-avatar :employee="$emp" size="w-9 h-9" :initials="2"
                                                fallback="bg-rose-50 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400"
                                                class="ring-2 ring-rose-100 dark:ring-rose-900/50" />
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
                                <p class="text-sm font-medium text-emerald-600 dark:text-emerald-400">Không có nhân viên
                                    trong
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
                                <h3 class="font-semibold text-slate-900 dark:text-white text-sm">Top nhân viên vi phạm
                                    nhiều
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
                                        <th class="table-th" data-mcard-title>Nhân viên</th>
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
                                                    <x-employee-avatar :employee="$emp" :name="$emp?->name ?? '??'" size="w-8 h-8" :initials="2"
                                                        fallback="bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400" />
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
        </div>

        <div id="dash-panel-attendance" role="tabpanel" data-dash-tab-panel="attendance" class="hidden">
            {{-- ─── Chấm công hôm nay ───────────────────────────────────────────── --}}
            @can('view-attendance')
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                    <div class="card p-4 flex flex-col gap-0.5">
                        <p class="text-xs text-slate-400">Ca hôm nay</p>
                        <p class="text-2xl font-black text-slate-800 dark:text-white">{{ $scheduledTodayCount ?? 0 }}</p>
                    </div>
                    <div class="card p-4 flex flex-col gap-0.5 bg-emerald-50/60 dark:bg-emerald-900/20">
                        <p class="text-xs text-emerald-600 dark:text-emerald-400">Đã check-in</p>
                        <p class="text-2xl font-black text-emerald-700 dark:text-emerald-400">{{ $checkedInTodayCount ?? 0 }}
                        </p>
                    </div>
                    <div class="card p-4 flex flex-col gap-0.5">
                        <p class="text-xs text-slate-400">Đã check-out</p>
                        <p class="text-2xl font-black text-slate-800 dark:text-white">{{ $checkedOutTodayCount ?? 0 }}</p>
                    </div>
                    <div
                        class="card p-4 flex flex-col gap-0.5 {{ ($lateTodayCount ?? 0) > 0 ? 'bg-amber-50/60 dark:bg-amber-900/20' : '' }}">
                        <p
                            class="text-xs {{ ($lateTodayCount ?? 0) > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400' }}">
                            Đi trễ</p>
                        <p
                            class="text-2xl font-black {{ ($lateTodayCount ?? 0) > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-slate-800 dark:text-white' }}">
                            {{ $lateTodayCount ?? 0 }}</p>
                    </div>
                    <div
                        class="card p-4 flex flex-col gap-0.5 {{ ($notCheckedInTodayCount ?? 0) > 0 ? 'bg-rose-50/60 dark:bg-rose-900/20' : '' }}">
                        <p
                            class="text-xs {{ ($notCheckedInTodayCount ?? 0) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-400' }}">
                            Chưa check-in</p>
                        <p
                            class="text-2xl font-black {{ ($notCheckedInTodayCount ?? 0) > 0 ? 'text-rose-700 dark:text-rose-400' : 'text-slate-800 dark:text-white' }}">
                            {{ $notCheckedInTodayCount ?? 0 }}</p>
                    </div>
                </div>

                {{-- Charts --}}
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                    <div class="card">
                        <div class="card-header flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-sky-50 dark:bg-sky-900/30 flex items-center justify-center">
                                <i class="bi bi-pie-chart-fill text-sky-600 dark:text-sky-400"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold text-slate-900 dark:text-white text-sm">Trạng thái chấm công</h3>
                                <p class="text-xs text-slate-400">Hôm nay {{ $now->format('d/m/Y') }}</p>
                            </div>
                        </div>
                        <div class="card-body flex flex-col items-center gap-4">
                            <div style="width:160px;height:160px;">
                                <canvas id="attendanceStatusChart"></canvas>
                            </div>
                            <div class="w-full space-y-1.5">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="flex items-center gap-1.5 text-slate-500 dark:text-slate-400">
                                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block"></span> Đúng giờ
                                    </span>
                                    <span
                                        class="font-semibold text-slate-700 dark:text-slate-200">{{ $onTimeTodayCount ?? 0 }}</span>
                                </div>
                                <div class="flex items-center justify-between text-xs">
                                    <span class="flex items-center gap-1.5 text-slate-500 dark:text-slate-400">
                                        <span class="w-2.5 h-2.5 rounded-full bg-amber-400 inline-block"></span> Đi trễ
                                    </span>
                                    <span
                                        class="font-semibold text-slate-700 dark:text-slate-200">{{ $lateTodayCount ?? 0 }}</span>
                                </div>
                                <div class="flex items-center justify-between text-xs">
                                    <span class="flex items-center gap-1.5 text-slate-500 dark:text-slate-400">
                                        <span class="w-2.5 h-2.5 rounded-full bg-rose-500 inline-block"></span> Chưa check-in
                                    </span>
                                    <span
                                        class="font-semibold text-slate-700 dark:text-slate-200">{{ $notCheckedInTodayCount ?? 0 }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card lg:col-span-2">
                        <div class="card-header flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-pcrm-50 dark:bg-pcrm-900/30 flex items-center justify-center">
                                <i class="bi bi-graph-up text-pcrm-600 dark:text-pcrm-400"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold text-slate-900 dark:text-white text-sm">Xu hướng chấm công</h3>
                                <p class="text-xs text-slate-400">7 ngày gần nhất</p>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 220px;">
                                <canvas id="attendanceWeekChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Nhân viên đi trễ hôm nay --}}
                @if (isset($lateEmployeesToday) && $lateEmployeesToday->isNotEmpty())
                    <div class="card mb-6">
                        <div class="card-header flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center">
                                <i class="bi bi-alarm-fill text-amber-600 dark:text-amber-400"></i>
                            </div>
                            <h3 class="font-semibold text-slate-900 dark:text-white text-sm">Nhân viên đi trễ hôm nay</h3>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-container border-0 rounded-none">
                                <table class="table-base">
                                    <thead>
                                        <tr>
                                            <th class="table-th">Nhân viên</th>
                                            <th class="table-th">Đội nhóm</th>
                                            <th class="table-th">Chi nhánh</th>
                                            <th class="table-th text-center">Giờ vào</th>
                                            <th class="table-th text-center">Số phút trễ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($lateEmployeesToday as $log)
                                            <tr class="table-tr-hover">
                                                <td class="table-td font-medium">
                                                    <div class="flex items-center gap-2">
                                                        <x-employee-avatar :employee="$log->employee" size="w-7 h-7" />
                                                        <span>{{ $log->employee->name ?? 'N/A' }}</span>
                                                    </div>
                                                </td>
                                                <td class="table-td text-slate-500 dark:text-slate-400 text-sm">
                                                    {{ $log->employee->team->name ?? '—' }}</td>
                                                <td class="table-td text-slate-500 dark:text-slate-400 text-sm">
                                                    {{ $log->employee->branch->name ?? '—' }}</td>
                                                <td class="table-td text-center text-sm">
                                                    {{ $log->check_in_at?->format('H:i') ?? '—' }}</td>
                                                <td class="table-td text-center">
                                                    <span class="badge badge-warning">+{{ $log->late_minutes }} phút</span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif
            @endcan

            {{-- ─── Nhân viên đang trong ca làm (theo chi nhánh) ───────────────────── --}}
            @can('view-attendance')
                <div class="card mb-6">
                    <div class="card-header flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div
                                class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center">
                                <i class="bi bi-person-badge-fill text-emerald-600 dark:text-emerald-400"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold text-slate-900 dark:text-white text-sm">Nhân viên đang trong ca làm
                                </h3>
                                <p class="text-xs text-slate-400">Đã check-in, chưa check-out · Hôm nay
                                    {{ $now->format('d/m/Y') }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400">
                                <i class="bi bi-record-circle-fill text-xs animate-pulse"></i>
                                {{ $onShiftTotalCount ?? 0 }} đang làm việc
                            </span>
                            <a href="{{ route('attendance-logs.index') }}"
                                class="text-xs text-pcrm-600 dark:text-pcrm-400 hover:underline flex items-center gap-1">
                                Xem tất cả <i class="bi bi-arrow-right text-xs"></i>
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        @if (isset($onShiftByBranch) && $onShiftByBranch->isNotEmpty())
                            <div class="table-container border-0 rounded-none">
                                <table class="table-base">
                                    <thead>
                                        <tr>
                                            <th class="table-th">Nhân viên</th>
                                            <th class="table-th">Đội nhóm</th>
                                            <th class="table-th">Chi nhánh</th>
                                            <th class="table-th">Ca làm</th>
                                            <th class="table-th text-center">Giờ vào</th>
                                            <th class="table-th text-center">Trạng thái</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($onShiftByBranch as $branchName => $logs)
                                            @foreach ($logs as $log)
                                                <tr class="table-tr-hover">
                                                    <td class="table-td">
                                                        <div class="flex items-center gap-2">
                                                            <x-employee-avatar :employee="$log->employee" size="w-7 h-7"
                                                                fallback="bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400" />
                                                            <span
                                                                class="font-medium text-slate-800 dark:text-slate-200">{{ $log->employee->name ?? 'N/A' }}</span>
                                                        </div>
                                                    </td>
                                                    <td class="table-td text-slate-500 dark:text-slate-400 text-sm">
                                                        {{ $log->employee->team->name ?? '—' }}</td>
                                                    <td class="table-td text-slate-500 dark:text-slate-400 text-sm">
                                                        {{ $branchName }}</td>
                                                    <td class="table-td text-slate-500 dark:text-slate-400 text-sm">
                                                        {{ $log->shiftSchedule?->shift?->name ?? '—' }}</td>
                                                    <td class="table-td text-center text-sm">
                                                        {{ $log->check_in_at->format('H:i') }}</td>
                                                    <td class="table-td text-center">
                                                        <span class="badge badge-success inline-flex items-center gap-1">
                                                            <i class="bi bi-record-circle-fill" style="font-size:0.5rem"></i>
                                                            Đang làm
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="p-8 text-center">
                                <div
                                    class="w-12 h-12 mx-auto mb-3 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                                    <i class="bi bi-moon-stars text-xl text-slate-400"></i>
                                </div>
                                <p class="text-sm text-slate-500 dark:text-slate-400">Hiện không có nhân viên nào đang trong
                                    ca làm</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endcan
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
                default => 'linear-gradient(135deg, #e11d48 0%, #be123c 45%, #881337 100%)',
            };
        @endphp

        {{-- ─── Hero Welcome Banner ────────────────────────────────────────────── --}}
        <div class="rounded-2xl overflow-hidden mb-6 shadow-lg dash-hero" style="background: {{ $heroGradient }};">
            <div class="relative px-4 py-4 md:px-10 md:py-10 overflow-hidden ">

                {{-- Decorative blobs --}}
                <div class="absolute inset-0 pointer-events-none">
                    <div class="absolute -top-14 -right-14 w-60 h-60 rounded-full"
                        style="background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%)"></div>
                    <div class="absolute -bottom-12 left-1/4 w-48 h-48 rounded-full"
                        style="background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%)"></div>
                    <div class="absolute top-1/2 right-1/3 w-28 h-28 rounded-full"
                        style="background: radial-gradient(circle, rgba(255,255,255,0.06) 0%, transparent 70%)"></div>
                </div>

                <div class="relative grid grid-cols-1 md:grid-cols-4 gap-6 items-center">

                    {{-- Identity --}}
                    <div class="min-w-0 lg:col-span-2">
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
                                    {{ $employee->position->name }}
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

                    {{-- Chấm công nhanh — hoà chung một khối gradient với phần chào mừng --}}
                    @if ($employee)
                        @can('checkin-attendance')
                            <div class="lg:col-span-2">
                                <div class="flex items-center justify-between gap-3 mb-3">
                                    <div class="flex items-center gap-2 text-sm font-semibold text-white/90">
                                        <i class="bi bi-fingerprint"></i>
                                        Chấm công nhanh

                                    </div>
                                    <a href="{{ route('attendance.index') }}"
                                        class="text-xs text-white/70 hover:text-white transition-colors flex items-center gap-1 shrink-0">
                                        Xem đầy đủ <i class="bi bi-arrow-right text-xs"></i>
                                    </a>
                                </div>

                                <div class="space-y-2">
                                    @forelse ($todayShiftSchedules as $sched)
                                        @php
                                            $sid = $sched->id;
                                            $isMissed = $sched->isMissed();
                                            $effShift = $sched->effectiveShift();
                                            $schedOvernight = (bool) $effShift?->is_overnight;
                                            $checkBoundaries = [];
                                            if ($effShift && !$schedOvernight) {
                                                $checkBoundaries = [
                                                    'startTime' => $effShift ? substr($effShift->start_time, 0, 5) : '',
                                                    'endTime' => $effShift ? substr($effShift->end_time, 0, 5) : '',
                                                    'earlyCheckoutBoundary' => \Carbon\Carbon::parse(
                                                        $effShift->end_time,
                                                    )
                                                        ->subMinutes($effShift->grace_early_minutes ?? 0)
                                                        ->format('H:i'),
                                                ];
                                            }
                                        @endphp
                                        <div class="rounded-xl px-4 py-3 lg:px-8 lg:py-8 flex flex-col gap-2"
                                            style="background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.15);">
                                            <div class="text-center">
                                                <span
                                                    class="font-black text-4xl flex justify-center text-white items-center gap-1">
                                                    <span id="quickAttClock">{{ now()->format('H:i:s') }}</span>
                                                </span>
                                                <div class="flex items-center gap-2 w-full justify-center mt-1">
                                                    <p class="text-sm font-semibold text-white truncate">
                                                        {{ $sched->shift?->name ?? 'Ca linh hoạt' }}
                                                    </p>
                                                    @if ($sched->shift ? $sched->shift->isWfh() : $sched->custom_is_wfh)
                                                        <span
                                                            class="text-[10px] font-semibold text-white px-1.5 py-0.5 rounded-md shrink-0"
                                                            style="background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.25);">WFH</span>
                                                    @endif
                                                </div>
                                                <p class="text-xs mt-0.5" style="color:rgba(255,255,255,0.6)">
                                                    {{ substr($sched->shift?->start_time ?? ($sched->custom_start_time ?? ''), 0, 5) }}–{{ substr($sched->shift?->end_time ?? ($sched->custom_end_time ?? ''), 0, 5) }}
                                                    @if ($sched->attendanceLog?->check_in_at)
                                                        · Vào lúc
                                                        {{ $sched->attendanceLog->check_in_at->format('H:i') }}
                                                        @if ($sched->attendanceLog->late_minutes > 0)
                                                            <span
                                                                class="ml-1 text-white px-1.5 py-0.5 rounded text-[10px] font-semibold"
                                                                style="background:rgba(255,255,255,0.15);">trễ
                                                                {{ $sched->attendanceLog->late_minutes }}p</span>
                                                        @endif
                                                    @elseif ($isMissed)
                                                        <span
                                                            class="ml-1 text-white px-1.5 py-0.5 rounded text-[10px] font-semibold"
                                                            style="background:rgba(255,255,255,0.15);">đã kết thúc</span>
                                                    @endif
                                                </p>
                                            </div>

                                            <div id="attendanceMessage-{{ $sid }}"
                                                class="hidden mt-3 text-sm rounded-lg px-3 py-2 text-center"></div>

                                            @if ($isMissed)
                                                <p class="text-xs text-center" style="color:rgba(255,255,255,0.7)">
                                                    <i class="bi bi-exclamation-circle"></i>
                                                    Bạn chưa chấm công cho ca này. Liên hệ quản lý để được hỗ trợ.
                                                </p>
                                            @else
                                            <div class="grid grid-cols-2 gap-2 mt-3">
                                                @if ($sched->attendanceLog?->check_in_at)
                                                    <span class="quick-att-btn-done">
                                                        <i class="bi bi-check-circle-fill"></i> Đã check-in
                                                    </span>
                                                @else
                                                    <button id="btnCheckIn-{{ $sid }}"
                                                        onclick="quickDoAttendance('check-in', {{ $sid }}, {{ Illuminate\Support\Js::from($checkBoundaries) }})"
                                                        class="quick-att-btn">
                                                        <i class="bi bi-box-arrow-in-right"></i> Check-in
                                                    </button>
                                                @endif

                                                @if ($sched->attendanceLog?->check_out_at)
                                                    <span class="quick-att-btn-done">
                                                        <i class="bi bi-check-circle-fill"></i> Đã check-out
                                                    </span>
                                                @else
                                                    <button id="btnCheckOut-{{ $sid }}"
                                                        onclick="quickDoAttendance('check-out', {{ $sid }}, {{ Illuminate\Support\Js::from($checkBoundaries) }})"
                                                        class="quick-att-btn"
                                                        {{ !$sched->attendanceLog?->check_in_at ? 'disabled' : '' }}>
                                                        <i class="bi bi-box-arrow-right"></i> Check-out
                                                    </button>
                                                @endif
                                            </div>
                                            @endif
                                        </div>
                                    @empty
                                        <div class="rounded-xl px-4 py-3"
                                            style="background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.15);">
                                            <p class="text-sm font-semibold text-white text-center">Ca ngoài lịch</p>

                                            <div id="attendanceMessage-0"
                                                class="hidden mt-3 text-sm rounded-lg px-3 py-2 text-center"></div>

                                            <div class="grid grid-cols-2 gap-2 mt-3">
                                                @if ($unscheduledLog?->check_in_at)
                                                    <span class="quick-att-btn-done">
                                                        <i class="bi bi-check-circle-fill"></i> Đã check-in
                                                    </span>
                                                @else
                                                    <button id="btnCheckIn-0"
                                                        onclick="quickDoAttendance('check-in', null, {})"
                                                        class="quick-att-btn">
                                                        <i class="bi bi-box-arrow-in-right"></i> Check-in
                                                    </button>
                                                @endif

                                                @if ($unscheduledLog?->check_out_at)
                                                    <span class="quick-att-btn-done">
                                                        <i class="bi bi-check-circle-fill"></i> Đã check-out
                                                    </span>
                                                @else
                                                    <button id="btnCheckOut-0"
                                                        onclick="quickDoAttendance('check-out', null, {})"
                                                        class="quick-att-btn"
                                                        {{ !$unscheduledLog?->check_in_at ? 'disabled' : '' }}>
                                                        <i class="bi bi-box-arrow-right"></i> Check-out
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        @endcan
                    @endif
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
                                                        -{{ number_format($penalty->total_points_deducted) }}
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

                {{-- Thông báo của tôi --}}
                <div class="card dash-sect-r">
                    <div class="card-header flex items-center justify-between">
                        <div class="flex items-center gap-2 min-w-0">
                            <i class="bi bi-bell-fill text-indigo-500 shrink-0"></i>
                            <h3 class="font-semibold text-slate-900 dark:text-white truncate">
                                Thông báo của tôi
                            </h3>
                            @if (($unreadNotificationsCount ?? 0) > 0)
                                <span class="badge badge-danger shrink-0">{{ $unreadNotificationsCount }}</span>
                            @endif
                        </div>
                        <a href="{{ route('notifications.index') }}"
                            class="text-sm text-pcrm-600 dark:text-pcrm-400 hover:underline flex items-center gap-1 shrink-0 ml-3">
                            Xem tất cả <i class="bi bi-arrow-right text-xs"></i>
                        </a>
                    </div>
                    <div class="card-body p-0">
                        @if (isset($recentNotifications) && $recentNotifications->isNotEmpty())
                            <div class="divide-y divide-slate-100 dark:divide-slate-700/50">
                                @foreach ($recentNotifications as $notif)
                                    @php $isUnread = $notif->isUnread(); @endphp
                                    <a href="{{ route('notifications.show', $notif) }}"
                                        class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                                        <span
                                            class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 {{ $notif->typeColor() }}">
                                            <i class="bi {{ $notif->typeIcon() }} text-sm"></i>
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <p
                                                class="text-sm truncate
                                                {{ $isUnread
                                                    ? 'font-semibold text-slate-900 dark:text-white'
                                                    : 'font-medium text-slate-500 dark:text-slate-400' }}">
                                                {{ $notif->title }}
                                            </p>
                                            @if ($notif->body)
                                                <p class="text-xs text-slate-400 truncate mt-0.5">{{ $notif->body }}</p>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-2 shrink-0">
                                            @if ($isUnread)
                                                <span class="w-1.5 h-1.5 rounded-full bg-pcrm-500"></span>
                                            @endif
                                            <span
                                                class="text-xs text-slate-400">{{ $notif->created_at->diffForHumans(null, true, true) }}</span>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <div class="py-10 text-center">
                                <div
                                    class="w-14 h-14 mx-auto mb-3 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                                    <i class="bi bi-bell-slash text-2xl text-slate-400"></i>
                                </div>
                                <p class="text-sm text-slate-500 dark:text-slate-400">Chưa có thông báo nào</p>
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

@if (!$isAdmin)
    @can('checkin-attendance')
        @push('modals')
            <div id="quickEarlyConfirmModal"
                class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
                onclick="if(event.target===this)closeModal('quickEarlyConfirmModal')">
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-sm p-4 sm:p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div
                            class="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center shrink-0">
                            <i class="bi bi-clock-history text-amber-600 dark:text-amber-400"></i>
                        </div>
                        <h3 class="font-semibold text-slate-900 dark:text-white" id="quickEarlyConfirmTitle">Xác nhận</h3>
                    </div>
                    <p class="text-sm text-slate-700 dark:text-slate-300 mb-5" id="quickEarlyConfirmMessage"></p>
                    <div class="flex gap-3">
                        <button type="button" onclick="closeModal('quickEarlyConfirmModal')"
                            class="btn-secondary flex-1">Hủy</button>
                        <button type="button" id="quickEarlyConfirmProceedBtn" class="btn-primary flex-1">Xác
                            nhận</button>
                    </div>
                </div>
            </div>
        @endpush

        @push('scripts')
            <script>
                setInterval(function() {
                    var el = document.getElementById('quickAttClock');
                    if (el) el.textContent = new Date().toLocaleTimeString('vi-VN');
                }, 1000);

                function quickShowAttendanceMessage(shiftScheduleId, message, isError) {
                    const el = document.getElementById('attendanceMessage-' + (shiftScheduleId ?? 0));
                    if (!el) return;
                    el.textContent = message;
                    el.classList.remove('hidden', 'bg-red-50', 'text-red-700', 'bg-emerald-50', 'text-emerald-700');
                    el.classList.add(isError ? 'bg-red-50' : 'bg-emerald-50', isError ? 'text-red-700' : 'text-emerald-700');
                }

                function quickOpenEarlyConfirmModal(message, onConfirm) {
                    document.getElementById('quickEarlyConfirmMessage').textContent = message;
                    const btn = document.getElementById('quickEarlyConfirmProceedBtn');
                    const freshBtn = btn.cloneNode(true);
                    btn.parentNode.replaceChild(freshBtn, btn);
                    freshBtn.addEventListener('click', function() {
                        closeModal('quickEarlyConfirmModal');
                        onConfirm();
                    });
                    openModal('quickEarlyConfirmModal');
                }

                function quickDoAttendance(type, shiftScheduleId, boundaries) {
                    const suffix = shiftScheduleId ?? 0;
                    const btn = document.getElementById(
                        (type === 'check-in' ? 'btnCheckIn-' : 'btnCheckOut-') + suffix
                    );

                    function proceed() {
                        if (btn) btn.disabled = true;

                        function submit(lat, lng) {
                            fetch('{{ url('/attendance') }}/' + type, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                        'Accept': 'application/json',
                                    },
                                    body: JSON.stringify({
                                        lat: lat,
                                        lng: lng,
                                        shift_schedule_id: shiftScheduleId
                                    }),
                                })
                                .then(res => res.json().then(data => ({
                                    status: res.status,
                                    body: data
                                })))
                                .then(({
                                    status,
                                    body
                                }) => {
                                    quickShowAttendanceMessage(shiftScheduleId, body.message, status !== 200);
                                    if (status === 200) {
                                        setTimeout(() => window.location.reload(), 1000);
                                    } else if (btn) {
                                        btn.disabled = false;
                                    }
                                })
                                .catch(() => {
                                    quickShowAttendanceMessage(shiftScheduleId, 'Có lỗi xảy ra, vui lòng thử lại.', true);
                                    if (btn) btn.disabled = false;
                                });
                        }

                        if (!navigator.geolocation) {
                            submit(null, null);
                            return;
                        }

                        navigator.geolocation.getCurrentPosition(
                            pos => submit(pos.coords.latitude, pos.coords.longitude),
                            () => {
                                quickShowAttendanceMessage(shiftScheduleId,
                                    'Không thể lấy vị trí GPS. Vui lòng cấp quyền định vị cho trình duyệt.', true);
                                if (btn) btn.disabled = false;
                            }, {
                                enableHighAccuracy: true,
                                timeout: 10000
                            }
                        );
                    }

                    const nowHM = new Date().toTimeString().slice(0, 5);

                    if (type === 'check-out' && boundaries.earlyCheckoutBoundary && nowHM < boundaries
                        .earlyCheckoutBoundary) {
                        quickOpenEarlyConfirmModal(
                            `Hiện tại là ${nowHM}, ca kết thúc lúc ${boundaries.endTime}. Bạn có chắc muốn ra ca sớm không?`,
                            proceed
                        );
                        return;
                    }

                    proceed();
                }
            </script>
        @endpush
    @endcan
@endif

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
                    primary: isDark ? 'rgba(92,129,246,0.85)' : 'rgba(47,85,231,0.85)',
                    primaryLight: isDark ? 'rgba(92,129,246,0.15)' : 'rgba(47,85,231,0.1)',
                    reward: isDark ? 'rgba(52,211,153,0.85)' : 'rgba(22,138,99,0.85)',
                    rewardLight: isDark ? 'rgba(52,211,153,0.12)' : 'rgba(22,138,99,0.08)',
                    approved: isDark ? 'rgba(251,191,36,0.9)' : 'rgba(201,130,25,0.9)',
                    approvedLight: isDark ? 'rgba(251,191,36,0.1)' : 'rgba(201,130,25,0.08)',
                    grid: isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.05)',
                    tick: isDark ? '#94a3b8' : '#64748b',
                    tooltip: {
                        bg: isDark ? '#1e293b' : '#ffffff',
                        border: isDark ? '#334155' : '#e2e8f0',
                        text: isDark ? '#f1f5f9' : '#0f172a',
                    }
                };

                var pieColors = [
                    '#2F55E7', '#C98219', '#168A63', '#C94758',
                    '#6366f1', '#06b6d4', '#84cc16', '#ec4899'
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

                // ── Overview: Tỷ lệ chấm công tuần này (bar, hôm nay đậm hơn) ─────────────
                var attRate = @json($attendanceRateWeek ?? null);
                var ctxAtt = document.getElementById('attendanceRateWeekChart');
                if (ctxAtt && attRate) {
                    var todayIdx = typeof attRate.todayIndex !== 'undefined' ? attRate.todayIndex : (attRate.rates.length - 1);
                    var barColors = attRate.rates.map(function(_, i) {
                        if (i === todayIdx) return isDark ? '#5c81f6' : '#1D63ED';
                        return isDark ? 'rgba(92,129,246,0.55)' : '#5FA2F7';
                    });
                    var datalabelPlugins = window.ChartDataLabels ? [ChartDataLabels] : [];
                    new Chart(ctxAtt, {
                        type: 'bar',
                        plugins: datalabelPlugins,
                        data: {
                            labels: attRate.days.map(function(day, i) { return [day, attRate.dates[i]]; }),
                            datasets: [{
                                label: 'Tỷ lệ chấm công',
                                data: attRate.rates,
                                backgroundColor: barColors,
                                borderRadius: { topLeft: 4, topRight: 4 },
                                borderSkipped: false,
                                maxBarThickness: 44,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            layout: { padding: { top: 22 } },
                            plugins: {
                                legend: { display: false },
                                datalabels: {
                                    anchor: 'end',
                                    align: 'end',
                                    offset: 2,
                                    color: isDark ? '#e2e8f0' : '#344054',
                                    font: { weight: '600', size: 12 },
                                    formatter: function(v) { return v === null ? '' : v + '%'; },
                                },
                                tooltip: Object.assign({}, tooltipPlugin, {
                                    callbacks: {
                                        title: function(items) { return items[0].label.replace(',', ' '); },
                                        label: function(ctx) {
                                            var i = ctx.dataIndex;
                                            if (attRate.rates[i] === null) {
                                                if (todayIdx !== -1 && i > todayIdx) {
                                                    return ' Chưa diễn ra (' + (attRate.scheduled[i] || 0) + ' ca xếp)';
                                                }
                                                return ' Chưa có ca được xếp';
                                            }
                                            return ' ' + attRate.rates[i] + '% (' + attRate.checkedIn[i] + '/' + attRate.scheduled[i] + ' ca)';
                                        }
                                    }
                                }),
                            },
                            scales: {
                                x: {
                                    grid: { display: false },
                                    border: { display: false },
                                    ticks: {
                                        color: function(c) { return c.index === todayIdx ? (isDark ? '#f8fafc' : '#111827') : colors.tick; },
                                        font: function(c) { return { weight: c.index === todayIdx ? '700' : '400' }; },
                                    },
                                },
                                y: {
                                    min: 0,
                                    max: 100,
                                    ticks: { stepSize: 25, color: colors.tick, callback: function(v) { return v + '%'; } },
                                    grid: { color: colors.grid },
                                    border: { display: false },
                                },
                            },
                        },
                    });
                }

                // ── Overview: Xu hướng kỷ luật 30 ngày (line phạt/thưởng) ──────────────
                var disc = @json($disciplineTrend30 ?? null);
                var ctxDisc = document.getElementById('disciplineTrend30Chart');
                if (ctxDisc && disc) {
                    var penColor = isDark ? '#fb7185' : '#E5484D';
                    var rewColor = isDark ? '#34d399' : '#1F9D5C';
                    new Chart(ctxDisc, {
                        type: 'line',
                        data: {
                            labels: disc.labels,
                            datasets: [{
                                label: 'Phiếu phạt',
                                data: disc.penalties,
                                borderColor: penColor,
                                backgroundColor: isDark ? 'rgba(251,113,133,0.12)' : 'rgba(229,72,77,0.08)',
                                fill: true,
                                tension: 0.3,
                                borderWidth: 2,
                                pointRadius: 2.5,
                                pointBackgroundColor: '#fff',
                                pointBorderColor: penColor,
                            }, {
                                label: 'Phiếu thưởng',
                                data: disc.rewards,
                                borderColor: rewColor,
                                backgroundColor: isDark ? 'rgba(52,211,153,0.1)' : 'rgba(31,157,92,0.07)',
                                fill: true,
                                tension: 0.3,
                                borderWidth: 2,
                                pointRadius: 2.5,
                                pointBackgroundColor: '#fff',
                                pointBorderColor: rewColor,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: { display: false },
                                datalabels: { display: false },
                                tooltip: tooltipPlugin,
                            },
                            scales: {
                                x: {
                                    grid: { display: false },
                                    border: { display: false },
                                    ticks: { color: colors.tick, maxRotation: 0, autoSkip: true, maxTicksLimit: 5 },
                                },
                                y: {
                                    beginAtZero: true,
                                    ticks: { color: colors.tick, precision: 0, maxTicksLimit: 5 },
                                    grid: { color: colors.grid },
                                    border: { display: false },
                                },
                            },
                        },
                    });
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
                                borderColor: isDark ? 'rgba(92,129,246,0.9)' : 'rgba(47,85,231,0.9)',
                                backgroundColor: isDark ? 'rgba(92,129,246,0.15)' : 'rgba(47,85,231,0.08)',
                                borderWidth: 2.5,
                                pointRadius: 4,
                                pointBackgroundColor: isDark ? '#5c81f6' : '#2F55E7',
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
                                    borderColor: isDark ? 'rgba(201,71,88,0.85)' : 'rgba(201,71,88,0.8)',
                                    backgroundColor: isDark ? 'rgba(201,71,88,0.12)' : 'rgba(201,71,88,0.07)',
                                    borderWidth: 2,
                                    pointRadius: 2.5,
                                    pointBackgroundColor: isDark ? '#f87171' : '#C94758',
                                    tension: 0.3,
                                    fill: true
                                },
                                {
                                    label: 'Thưởng',
                                    data: daData.rewards,
                                    borderColor: isDark ? 'rgba(52,211,153,0.85)' : 'rgba(22,138,99,0.8)',
                                    backgroundColor: isDark ? 'rgba(52,211,153,0.10)' : 'rgba(22,138,99,0.07)',
                                    borderWidth: 2,
                                    pointRadius: 2.5,
                                    pointBackgroundColor: isDark ? '#34d399' : '#168a63',
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

                // ── Attendance Status Doughnut (hôm nay) ───────────────────────────
                var attStatusEl = document.getElementById('attendanceStatusChart');
                if (attStatusEl) {
                    new Chart(attStatusEl, {
                        type: 'doughnut',
                        data: {
                            labels: ['Đúng giờ', 'Đi trễ', 'Chưa check-in'],
                            datasets: [{
                                data: [{{ $onTimeTodayCount ?? 0 }}, {{ $lateTodayCount ?? 0 }},
                                    {{ $notCheckedInTodayCount ?? 0 }}
                                ],
                                backgroundColor: ['#168A63', '#C98219', '#C94758'],
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

                // ── Attendance Week Trend (7 ngày) ─────────────────────────────────
                var attWeekData = @json($attendanceWeekTrend ?? null);
                var attWeekEl = document.getElementById('attendanceWeekChart');
                if (attWeekEl && attWeekData) {
                    new Chart(attWeekEl, {
                        type: 'bar',
                        data: {
                            labels: attWeekData.labels,
                            datasets: [{
                                    label: 'Đã check-in',
                                    data: attWeekData.checkedIn,
                                    backgroundColor: isDark ? 'rgba(52,211,153,0.75)' : 'rgba(22,138,99,0.75)',
                                    borderRadius: 4,
                                    maxBarThickness: 28,
                                },
                                {
                                    label: 'Đi trễ',
                                    data: attWeekData.late,
                                    backgroundColor: isDark ? 'rgba(251,191,36,0.85)' : 'rgba(201,130,25,0.85)',
                                    borderRadius: 4,
                                    maxBarThickness: 28,
                                },
                            ],
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
                                            size: 11
                                        },
                                        padding: 10
                                    }
                                },
                                tooltip: ttBase,
                            },
                            scales: {
                                x: {
                                    grid: {
                                        display: false
                                    },
                                    ticks: {
                                        color: tickClr
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    grid: {
                                        color: gridClr
                                    },
                                    ticks: {
                                        color: tickClr,
                                        precision: 0
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
