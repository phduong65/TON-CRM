@extends('layouts.admin')

@section('title', 'Lịch vận hành & Định biên tuần')
@section('page-title', 'Lịch vận hành tuần')
@section('page-subtitle', 'Tình trạng đủ/thiếu nhân sự theo từng khung giờ vận hành, kèm lịch nghỉ và cảnh báo chấm công')
@section('breadcrumb', 'Ca làm việc & Chấm công')

@section('page-actions')
    <a href="{{ route('shift-coverage-requirements.index', ['branch_id' => $selectedBranchId]) }}" class="btn-secondary">
        <i class="bi bi-sliders"></i>
        <span>Cấu hình định biên</span>
    </a>
    <a href="{{ route('shift-schedules.index', ['branch_id' => $selectedBranchId, 'week' => $weekStart->toDateString()]) }}" class="btn-primary">
        <i class="bi bi-calendar-week"></i>
        <span>Lưới xếp ca</span>
    </a>
@endsection

@section('content')
@php
    $weekParams = fn(array $extra = []) => array_filter(array_merge([
        'branch_id' => $selectedBranchId,
        'team_id'   => $selectedTeamId,
        'week'      => $weekStart->toDateString(),
        'view'      => $viewMode,
    ], $extra), fn($v) => $v !== null && $v !== '');
    $isThisWeek = $weekStart->toDateString() === $thisWeek;
    $viewModes = [
        'table' => ['Bảng tuần', 'bi-grid-3x3'],
        'list'  => ['Danh sách', 'bi-list-ul'],
        'cards' => ['Theo ngày', 'bi-calendar2-range'],
    ];
@endphp

<div class="space-y-5">
    {{-- ─── Thanh điều khiển: chi nhánh · tuần · chế độ xem · bộ phận ─────────── --}}
    <div class="card">
        <div class="ops-toolbar">
            <form method="GET" action="{{ route('operational-schedule.index') }}" class="flex items-center gap-2">
                <input type="hidden" name="view" id="currentViewInput" value="{{ $viewMode }}">
                <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">
                <label for="opsBranch" class="sr-only">Chi nhánh</label>
                <div class="relative">
                    <i class="bi bi-building pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400" aria-hidden="true"></i>
                    <select id="opsBranch" name="branch_id" onchange="this.form.submit()" class="form-input h-10 w-auto min-w-[13rem] pl-8 text-sm"
                            @if ($branches->count() === 1) disabled @endif>
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}" @selected($selectedBranchId == $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <noscript><button type="submit" class="btn-secondary">Xem</button></noscript>
            </form>

            {{-- Điều hướng tuần --}}
            <div class="ops-week" role="group" aria-label="Chọn tuần">
                <a href="{{ route('operational-schedule.index', $weekParams(['week' => $prevWeek])) }}" class="ops-week-btn" title="Tuần trước" aria-label="Tuần trước">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <div class="ops-week-label font-heading">
                    <span class="font-semibold text-slate-900 dark:text-white tabular-nums">{{ $weekStart->format('d/m') }} – {{ $weekEnd->format('d/m/Y') }}</span>
                    @if ($isThisWeek)
                        <span class="text-xs text-[#2F55E7] dark:text-[#809ff9]">Tuần này</span>
                    @endif
                </div>
                <a href="{{ route('operational-schedule.index', $weekParams(['week' => $nextWeek])) }}" class="ops-week-btn" title="Tuần sau" aria-label="Tuần sau">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>
            @unless ($isThisWeek)
                <a href="{{ route('operational-schedule.index', $weekParams(['week' => $thisWeek])) }}" class="btn-ghost btn-sm font-heading">
                    <i class="bi bi-calendar-event"></i> Về tuần này
                </a>
            @endunless

            {{-- Chế độ xem --}}
            <div class="ops-views ml-auto" role="tablist" aria-label="Chế độ hiển thị">
                @foreach ($viewModes as $mode => [$label, $icon])
                    <button type="button" role="tab" id="viewBtn{{ ucfirst($mode) }}" onclick="switchOperationalView('{{ $mode }}')"
                            aria-selected="{{ $viewMode === $mode ? 'true' : 'false' }}" aria-controls="operational{{ ucfirst($mode) }}View"
                            class="ops-view-btn font-heading {{ $viewMode === $mode ? 'is-active' : '' }}">
                        <i class="bi {{ $icon }}" aria-hidden="true"></i> <span>{{ $label }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Bộ phận --}}
        @if ($teams->count() > 1 || $selectedTeamId === null)
            <div class="type-chips" role="group" aria-label="Lọc theo bộ phận">
                <a href="{{ route('operational-schedule.index', $weekParams(['team_id' => null])) }}"
                   class="type-chip font-heading {{ is_null($selectedTeamId) ? 'is-active' : '' }}" @if (is_null($selectedTeamId)) aria-current="true" @endif>
                    Tất cả bộ phận <span class="type-chip-count font-heading">{{ $teams->count() }}</span>
                </a>
                @foreach ($teams as $t)
                    <a href="{{ route('operational-schedule.index', $weekParams(['team_id' => $t->id])) }}"
                       class="type-chip font-heading {{ $selectedTeamId == $t->id ? 'is-active' : '' }}" @if ($selectedTeamId == $t->id) aria-current="true" @endif>
                        {{ $t->name }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ─── KPI tuần ───────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="stat-card dash-kpi">
            <span class="dash-kpi-icon {{ $totalShortageFramesWeek > 0 ? 'bg-[#fde8ea] text-[#C94758] dark:bg-[#C94758]/20 dark:text-[#fb7185]' : 'bg-[#e8f5ef] text-[#168A63] dark:bg-[#168A63]/20 dark:text-[#34d399]' }}" aria-hidden="true">
                <i class="bi {{ $totalShortageFramesWeek > 0 ? 'bi-exclamation-octagon-fill' : 'bi-shield-check' }}"></i>
            </span>
            <div class="min-w-0 flex-1">
                <p class="dash-kpi-label font-heading font-semibold">Khung giờ thiếu người</p>
                <div class="dash-kpi-value-row">
                    <p class="dash-kpi-value font-heading font-extrabold {{ $totalShortageFramesWeek > 0 ? '!text-[#C94758]' : '' }}">{{ $totalShortageFramesWeek }}</p>
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-heading">lượt khung / tuần</span>
                </div>
                <p class="dash-kpi-sub">{{ $totalShortageFramesWeek > 0 ? 'Cần bổ sung người vào các ô màu đỏ' : 'Mọi khung đều đạt tối thiểu' }}</p>
            </div>
        </div>

        <div class="stat-card dash-kpi">
            <span class="dash-kpi-icon bg-[#E9EEFF] text-[#2F55E7] dark:bg-[#2F55E7]/20 dark:text-[#809ff9]" aria-hidden="true">
                <i class="bi bi-calendar-check"></i>
            </span>
            <div class="min-w-0 flex-1">
                <p class="dash-kpi-label font-heading font-semibold">Nghỉ phép đã duyệt</p>
                <div class="dash-kpi-value-row">
                    <p class="dash-kpi-value font-heading font-extrabold">{{ $totalApprovedLeavesWeek }}</p>
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-heading">lượt trong tuần</span>
                </div>
                <p class="dash-kpi-sub">Đã trừ khỏi số người có mặt</p>
            </div>
        </div>

        <a href="{{ route('attendance-alerts.index', ['branch_id' => $selectedBranchId]) }}" class="stat-card dash-kpi group">
            <span class="dash-kpi-icon bg-[#fef3dc] text-[#C98219] dark:bg-[#C98219]/20 dark:text-[#fbbf24]" aria-hidden="true">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </span>
            <div class="min-w-0 flex-1">
                <p class="dash-kpi-label font-heading font-semibold">Cảnh báo chấm công mở</p>
                <i class="bi bi-chevron-right dash-kpi-chevron" aria-hidden="true"></i>
                <div class="dash-kpi-value-row">
                    <p class="dash-kpi-value font-heading font-extrabold {{ $openAlertsCount > 0 ? '!text-[#C98219]' : '' }}">{{ $openAlertsCount }}</p>
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-heading">ca thiếu log</span>
                </div>
                <p class="dash-kpi-sub">Xem &amp; xử lý cảnh báo</p>
            </div>
        </a>
    </div>

    @php
        $dayNameMap = [1 => 'Thứ 2', 2 => 'Thứ 3', 3 => 'Thứ 4', 4 => 'Thứ 5', 5 => 'Thứ 6', 6 => 'Thứ 7', 7 => 'Chủ nhật'];
        $filteredTeams = $selectedTeamId ? $teams->where('id', $selectedTeamId) : $teams;
        $statusClass = ['shortage' => 'is-shortage', 'minimum_met' => 'is-minimum', 'target_met' => 'is-ok'];
    @endphp

    {{-- ═══ VIEW 1: BẢNG TUẦN ═══════════════════════════════════════════════════ --}}
    <section id="operationalTableView" role="tabpanel" aria-labelledby="viewBtnTable" class="{{ $viewMode === 'table' ? '' : 'hidden' }}">
        <div class="card">
            <div class="card-header flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="font-heading font-bold text-base text-slate-900 dark:text-white">Ma trận định biên 7 ngày</h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Bấm vào ô để xem chi tiết nhân sự được xếp ca và khoảng giờ thiếu.</p>
                </div>
                <ul class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs" aria-label="Chú thích">
                    <li class="inline-flex items-center gap-1.5"><span class="cov-dot is-ok"></span> <span class="font-heading font-semibold text-slate-700 dark:text-slate-300">Đạt chuẩn</span> <span class="text-slate-400">(số người đã xếp)</span></li>
                    <li class="inline-flex items-center gap-1.5"><span class="cov-dot is-shortage"></span> <span class="font-heading font-bold text-[#C94758]">Thiếu người</span></li>
                </ul>
            </div>

            <div class="overflow-x-auto">
                <table class="cov-table" data-no-cards>
                    <caption class="sr-only">Ma trận định biên theo bộ phận và ngày trong tuần {{ $weekStart->format('d/m') }} – {{ $weekEnd->format('d/m/Y') }}</caption>
                    <thead>
                        <tr>
                            <th scope="col" class="cov-sticky font-heading font-bold text-slate-800 dark:text-slate-200">Bộ phận · khung định biên</th>
                            @foreach ($days as $day)
                                @php
                                    $isToday = $day->isToday();
                                    $dayShortages = 0;
                                    foreach (($dailyCoverage[$day->toDateString()]['team_results'] ?? []) as $tr) {
                                        foreach ($tr['frames'] as $fr) {
                                            if ($fr['analysis']['status'] === 'shortage') $dayShortages++;
                                        }
                                    }
                                @endphp
                                <th scope="col" class="cov-day font-heading {{ $isToday ? 'is-today' : '' }} {{ $day->isWeekend() ? 'is-weekend' : '' }}">
                                    <span class="block text-xs font-heading font-bold uppercase tracking-wider {{ $isToday ? 'text-pcrm-600 dark:text-pcrm-400' : 'text-slate-700 dark:text-slate-300' }}">{{ $dayNameMap[$day->dayOfWeekIso] }}</span>
                                    <span class="cov-day-date font-heading font-bold text-sm {{ $isToday ? 'text-pcrm-600 dark:text-pcrm-400' : 'text-slate-900 dark:text-white' }}">{{ $day->format('d/m') }}</span>
                                    @if ($dayShortages > 0)
                                        <span class="mt-1 block text-[11px] font-heading font-bold text-[#C94758]">Thiếu {{ $dayShortages }}</span>
                                    @elseif ($isToday)
                                        <span class="mt-1 block text-[10px] font-heading font-bold text-pcrm-600 dark:text-pcrm-400 uppercase tracking-wide">Hôm nay</span>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($filteredTeams as $team)
                            @php $reqs = $teamRequirements[$team->id] ?? collect(); @endphp

                            <tr class="cov-team-row">
                                <th scope="rowgroup" colspan="{{ count($days) + 1 }}">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="flex items-center gap-2">
                                            <span class="w-2.5 h-2.5 rounded-full bg-pcrm-600"></span>
                                            <span class="font-heading font-bold text-slate-900 dark:text-white text-sm">{{ $team->name }}</span>
                                            <span class="text-xs font-normal text-slate-500 dark:text-slate-400 font-heading">({{ $reqs->count() }} khung định biên)</span>
                                        </span>
                                        <a href="{{ route('shift-schedules.index', ['branch_id' => $selectedBranchId, 'team_id' => $team->id, 'week' => $weekStart->toDateString()]) }}"
                                           class="text-xs font-heading font-semibold text-pcrm-600 hover:underline dark:text-pcrm-400">
                                            Xếp ca bộ phận <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                        </a>
                                    </div>
                                </th>
                            </tr>

                            @forelse ($reqs as $req)
                                <tr>
                                    <th scope="row" class="cov-sticky cov-req font-heading">
                                        <span class="block truncate font-heading font-bold text-slate-900 dark:text-white text-xs" title="{{ $req->name }}">{{ $req->name }}</span>
                                        <span class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs font-normal text-slate-500 dark:text-slate-400">
                                            <span class="font-mono font-medium">{{ substr($req->start_time, 0, 5) }}–{{ substr($req->end_time, 0, 5) }}</span>
                                            @if ($req->isOvernight())<span class="badge-warning font-heading !py-0 !px-1.5 !text-[10px]">Qua đêm</span>@endif
                                        </span>
                                    </th>

                                    @foreach ($days as $day)
                                        @php
                                            $dateStr = $day->toDateString();
                                            $frameRes = null;
                                            $teamResForDay = null;
                                            foreach (($dailyCoverage[$dateStr]['team_results'] ?? []) as $tr) {
                                                if ($tr['team']->id === $team->id) {
                                                    $teamResForDay = $tr;
                                                    foreach ($tr['frames'] as $fr) {
                                                        if ($fr['requirement']->id === $req->id) { $frameRes = $fr; break 2; }
                                                    }
                                                }
                                            }
                                        @endphp
                                        <td class="cov-cell {{ $day->isToday() ? 'is-today' : '' }}">
                                            @if ($frameRes)
                                                @php
                                                    $an = $frameRes['analysis'];
                                                    $status = $an['status'];
                                                    $missing = max(0, $an['minimum_staff'] - $an['scheduled_coverage']);
                                                    $detailPayload = [
                                                        'date_formatted'      => $dayNameMap[$day->dayOfWeekIso] . ', ' . $day->format('d/m/Y'),
                                                        'date_str'            => $dateStr,
                                                        'team_name'           => $team->name,
                                                        'team_id'             => $team->id,
                                                        'req_name'            => $req->name,
                                                        'time_range'          => substr($req->start_time, 0, 5) . ' – ' . substr($req->end_time, 0, 5) . ($req->isOvernight() ? ' (qua đêm)' : ''),
                                                        'status'              => $status,
                                                        'status_label'        => $an['status_label'],
                                                        'minimum_staff'       => $an['minimum_staff'],
                                                        'target_staff'        => $an['target_staff'],
                                                        'scheduled_coverage'  => $an['scheduled_coverage'],
                                                        'actual_coverage'     => $an['actual_coverage'] ?? 0,
                                                        'shortage_intervals'  => $an['shortage_intervals'],
                                                        'scheduled_employees' => $frameRes['scheduled_employees'] ?? [],
                                                        'approved_leaves'     => ($teamResForDay['approved_leaves'] ?? collect())->map(fn($l) => [
                                                            'name' => $l->employee?->name,
                                                            'type' => $l->is_partial_day ? ($l->from_time ? substr($l->from_time, 0, 5) . '–' . substr($l->to_time, 0, 5) : 'Nửa ngày') : 'Cả ngày',
                                                        ])->values()->all(),
                                                        'pending_leaves'      => ($teamResForDay['pending_leaves'] ?? collect())->map(fn($l) => [
                                                            'name' => $l->employee?->name,
                                                            'type' => $l->is_partial_day ? 'Nghỉ theo giờ' : 'Cả ngày',
                                                        ])->values()->all(),
                                                        'schedule_url'        => route('shift-schedules.index', ['branch_id' => $selectedBranchId, 'team_id' => $team->id, 'week' => $dateStr]),
                                                    ];
                                                    $statusText = $status === 'shortage' ? 'Thiếu ' . $missing . ' người' : 'Đã xếp ' . $an['scheduled_coverage'] . ' người';
                                                @endphp
                                                <button type="button" class="cov-chip {{ $statusClass[$status] ?? 'is-ok' }}"
                                                        onclick="openOperationalDetailModal({{ json_encode($detailPayload) }})"
                                                        title="{{ $status === 'shortage' ? 'Thiếu ' . $missing . ' người (Xếp ca: ' . $an['scheduled_coverage'] . '/' . $an['minimum_staff'] . ')' : 'Đã xếp: ' . $an['scheduled_coverage'] . ' người' }}"
                                                        aria-label="{{ $team->name }}, {{ $req->name }}, {{ $dayNameMap[$day->dayOfWeekIso] }} {{ $day->format('d/m') }}: {{ $statusText }}">
                                                    @if ($status === 'shortage')
                                                        <span class="cov-chip-value font-heading"><i class="bi bi-exclamation-triangle-fill text-[10px]"></i> {{ $an['scheduled_coverage'] }}/{{ $an['minimum_staff'] }}</span>
                                                        <span class="cov-chip-label font-heading">Thiếu {{ $missing }}</span>
                                                    @else
                                                        <span class="cov-chip-value font-heading">{{ $an['scheduled_coverage'] }}</span>
                                                    @endif
                                                </button>
                                            @else
                                                <span class="text-slate-300 dark:text-slate-600 font-mono" aria-label="Không áp dụng">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($days) + 1 }}" class="px-4 py-2.5 text-xs italic text-slate-400 dark:text-slate-500 font-heading">
                                        Chưa cấu hình khung định biên cho bộ phận này.
                                        <a href="{{ route('shift-coverage-requirements.index', ['branch_id' => $selectedBranchId]) }}" class="not-italic font-semibold text-pcrm-600 hover:underline dark:text-pcrm-400 ml-1">Cấu hình ngay</a>
                                    </td>
                                </tr>
                            @endforelse

                            <tr class="cov-summary-row">
                                <th scope="row" class="cov-sticky">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-heading font-bold text-slate-700 dark:text-slate-300">
                                        <i class="bi bi-people" aria-hidden="true"></i> Đã xếp ca
                                    </span>
                                </th>
                                @foreach ($days as $day)
                                    @php
                                        $tCount = 0; $lCount = 0;
                                        foreach (($dailyCoverage[$day->toDateString()]['team_results'] ?? []) as $tr) {
                                            if ($tr['team']->id === $team->id) { $tCount = $tr['scheduled_count']; $lCount = $tr['approved_leaves']->count(); break; }
                                        }
                                    @endphp
                                    <td class="cov-cell text-xs {{ $day->isToday() ? 'is-today' : '' }}">
                                        <span class="font-heading font-bold text-slate-900 dark:text-white tabular-nums text-xs">{{ $tCount }}</span>
                                        @if ($lCount > 0)<span class="block text-[10px] text-pcrm-600 dark:text-pcrm-400 font-heading font-medium">({{ $lCount }} phép)</span>@endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($days) + 1 }}" class="py-12 text-center text-sm text-slate-500 dark:text-slate-400 font-heading">
                                    <i class="bi bi-diagram-3 mb-2 block text-3xl text-slate-300" aria-hidden="true"></i>
                                    Chi nhánh này chưa có bộ phận đang hoạt động.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row" class="cov-sticky font-heading font-bold text-slate-800 dark:text-slate-200">Khung thiếu trong ngày</th>
                            @foreach ($days as $day)
                                @php
                                    $totShortage = 0;
                                    foreach (($dailyCoverage[$day->toDateString()]['team_results'] ?? []) as $tr) {
                                        foreach ($tr['frames'] as $fr) {
                                            if ($fr['analysis']['status'] === 'shortage') $totShortage++;
                                        }
                                    }
                                @endphp
                                <td class="cov-cell {{ $day->isToday() ? 'is-today' : '' }}">
                                    @if ($totShortage > 0)
                                        <span class="badge-danger font-heading font-bold text-xs px-2 py-0.5">{{ $totShortage }} thiếu</span>
                                    @else
                                        <span class="text-slate-300 dark:text-slate-600 font-heading font-medium text-xs">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </section>

    <!-- ========================================== -->
    <!-- VIEW 2: DẠNG DANH SÁCH (COMPACT LIST VIEW) -->
    <!-- ========================================== -->
    <div id="operationalListView" class="{{ $viewMode === 'list' ? '' : 'hidden' }} space-y-4">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200/80 dark:border-slate-700/80 overflow-hidden">
            <!-- List View Controls -->
            <div class="px-5 py-3 bg-slate-50 dark:bg-slate-700/40 border-b border-slate-200 dark:border-slate-700 flex flex-wrap items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-2">
                    <span class="font-heading font-bold text-slate-800 dark:text-white flex items-center gap-1.5">
                        <i class="bi bi-list-check text-pcrm-600"></i> Danh sách tất cả khung vận hành trong tuần
                    </span>
                </div>

                <!-- Quick Shortage Filter Toggle -->
                <div class="flex items-center gap-2">
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none text-xs font-semibold text-slate-700 dark:text-slate-300">
                        <input type="checkbox" id="shortageOnlyCheckbox" onchange="filterListViewShortages(this.checked)"
                            class="rounded border-slate-300 dark:border-slate-600 text-red-600 focus:ring-red-500 h-4 w-4">
                        <span class="text-red-600 dark:text-red-400 font-heading font-bold flex items-center gap-1">
                            <i class="bi bi-exclamation-triangle"></i> Chỉ hiện khung thiếu người
                        </span>
                    </label>
                </div>
            </div>

            <!-- List Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left border-collapse" id="operationalListTable">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-750/50 text-slate-700 dark:text-slate-300 font-heading font-bold">
                            <th class="py-2.5 px-3 font-heading font-bold">Ngày & Thứ</th>
                            <th class="py-2.5 px-3 font-heading font-bold">Bộ phận</th>
                            <th class="py-2.5 px-3 font-heading font-bold">Khung định biên</th>
                            <th class="py-2.5 px-3 font-heading font-bold">Thời gian</th>
                            <th class="py-2.5 px-3 text-center font-heading font-bold">Xếp ca</th>
                            <th class="py-2.5 px-3 font-heading font-bold">Khoảng giờ thiếu hụt</th>
                            <th class="py-2.5 px-3 text-center font-heading font-bold">Trạng thái</th>
                            <th class="py-2.5 px-3 text-center font-heading font-bold">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        @php
                            $hasAnyListRow = false;
                        @endphp
                        @foreach($days as $day)
                            @php
                                $dateStr = $day->toDateString();
                                $dayData = $dailyCoverage[$dateStr] ?? null;
                            @endphp

                            @if($dayData)
                                @foreach($dayData['team_results'] as $tRes)
                                    @if(is_null($selectedTeamId) || $selectedTeamId == $tRes['team']->id)
                                        @foreach($tRes['frames'] as $fr)
                                            @php
                                                $hasAnyListRow = true;
                                                $req = $fr['requirement'];
                                                $an = $fr['analysis'];
                                                $status = $an['status'];

                                                $detailPayload = [
                                                    'date_formatted'      => $dayNameMap[$day->dayOfWeekIso].', '.$day->format('d/m/Y'),
                                                    'date_str'            => $dateStr,
                                                    'team_name'           => $tRes['team']->name,
                                                    'team_id'             => $tRes['team']->id,
                                                    'req_name'            => $req->name,
                                                    'time_range'          => substr($req->start_time, 0, 5).' – '.substr($req->end_time, 0, 5).($req->isOvernight() ? ' (Qua đêm)' : ''),
                                                    'status'              => $status,
                                                    'status_label'        => $an['status_label'],
                                                    'minimum_staff'       => $an['minimum_staff'],
                                                    'target_staff'        => $an['target_staff'],
                                                    'scheduled_coverage'  => $an['scheduled_coverage'],
                                                    'actual_coverage'     => $an['actual_coverage'] ?? 0,
                                                    'shortage_intervals'  => $an['shortage_intervals'],
                                                    'scheduled_employees' => $fr['scheduled_employees'] ?? [],
                                                    'approved_leaves'     => $tRes['approved_leaves']->map(fn($l) => [
                                                        'name' => $l->employee?->name,
                                                        'type' => $l->is_partial_day ? ($l->from_time ? substr($l->from_time, 0, 5).'–'.substr($l->to_time, 0, 5) : 'Nửa ngày') : 'Cả ngày',
                                                    ])->values()->all(),
                                                    'pending_leaves'      => $tRes['pending_leaves']->map(fn($l) => [
                                                        'name' => $l->employee?->name,
                                                        'type' => $l->is_partial_day ? 'Nghỉ theo giờ' : 'Cả ngày',
                                                    ])->values()->all(),
                                                    'schedule_url'        => route('shift-schedules.index', ['branch_id' => $selectedBranchId, 'team_id' => $tRes['team']->id, 'week' => $dateStr]),
                                                ];
                                            @endphp

                                            <tr class="list-item-row hover:bg-slate-50/60 dark:hover:bg-slate-750/30 transition-colors {{ $day->isToday() ? 'bg-pcrm-50/10' : '' }}"
                                                data-shortage="{{ $status === 'shortage' ? '1' : '0' }}">
                                                
                                                <!-- Ngày & Thứ -->
                                                <td class="py-2.5 px-3 font-heading font-bold text-slate-800 dark:text-slate-200">
                                                    <div class="flex items-center gap-1.5">
                                                        <span class="font-heading">{{ $dayNameMap[$day->dayOfWeekIso] }}</span>
                                                        <span class="text-slate-400 font-heading font-normal">({{ $day->format('d/m') }})</span>
                                                        @if($day->isToday())
                                                            <span class="px-1.5 py-0.2 rounded text-[9px] font-heading font-bold bg-pcrm-600 text-white">Nay</span>
                                                        @endif
                                                    </div>
                                                </td>

                                                <!-- Bộ phận -->
                                                <td class="py-2.5 px-3">
                                                    <span class="px-2 py-0.5 rounded-lg text-[11px] font-heading font-bold bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                                                        {{ $tRes['team']->name }}
                                                    </span>
                                                </td>

                                                <!-- Khung định biên -->
                                                <td class="py-2.5 px-3 font-heading font-bold text-slate-900 dark:text-white">
                                                    {{ $req->name }}
                                                </td>

                                                <!-- Thời gian -->
                                                <td class="py-2.5 px-3 font-mono text-slate-600 dark:text-slate-400 text-[11px]">
                                                    {{ substr($req->start_time, 0, 5) }} – {{ substr($req->end_time, 0, 5) }}
                                                    @if($req->isOvernight())
                                                        <span class="text-[9px] px-1 rounded bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 font-heading">Qua đêm</span>
                                                    @endif
                                                </td>

                                                <!-- Xếp ca -->
                                                <td class="py-2.5 px-3 text-center">
                                                    @if ($status === 'shortage')
                                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-heading font-bold bg-[#fde8ea] text-[#b91c1c] border border-[#c94758]/35">
                                                            <i class="bi bi-exclamation-triangle-fill text-[10px]"></i> {{ $an['scheduled_coverage'] }} người
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-heading font-bold bg-[#e8f5ef] text-[#0f7654] border border-[#168a63]/25">
                                                            {{ $an['scheduled_coverage'] }} người
                                                        </span>
                                                    @endif
                                                </td>

                                                <!-- Khoảng giờ thiếu hụt -->
                                                <td class="py-2.5 px-3">
                                                    @if(count($an['shortage_intervals']) > 0)
                                                        <div class="space-y-0.5">
                                                            @foreach($an['shortage_intervals'] as $sInt)
                                                                <span class="inline-flex items-center gap-1 font-mono text-[10px] text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/40 px-1.5 py-0.5 rounded border border-red-200 dark:border-red-900/50">
                                                                    {{ $sInt['start_formatted'] }}–{{ $sInt['end_formatted'] }}: {{ $sInt['concurrent'] }}/{{ $an['minimum_staff'] }}
                                                                </span>
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        <span class="text-slate-400 text-[11px] font-heading">Không có</span>
                                                    @endif
                                                </td>

                                                <!-- Trạng thái -->
                                                <td class="py-2.5 px-3 text-center">
                                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-heading font-bold inline-block
                                                        {{ $status === 'shortage' ? 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300' : ($status === 'minimum_met' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300') }}">
                                                        {{ $an['status_label'] }}
                                                    </span>
                                                </td>

                                                <!-- Thao tác -->
                                                <td class="py-2.5 px-3 text-center">
                                                    <div class="flex items-center justify-center gap-1.5">
                                                        <button type="button" onclick="openOperationalDetailModal({{ json_encode($detailPayload) }})"
                                                            class="px-2.5 py-1 text-xs font-heading font-semibold text-pcrm-600 dark:text-pcrm-400 hover:bg-pcrm-50 dark:hover:bg-slate-700 rounded-lg transition-colors"
                                                            title="Xem danh sách nhân sự">
                                                            <i class="bi bi-people"></i> Chi tiết
                                                        </button>
                                                        <a href="{{ route('shift-schedules.index', ['branch_id' => $selectedBranchId, 'team_id' => $tRes['team']->id, 'week' => $dateStr]) }}"
                                                            class="p-1 text-slate-500 hover:text-pcrm-600 dark:hover:text-pcrm-400 rounded transition-colors"
                                                            title="Xếp ca ngày này">
                                                            <i class="bi bi-calendar-plus"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endif
                                @endforeach
                            @endif
                        @endforeach

                        @if(!$hasAnyListRow)
                            <tr>
                                <td colspan="9" class="py-8 text-center text-slate-400 text-xs">
                                    Không có khung định biên nào phù hợp với bộ lọc.
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- VIEW 3: DẠNG THẺ CHI TIẾT (CARDS VIEW) -->
    <!-- ========================================== -->
    <div id="operationalCardsView" class="{{ $viewMode === 'cards' ? '' : 'hidden' }} space-y-6">
        @foreach($days as $day)
            @php
                $dateStr = $day->toDateString();
                $dayData = $dailyCoverage[$dateStr] ?? null;
                $isToday = $day->isToday();
            @endphp

            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border {{ $isToday ? 'border-pcrm-500 ring-2 ring-pcrm-500/20' : 'border-slate-200/80 dark:border-slate-700/80' }} overflow-hidden">
                <!-- Day Header -->
                <div class="px-5 py-3.5 bg-slate-50/80 dark:bg-slate-700/40 border-b border-slate-200/80 dark:border-slate-700/80 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl {{ $isToday ? 'bg-pcrm-600 text-white font-bold' : 'bg-slate-200 dark:bg-slate-600 text-slate-700 dark:text-slate-200 font-semibold' }} flex items-center justify-center text-sm shadow-sm">
                            {{ $day->format('d') }}
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-heading font-bold text-slate-900 dark:text-white text-sm">
                                    {{ $dayNameMap[$day->dayOfWeekIso] ?? '' }} — {{ $day->format('d/m/Y') }}
                                </h3>
                                @if($isToday)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-heading font-bold bg-pcrm-100 text-pcrm-800 dark:bg-pcrm-900/50 dark:text-pcrm-300">
                                        HÔM NAY
                                    </span>
                                @elseif($day->isPast())
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-heading font-medium bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400">
                                        Đã qua
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Day stats summary -->
                    <div class="flex items-center gap-2 text-xs">
                        @php
                            $dayShortageCount = 0;
                            if ($dayData) {
                                foreach ($dayData['team_results'] as $tr) {
                                    if (is_null($selectedTeamId) || $selectedTeamId == $tr['team']->id) {
                                        foreach ($tr['frames'] as $fr) {
                                            if ($fr['analysis']['status'] === 'shortage') $dayShortageCount++;
                                        }
                                    }
                                }
                            }
                        @endphp

                        @if($dayShortageCount > 0)
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-heading font-bold bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300">
                                <i class="bi bi-exclamation-triangle-fill text-[11px]"></i>
                                Thiếu người ở {{ $dayShortageCount }} khung
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-heading font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">
                                <i class="bi bi-check-circle-fill text-[11px]"></i>
                                Định biên đảm bảo
                            </span>
                        @endif
                    </div>
                </div>

                <!-- Teams Content for this day -->
                <div class="p-5 space-y-6 divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($dayData['team_results'] ?? [] as $tIdx => $tRes)
                        @if(is_null($selectedTeamId) || $selectedTeamId == $tRes['team']->id)
                            <div class="{{ $tIdx > 0 ? 'pt-6' : '' }} space-y-4">
                                <!-- Team Title -->
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2.5 h-2.5 rounded-full bg-pcrm-500"></span>
                                        <h4 class="font-heading font-bold text-slate-800 dark:text-slate-100 text-sm">
                                            Bộ phận: {{ $tRes['team']->name }}
                                        </h4>
                                        <span class="text-xs text-slate-400 font-heading">
                                            ({{ $tRes['scheduled_count'] }} nhân sự được xếp ca)
                                        </span>
                                    </div>
                                    <a href="{{ route('shift-schedules.index', ['branch_id' => $selectedBranchId, 'team_id' => $tRes['team']->id, 'week' => $dateStr]) }}"
                                        class="text-xs font-heading font-medium text-pcrm-600 dark:text-pcrm-400 hover:underline">
                                        Mở lưới xếp ca &rarr;
                                    </a>
                                </div>

                                <!-- Operating Frames (Coverage Requirements) -->
                                @if(count($tRes['frames']) > 0)
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                        @foreach($tRes['frames'] as $fRes)
                                            @php
                                                $req = $fRes['requirement'];
                                                $an = $fRes['analysis'];
                                            @endphp
                                            <div class="rounded-xl border p-3.5 transition-all {{ $an['status'] === 'shortage' ? 'bg-red-50/40 dark:bg-red-950/20 border-red-200 dark:border-red-900/50' : ($an['status'] === 'minimum_met' ? 'bg-amber-50/40 dark:bg-amber-950/20 border-amber-200 dark:border-amber-900/50' : 'bg-slate-50/50 dark:bg-slate-750/30 border-slate-200 dark:border-slate-700') }}">
                                                <div class="flex items-start justify-between gap-2">
                                                    <div>
                                                        <h5 class="font-heading font-bold text-slate-900 dark:text-white text-xs">
                                                             {{ $req->name }}
                                                        </h5>
                                                        <p class="text-[11px] font-mono text-slate-500 dark:text-slate-400 mt-0.5">
                                                            {{ substr($req->start_time, 0, 5) }} – {{ substr($req->end_time, 0, 5) }}
                                                            @if($req->isOvernight())
                                                                <span class="text-[9px] px-1 rounded bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300 font-heading">Qua đêm</span>
                                                            @endif
                                                        </p>
                                                    </div>

                                                    <!-- Status Badge -->
                                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-heading font-bold shrink-0 {{ $an['status'] === 'shortage' ? 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300' : ($an['status'] === 'minimum_met' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300') }}">
                                                        {{ $an['status_label'] }}
                                                    </span>
                                                </div>

                                                <!-- Staffing Metrics -->
                                                <div class="grid grid-cols-3 gap-2 mt-3 pt-2.5 border-t border-slate-200/60 dark:border-slate-700/60 text-center">
                                                    <div>
                                                        <span class="block text-[10px] text-slate-400 uppercase tracking-wider font-heading font-semibold">Tối thiểu</span>
                                                        <span class="font-heading font-bold text-xs text-slate-700 dark:text-slate-200">{{ $an['minimum_staff'] }}</span>
                                                    </div>
                                                    <div>
                                                        <span class="block text-[10px] text-slate-400 uppercase tracking-wider font-heading font-semibold">Mục tiêu</span>
                                                        <span class="font-heading font-bold text-xs text-slate-700 dark:text-slate-200">{{ $an['target_staff'] }}</span>
                                                    </div>
                                                    <div>
                                                        <span class="block text-[10px] text-slate-400 uppercase tracking-wider font-heading font-semibold">Bao phủ</span>
                                                        <span class="font-heading font-bold text-xs {{ $an['status'] === 'shortage' ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                                            {{ $an['scheduled_coverage'] }} người
                                                        </span>
                                                    </div>
                                                </div>

                                                @if($day->isPast() || $isToday)
                                                    <div class="mt-2 text-[10px] text-slate-500 dark:text-slate-400 flex items-center justify-between font-heading">
                                                        <span>Thực tế check-in đồng thời:</span>
                                                        <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $an['actual_coverage'] }} người</span>
                                                    </div>
                                                @endif

                                                <!-- Shortage intervals if any -->
                                                @if(count($an['shortage_intervals']) > 0)
                                                    <div class="mt-2.5 pt-2 border-t border-red-200/60 dark:border-red-900/40 text-[11px] text-red-600 dark:text-red-400">
                                                        <span class="font-heading font-semibold block mb-1">Đoạn giờ thiếu người:</span>
                                                        <ul class="space-y-0.5">
                                                            @foreach($an['shortage_intervals'] as $sInt)
                                                                <li class="flex items-center justify-between font-mono text-[10px]">
                                                                    <span>{{ $sInt['start_formatted'] }} – {{ $sInt['end_formatted'] }}:</span>
                                                                    <span class="font-heading font-bold">{{ $sInt['concurrent'] }} / {{ $an['minimum_staff'] }} người</span>
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="p-3 bg-slate-50 dark:bg-slate-750/30 rounded-xl text-xs text-slate-400 italic font-heading">
                                        Chưa cấu hình quy tắc định biên cho bộ phận này vào ngày này.
                                    </div>
                                @endif

                                <!-- Personnel breakdown accordion -->
                                <div class="pt-2">
                                    <details class="group bg-slate-50/60 dark:bg-slate-750/20 rounded-xl border border-slate-200/60 dark:border-slate-700/60">
                                        <summary class="px-4 py-2.5 text-xs font-heading font-semibold text-slate-700 dark:text-slate-300 cursor-pointer flex items-center justify-between select-none">
                                            <div class="flex items-center gap-3">
                                                <span><i class="bi bi-people text-slate-400 mr-1.5"></i> Danh sách nhân sự chi tiết trong ngày:</span>
                                                <span class="text-emerald-600 dark:text-emerald-400 font-medium">{{ count($tRes['scheduled_employees']) }} được xếp ca</span>
                                                <span class="text-slate-300 dark:text-slate-600">•</span>
                                                <span class="text-blue-600 dark:text-blue-400 font-medium">{{ $tRes['approved_leaves']->count() }} nghỉ đã duyệt</span>
                                                @if($tRes['pending_leaves']->count() > 0)
                                                    <span class="text-slate-300 dark:text-slate-600">•</span>
                                                    <span class="text-amber-600 dark:text-amber-400 font-medium">{{ $tRes['pending_leaves']->count() }} nghỉ chờ duyệt</span>
                                                @endif
                                                <span class="text-slate-300 dark:text-slate-600">•</span>
                                                <span class="text-slate-500 font-medium">{{ $tRes['unassigned_employees']->count() }} chưa xếp ca</span>
                                            </div>
                                            <i class="bi bi-chevron-down text-slate-400 group-open:rotate-180 transition-transform"></i>
                                        </summary>

                                        <div class="p-4 border-t border-slate-200/60 dark:border-slate-700/60 grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                                            <!-- Cột 1: Được xếp ca -->
                                            <div>
                                                <h6 class="font-heading font-bold text-slate-800 dark:text-slate-200 mb-2 flex items-center gap-1.5 text-xs">
                                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                                    Được xếp ca ({{ count($tRes['scheduled_employees']) }})
                                                </h6>
                                                <div class="space-y-1.5 max-h-48 overflow-y-auto pr-1">
                                                    @forelse($tRes['scheduled_employees'] as $sch)
                                                        <div class="p-2 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-between">
                                                            <div>
                                                                <p class="font-heading font-semibold text-slate-800 dark:text-slate-200">{{ $sch['employee']?->name }}</p>
                                                                <p class="text-[10px] text-slate-400">{{ $sch['shift_name'] }} ({{ $sch['time_range'] }})</p>
                                                            </div>
                                                            <div>
                                                                @if($sch['has_checked_in'])
                                                                    <span class="px-1.5 py-0.5 rounded text-[10px] bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 font-heading font-medium">Đã check-in</span>
                                                                @elseif($sch['is_missed'])
                                                                    <span class="px-1.5 py-0.5 rounded text-[10px] bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300 font-heading font-medium">Lỡ ca</span>
                                                                @else
                                                                    <span class="px-1.5 py-0.5 rounded text-[10px] bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400 font-heading font-medium">Theo lịch</span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    @empty
                                                        <p class="text-slate-400 text-xs italic font-heading">Chưa xếp ca nào.</p>
                                                    @endforelse
                                                </div>
                                            </div>

                                            <!-- Cột 2: Nghỉ phép (Đã duyệt & Chờ duyệt) -->
                                            <div>
                                                <h6 class="font-heading font-bold text-slate-800 dark:text-slate-200 mb-2 flex items-center gap-1.5 text-xs">
                                                    <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                                    Nghỉ phép ({{ $tRes['approved_leaves']->count() + $tRes['pending_leaves']->count() }})
                                                </h6>
                                                <div class="space-y-1.5 max-h-48 overflow-y-auto pr-1">
                                                    @foreach($tRes['approved_leaves'] as $al)
                                                        <div class="p-2 rounded-lg bg-blue-50/50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-900/50">
                                                            <div class="flex items-center justify-between">
                                                                <p class="font-heading font-semibold text-slate-800 dark:text-slate-200">{{ $al->employee?->name }}</p>
                                                                <span class="px-1.5 py-0.5 rounded text-[10px] bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 font-heading font-medium">Đã duyệt</span>
                                                            </div>
                                                            <p class="text-[10px] text-slate-400 mt-0.5">
                                                                {{ $al->is_partial_day ? ($al->from_time ? substr($al->from_time, 0, 5).'–'.substr($al->to_time, 0, 5) : 'Nửa ngày') : 'Cả ngày' }}
                                                            </p>
                                                        </div>
                                                    @endforeach

                                                    @foreach($tRes['pending_leaves'] as $pl)
                                                        <div class="p-2 rounded-lg bg-amber-50/50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/50">
                                                            <div class="flex items-center justify-between">
                                                                <p class="font-heading font-semibold text-slate-800 dark:text-slate-200">{{ $pl->employee?->name }}</p>
                                                                <span class="px-1.5 py-0.5 rounded text-[10px] bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 font-heading font-medium">Chờ duyệt</span>
                                                            </div>
                                                            <p class="text-[10px] text-slate-400 mt-0.5">
                                                                {{ $pl->is_partial_day ? 'Nghỉ theo giờ' : 'Cả ngày' }} (chưa trừ định biên)
                                                            </p>
                                                        </div>
                                                    @endforeach

                                                    @if($tRes['approved_leaves']->isEmpty() && $tRes['pending_leaves']->isEmpty())
                                                        <p class="text-slate-400 text-xs italic font-heading">Không có ai nghỉ phép.</p>
                                                    @endif
                                                </div>
                                            </div>

                                            <!-- Cột 3: Chưa xếp ca -->
                                            <div>
                                                <h6 class="font-heading font-bold text-slate-800 dark:text-slate-200 mb-2 flex items-center gap-1.5 text-xs">
                                                    <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                                                    Chưa xếp ca ({{ $tRes['unassigned_employees']->count() }})
                                                </h6>
                                                <div class="space-y-1.5 max-h-48 overflow-y-auto pr-1">
                                                    @forelse($tRes['unassigned_employees'] as $ue)
                                                        <div class="p-2 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-between">
                                                            <p class="text-slate-700 dark:text-slate-300 font-heading font-medium">{{ $ue->name }}</p>
                                                            <span class="text-[10px] text-slate-400">{{ $ue->code }}</span>
                                                        </div>
                                                    @empty
                                                        <p class="text-slate-400 text-xs italic font-heading">Tất cả nhân viên đã được xếp ca.</p>
                                                    @endforelse
                                                </div>
                                            </div>
                                        </div>
                                    </details>
                                </div>
                            </div>
                        @endif
                    @empty
                        <div class="py-8 text-center text-slate-400 text-xs font-heading">
                            Không có dữ liệu bộ phận nào cho chi nhánh này.
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection

@push('modals')
{{-- Chi tiết 1 ô định biên (bộ phận × khung giờ × ngày) --}}
<div id="operationalDetailModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeOperationalDetailModal()">
    <div class="pcrm-dialog max-w-2xl" role="dialog" aria-modal="true" aria-labelledby="modalReqName">
        <div class="pcrm-dialog-head">
            <span id="modalStatusIconWrap" class="pcrm-dialog-icon" aria-hidden="true"><i id="modalStatusIcon" class="bi"></i></span>
            <div class="min-w-0 flex-1">
                <h3 id="modalReqName" class="pcrm-dialog-title font-heading font-bold">Khung định biên</h3>
                <p class="pcrm-dialog-sub font-heading"><span id="modalTeamBadge"></span> · <span id="modalDateTitle"></span> · <span id="modalTimeRange" class="font-mono"></span></p>
            </div>
            <button type="button" onclick="closeOperationalDetailModal()" class="pcrm-dialog-close" aria-label="Đóng"><i class="bi bi-x-lg"></i></button>
        </div>

        <div class="pcrm-dialog-body">
            <div id="modalStatusBanner" class="record-status" role="status">
                <div class="min-w-0 flex-1 text-sm">
                    <p id="modalStatusText" class="font-heading font-semibold text-slate-900 dark:text-white"></p>
                </div>
                <dl class="flex shrink-0 items-center gap-5 text-right text-sm">
                    <div>
                        <dt class="text-xs text-slate-500 dark:text-slate-400 font-heading">Đã xếp</dt>
                        <dd id="modalStaffScheduled" class="font-heading font-bold tabular-nums text-slate-900 dark:text-white"></dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500 dark:text-slate-400 font-heading">Tối thiểu / chuẩn</dt>
                        <dd id="modalStaffTargets" class="font-heading font-bold tabular-nums text-slate-900 dark:text-white"></dd>
                    </div>
                </dl>
            </div>

            <div id="modalShortageContainer" class="hidden">
                <h4 class="mb-2 text-sm font-heading font-bold text-[#C94758]"><i class="bi bi-exclamation-octagon" aria-hidden="true"></i> Khoảng giờ thiếu người</h4>
                <ul id="modalShortageList" class="divide-y divide-slate-100 dark:divide-slate-700/60 rounded-xl border border-[#C94758]/25 text-sm"></ul>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <section>
                    <h4 class="mb-2 flex items-center justify-between text-sm font-heading font-bold text-slate-900 dark:text-white">
                        <span class="inline-flex items-center gap-1.5"><span class="cov-dot is-ok"></span> Có ca trong khung giờ</span>
                        <span id="modalScheduledCount" class="text-xs font-normal text-slate-500 font-heading"></span>
                    </h4>
                    <ul id="modalScheduledList" class="max-h-56 space-y-1.5 overflow-y-auto pr-1"></ul>
                </section>
                <section>
                    <h4 class="mb-2 flex items-center justify-between text-sm font-heading font-bold text-slate-900 dark:text-white">
                        <span class="inline-flex items-center gap-1.5"><span class="cov-dot is-leave"></span> Nghỉ phép</span>
                        <span id="modalLeavesCount" class="text-xs font-normal text-slate-500 font-heading"></span>
                    </h4>
                    <ul id="modalLeavesList" class="max-h-56 space-y-1.5 overflow-y-auto pr-1"></ul>
                </section>
            </div>
        </div>

        <div class="pcrm-dialog-foot">
            <button type="button" onclick="closeOperationalDetailModal()" class="btn-secondary">Đóng</button>
            <a id="modalScheduleLink" href="#" class="btn-primary">
                <i class="bi bi-calendar-plus"></i> <span>Mở lưới xếp ca ngày này</span>
            </a>
        </div>
    </div>
</div>
@endpush

@push('scripts')
<script>
function switchOperationalView(mode) {
    ['table', 'list', 'cards'].forEach(function (m) {
        const cap = m.charAt(0).toUpperCase() + m.slice(1);
        const panel = document.getElementById('operational' + cap + 'View');
        const btn = document.getElementById('viewBtn' + cap);
        if (panel) panel.classList.toggle('hidden', m !== mode);
        if (btn) {
            btn.classList.toggle('is-active', m === mode);
            btn.setAttribute('aria-selected', m === mode ? 'true' : 'false');
        }
    });
    const inputView = document.getElementById('currentViewInput');
    if (inputView) inputView.value = mode;
    // Giữ chế độ xem trên URL và trên các link tuần/bộ phận (không phải tải lại trang)
    try {
        const url = new URL(window.location.href);
        url.searchParams.set('view', mode);
        window.history.replaceState({}, '', url.toString());
        document.querySelectorAll('a[href*="operational-schedule"]').forEach(function (a) {
            const u = new URL(a.href);
            u.searchParams.set('view', mode);
            a.href = u.toString();
        });
    } catch (e) {}
}

function filterListViewShortages(shortageOnly) {
    document.querySelectorAll('#operationalListTable .list-item-row').forEach(function (row) {
        row.style.display = (!shortageOnly || row.getAttribute('data-shortage') === '1') ? '' : 'none';
    });
}

// Dựng phần tử bằng textContent — dữ liệu (tên nhân viên…) không bao giờ được chèn dạng HTML
function opsEl(tag, className, text) {
    const el = document.createElement(tag);
    if (className) el.className = className;
    if (text !== undefined && text !== null) el.textContent = text;
    return el;
}

function opsPersonRow(name, meta, badgeText, badgeClass) {
    const li = opsEl('li', 'flex items-center justify-between gap-3 rounded-lg border border-slate-100 dark:border-slate-700/60 px-3 py-2 text-sm');
    const left = opsEl('div', 'min-w-0');
    left.appendChild(opsEl('p', 'truncate font-medium text-slate-900 dark:text-white', name || 'Nhân viên'));
    if (meta) left.appendChild(opsEl('p', 'truncate text-xs text-slate-500 dark:text-slate-400', meta));
    li.appendChild(left);
    if (badgeText) li.appendChild(opsEl('span', badgeClass + ' shrink-0', badgeText));
    return li;
}

const OPS_STATUS = {
    shortage:    { text: 'Thiếu nhân sự — dưới mức tối thiểu', icon: 'bi-exclamation-octagon-fill', cls: 'is-rejected', iconCls: 'bg-[#fde8ea] text-[#C94758]' },
    minimum_met: { text: 'Đạt mức tối thiểu — nên bổ sung để đạt chuẩn', icon: 'bi-dash-circle-fill', cls: 'is-pending', iconCls: 'bg-[#fef3dc] text-[#C98219]' },
    target_met:  { text: 'Đạt định biên chuẩn', icon: 'bi-check-circle-fill', cls: 'is-approved', iconCls: 'bg-[#e8f5ef] text-[#168A63]' },
};

function openOperationalDetailModal(data) {
    const st = OPS_STATUS[data.status] || OPS_STATUS.target_met;

    document.getElementById('modalReqName').textContent = data.req_name || 'Khung định biên';
    document.getElementById('modalTeamBadge').textContent = data.team_name || 'Bộ phận';
    document.getElementById('modalDateTitle').textContent = data.date_formatted || '';
    document.getElementById('modalTimeRange').textContent = data.time_range || '';
    document.getElementById('modalStaffTargets').textContent = data.minimum_staff + ' / ' + data.target_staff;
    document.getElementById('modalStaffScheduled').textContent = data.scheduled_coverage + ' người';
    document.getElementById('modalStatusText').textContent = st.text;
    document.getElementById('modalStatusBanner').className = 'record-status ' + st.cls;
    document.getElementById('modalStatusIconWrap').className = 'pcrm-dialog-icon ' + st.iconCls;
    document.getElementById('modalStatusIcon').className = 'bi ' + st.icon;

    // Khoảng giờ thiếu
    const shortageBox = document.getElementById('modalShortageContainer');
    const shortageList = document.getElementById('modalShortageList');
    shortageList.replaceChildren();
    const intervals = data.shortage_intervals || [];
    intervals.forEach(function (item) {
        const li = opsEl('li', 'flex items-center justify-between gap-3 px-3 py-2');
        li.appendChild(opsEl('span', 'font-mono text-slate-700 dark:text-slate-300', item.start_formatted + ' – ' + item.end_formatted));
        li.appendChild(opsEl('span', 'font-semibold text-[#C94758]', item.concurrent + ' / ' + data.minimum_staff + ' người'));
        shortageList.appendChild(li);
    });
    shortageBox.classList.toggle('hidden', intervals.length === 0);

    // Nhân sự được xếp ca
    const schList = document.getElementById('modalScheduledList');
    schList.replaceChildren();
    const employees = data.scheduled_employees || [];
    document.getElementById('modalScheduledCount').textContent = employees.length + ' người';
    if (employees.length === 0) {
        schList.appendChild(opsEl('li', 'text-sm text-slate-500', 'Chưa xếp ca nhân viên nào.'));
    }
    employees.forEach(function (emp) {
        let badge = ['Theo lịch', 'badge-neutral'];
        if (emp.has_checked_in) badge = ['Đã check-in', 'badge-success'];
        else if (emp.is_missed) badge = ['Lỡ ca', 'badge-danger'];
        schList.appendChild(opsPersonRow(emp.employee ? emp.employee.name : null, (emp.shift_name || '') + (emp.time_range ? ' · ' + emp.time_range : ''), badge[0], badge[1]));
    });

    // Nghỉ phép
    const leavesList = document.getElementById('modalLeavesList');
    leavesList.replaceChildren();
    const approved = data.approved_leaves || [];
    const pending = data.pending_leaves || [];
    document.getElementById('modalLeavesCount').textContent = (approved.length + pending.length) + ' người';
    if (approved.length + pending.length === 0) {
        leavesList.appendChild(opsEl('li', 'text-sm text-slate-500', 'Không có ai nghỉ phép.'));
    }
    approved.forEach(function (l) { leavesList.appendChild(opsPersonRow(l.name, l.type, 'Đã duyệt', 'badge-info')); });
    pending.forEach(function (l) { leavesList.appendChild(opsPersonRow(l.name, l.type, 'Chờ duyệt', 'badge-warning')); });

    const schLink = document.getElementById('modalScheduleLink');
    if (schLink && data.schedule_url) schLink.href = data.schedule_url;

    openModal('operationalDetailModal');
}

function closeOperationalDetailModal() {
    closeModal('operationalDetailModal');
}
</script>
@endpush
