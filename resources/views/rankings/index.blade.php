@extends('layouts.admin')

@section('title', 'Bảng xếp hạng')
@section('page-title', 'Bảng xếp hạng')
@section('breadcrumb', 'Phân tích / Xếp hạng')

@php
    // ── Zone color helpers ────────────────────────────────────────────────────
    $zoneBadgeCss = fn(string $z) => match ($z) {
        'green' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        'yellow' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300',
        'orange' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300',
        'red' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        default => 'bg-slate-100 text-slate-500',
    };
    $zoneScoreColor = fn(string $z) => match ($z) {
        'green' => 'text-emerald-600 dark:text-emerald-400',
        'yellow' => 'text-yellow-600 dark:text-yellow-400',
        'orange' => 'text-orange-600 dark:text-orange-400',
        'red' => 'text-red-600 dark:text-red-400',
        default => 'text-slate-800 dark:text-white',
    };
    $zoneAvatarCss = fn(string $z) => match ($z) {
        'green'
            => 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 ring-2 ring-emerald-300 dark:ring-emerald-700',
        'yellow'
            => 'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-700 dark:text-yellow-300 ring-2 ring-yellow-300 dark:ring-yellow-700',
        'orange'
            => 'bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300 ring-2 ring-orange-300 dark:ring-orange-700',
        'red'
            => 'bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300 ring-2 ring-rose-300 dark:ring-rose-700',
        default => 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300',
    };
    $zoneBarCls = fn(string $z) => match ($z) {
        'green' => 'bg-emerald-500',
        'yellow' => 'bg-yellow-500',
        'orange' => 'bg-orange-500',
        'red' => 'bg-rose-500',
        default => 'bg-slate-400',
    };
    $zoneMobileBg = fn(string $z) => match ($z) {
        'green' => 'max-sm:!bg-emerald-50 max-sm:dark:!bg-emerald-950/30',
        'yellow' => 'max-sm:!bg-amber-50 max-sm:dark:!bg-amber-950/30',
        'orange' => 'max-sm:!bg-orange-50 max-sm:dark:!bg-orange-950/30',
        'red' => 'max-sm:!bg-rose-50 max-sm:dark:!bg-rose-950/30',
        default => '',
    };
    $zoneEmoji = fn(string $z) => match ($z) {
        'green' => '🟢',
        'yellow' => '🟡',
        'orange' => '🟠',
        'red' => '🔴',
        default => '⚪',
    };

    // ── Rank-based helpers (rank 1/2/3 get medal styling) ────────────────────
    $rankMedal = fn(int $rank) => match ($rank) {
        1 => '🥇',
        2 => '🥈',
        3 => '🥉',
        default => null,
    };
    $rankLabel = [1 => 'Hạng nhất', 2 => 'Hạng nhì', 3 => 'Hạng ba'];
    $rankScoreClass = fn(int $rank, string $z) => match ($rank) {
        1 => 'text-amber-600 dark:text-amber-400',
        2 => 'text-slate-500 dark:text-slate-300',
        3 => 'text-orange-600 dark:text-orange-400',
        default => $zoneScoreColor($z),
    };
    $rankAvatarClass = fn(int $rank, string $z) => match ($rank) {
        1
            => 'bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300 ring-2 ring-amber-300 dark:ring-amber-700',
        2
            => 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 ring-2 ring-slate-300 dark:ring-slate-600',
        3
            => 'bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300 ring-2 ring-orange-300 dark:ring-orange-700',
        default => $zoneAvatarCss($z),
    };
    $teamAvatarRankClass = fn(int $rank) => match ($rank) {
        1
            => 'bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300 ring-2 ring-amber-300 dark:ring-amber-700',
        2
            => 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 ring-2 ring-slate-300 dark:ring-slate-600',
        3
            => 'bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300 ring-2 ring-orange-300 dark:ring-orange-700',
        default => 'bg-pcrm-100 dark:bg-pcrm-900/30 text-pcrm-700 dark:text-pcrm-300',
    };
@endphp

@section('content')
    @php $isAdmin = auth()->user()->hasRole(['admin', 'manager', 'director']); @endphp
    <div class="flex items-center justify-between gap-2 mb-6">
        <div class="inline-flex items-center gap-1.5 sm:gap-2 min-w-0">
            <span
                class="hidden sm:inline-flex flex-shrink-0 items-center justify-center w-9 h-9 rounded-xl bg-pcrm-100 dark:bg-pcrm-900/50 text-pcrm-700 dark:text-pcrm-400">
                <i class="bi bi-trophy-fill text-lg"></i>
            </span>
            <h3 class="page-title !text-base sm:!text-2xl truncate">Bảng xếp hạng</h3>
        </div>
        <div class="flex gap-1 sm:gap-2 flex-shrink-0" role="tablist">
            <button type="button" class="btn-secondary ranking-tab-btn" onclick="showRankTab('alltime')" id="rtab-alltime"
                role="tab" title="Tất cả thời gian">
                <i class="bi bi-list-ol"></i><span class="hidden sm:inline">Tất cả thời gian</span>
            </button>
            <button type="button" class="btn btn-primary ranking-tab-btn" onclick="showRankTab('teams')" id="rtab-teams"
                role="tab" title="Đội nhóm">
                <i class="bi bi-people"></i><span class="hidden sm:inline">Đội nhóm</span>
            </button>
            <button type="button" class="btn btn-primary ranking-tab-btn" onclick="showRankTab('monthly')"
                id="rtab-monthly" role="tab" title="Tháng">
                <i class="bi bi-calendar-month"></i><span class="hidden sm:inline">Tháng</span>
            </button>
            <button type="button" class="btn btn-primary ranking-tab-btn" onclick="showRankTab('yearly')" id="rtab-yearly"
                role="tab" title="Năm">
                <i class="bi bi-calendar2-check"></i><span class="hidden sm:inline">Năm</span>
            </button>
        </div>
    </div>

    {{-- ══ Zone legend ════════════════════════════════════════════════════════ --}}
    <div class="hidden sm:flex flex-wrap items-center gap-3 mb-5 px-1">
        <span class="text-xs font-medium text-slate-400 uppercase tracking-wider">Zone:</span>
        <span
            class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">🟢
            An toàn</span>
        <span
            class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300">🟡
            Cần cố gắng</span>
        <span
            class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300">🟠
            Cảnh báo</span>
        <span
            class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300">🔴
            Redzone</span>
        @if($isAdmin)
        <span class="hidden sm:inline ml-auto text-xs text-slate-400"><span class="font-semibold text-emerald-500">HS</span> = Hiệu số (điểm
            thưởng vượt 100)</span>
        @endif
    </div>

    {{-- ══ TAB: ALL-TIME ══════════════════════════════════════════════════════ --}}
    <div id="ranking-panel-alltime" class="ranking-panel">
        <div class="card">
            <div class="card-header flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-slate-900 dark:text-white">Xếp hạng điểm tích luỹ</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Zone hiển thị theo tháng hiện tại · HS = Hiệu số cộng dồn</p>
                </div>
                <span class="text-xs font-medium text-slate-400 bg-slate-100 dark:bg-slate-700 px-2.5 py-1 rounded-full">
                    {{ $employees->count() }} nhân viên
                </span>
            </div>
            <div class="card-body sm:max-h-[90vh] sm:overflow-y-auto">
                @if ($employees->isEmpty())
                    <div class="py-14 text-center">
                        <div
                            class="w-14 h-14 mx-auto mb-3 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                            <i class="bi bi-trophy text-2xl text-slate-400"></i>
                        </div>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Chưa có dữ liệu xếp hạng</p>
                    </div>
                @else
                    <div class="ranking-scroll-list grid grid-cols-1 sm:grid-cols-1 gap-3">
                        @foreach ($employees as $index => $emp)
                            @php
                                $zone = $emp->zone ?: 'green';
                                $rank = $emp->rank;
                                $isTop = $rank <= 3;
                                $atscore = $emp->alltime_score ?? 0;
                                $surplus = $emp->alltime_surplus ?? 0;
                                $delay = min(0.04 + $index * 0.03, 0.55);
                            @endphp
                            <a href="{{ route('employees.show', $emp) }}"
                                class="leaderboard-card {{ $isTop ? 'leaderboard-card-top' : $zoneMobileBg($zone) }}"
                                style="animation-delay:{{ $delay }}s">

                                {{-- Rank --}}
                                <div class="leaderboard-card-rank leaderboard-medal w-8 text-center flex-shrink-0">
                                    @if ($rankMedal($rank))
                                        <span class="text-xl select-none">{{ $rankMedal($rank) }}</span>
                                    @else
                                        <span
                                            class="text-sm font-bold text-slate-400 dark:text-slate-500">{{ $rank }}</span>
                                    @endif
                                </div>

                                {{-- Avatar --}}
                                <div
                                    class="w-10 h-10 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-bold {{ $rankAvatarClass($rank, $zone) }}">
                                    {{ strtoupper(mb_substr($emp->name, 0, 2)) }}
                                </div>

                                {{-- Info --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span
                                            class="text-sm font-semibold text-slate-800 dark:text-slate-200 truncate">{{ $emp->name }}</span>
                                        <span
                                            class="hidden sm:inline-flex items-center gap-0.5 text-[10px] font-bold px-1.5 py-0.5 rounded-full flex-shrink-0 {{ $zoneBadgeCss($zone) }}">
                                            {{ $zoneEmoji($zone) }}
                                            {{ \App\Models\MonthlyEmployeeScore::zoneLabel($zone) }}
                                        </span>
                                    </div>
                                    <p class="hidden sm:block text-xs text-slate-400 truncate mt-0.5">
                                        {{ $emp->branch->name ?? '—' }}@if ($emp->team)
                                            <span class="opacity-40 mx-1">·</span>{{ $emp->team->name }}
                                        @endif
                                    </p>
                                    @if ($isTop)
                                        <p
                                            class="text-[10px] font-bold uppercase tracking-wider mt-0.5 {{ $rankScoreClass($rank, $zone) }}">
                                            {{ $rankLabel[$rank] ?? '' }}</p>
                                    @else
                                        <p class="hidden sm:block text-[10px] text-slate-400 mt-0.5">{{ $emp->code }}</p>
                                    @endif
                                </div>

                                {{-- Score + surplus (admin only) --}}
                                @if($isAdmin)
                                <div class="leaderboard-card-score flex-shrink-0 text-right">
                                    <p class="font-extrabold text-base {{ $rankScoreClass($rank, $zone) }}">
                                        {{ number_format($atscore) }}
                                        <span class="text-xs font-normal text-slate-400">pts</span>
                                    </p>
                                    @if ($surplus > 0)
                                        <p class="text-[10px] font-semibold text-emerald-500 dark:text-emerald-400">
                                            +{{ number_format($surplus) }} HS</p>
                                    @endif
                                </div>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ══ TAB: TEAMS ═════════════════════════════════════════════════════════ --}}
    <div id="ranking-panel-teams" class="ranking-panel hidden">
        <div class="card">
            <div class="card-header">
                <h3 class="font-semibold text-slate-900 dark:text-white">Xếp hạng đội nhóm</h3>
                <p class="text-xs text-slate-400 mt-0.5">Theo điểm trung bình thành viên</p>
            </div>
            <div class="card-body">
                @if (isset($teams) && $teams->isNotEmpty())
                    <div class="grid grid-cols-1 sm:grid-cols-1 gap-3">
                        @foreach ($teams as $index => $team)
                            @php
                                $trank = $index + 1;
                                $isTop = $trank <= 3;
                            @endphp
                            <div class="leaderboard-card {{ $isTop ? 'leaderboard-card-top' : '' }}"
                                style="animation-delay:{{ 0.04 + $index * 0.04 }}s">

                                {{-- Rank --}}
                                <div class="leaderboard-card-rank leaderboard-medal w-8 text-center flex-shrink-0">
                                    @if ($rankMedal($trank))
                                        <span class="text-xl select-none">{{ $rankMedal($trank) }}</span>
                                    @else
                                        <span
                                            class="text-sm font-bold text-slate-400 dark:text-slate-500">{{ $trank }}</span>
                                    @endif
                                </div>

                                {{-- Team avatar --}}
                                <div
                                    class="w-10 h-10 rounded-xl flex-shrink-0 flex items-center justify-center text-xs font-bold {{ $teamAvatarRankClass($trank) }}">
                                    <i class="bi bi-people-fill text-sm"></i>
                                </div>

                                {{-- Info --}}
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-200 truncate">
                                        {{ $team->name }}</p>
                                    <p class="hidden sm:block text-xs text-slate-400 truncate mt-0.5">
                                        {{ $team->branch->name ?? '—' }}
                                        <span class="opacity-40 mx-1">·</span>{{ $team->employees_count ?? 0 }} thành viên
                                    </p>
                                    @if ($isTop)
                                        <p
                                            class="text-[10px] font-bold uppercase tracking-wider mt-0.5 {{ $rankScoreClass($trank, 'green') }}">
                                            {{ $rankLabel[$trank] ?? '' }}</p>
                                    @endif
                                </div>

                                {{-- Score (admin only) --}}
                                @if($isAdmin)
                                <div class="leaderboard-card-score flex-shrink-0">
                                    <p class="font-extrabold text-base {{ $rankScoreClass($trank, 'green') }}">
                                        {{ number_format($team->average_score, 1) }}
                                        <span class="text-xs font-normal text-slate-400">TB</span>
                                    </p>
                                </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-14 text-center">
                        <div
                            class="w-14 h-14 mx-auto mb-3 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                            <i class="bi bi-people text-2xl text-slate-400"></i>
                        </div>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Chưa có dữ liệu đội nhóm</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ══ TAB: MONTHLY ═══════════════════════════════════════════════════════ --}}
    <div id="ranking-panel-monthly" class="ranking-panel hidden">
        <div class="card mb-4">
            <div class="card-body py-3">
                <form method="GET" action="{{ route('rankings.index') }}" class="flex flex-wrap items-end gap-4">
                    <input type="hidden" name="eval_year_only" value="{{ $evalYearOnly }}">
                    <div>
                        <label class="form-label text-xs mb-1">Chọn tháng</label>
                        <select name="eval_month" class="form-select text-sm w-32" onchange="syncMonthYear(this)">
                            @foreach ($monthOptions as $opt)
                                <option value="{{ $opt['month'] }}" data-year="{{ $opt['year'] }}"
                                    {{ $opt['month'] == $evalMonth && $opt['year'] == $evalYear ? 'selected' : '' }}>
                                    {{ $opt['label'] }}
                                </option>
                            @endforeach
                        </select>
                        <input type="hidden" name="eval_year" id="eval_year_hidden" value="{{ $evalYear }}">
                    </div>
                    <button type="submit" onclick="setTab('monthly')" class="btn-primary text-sm">
                        <i class="bi bi-search"></i> Xem
                    </button>
                </form>
            </div>
        </div>

        @if ($employeeOfMonth)
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
                {{-- Spotlight --}}
                <div class="lg:col-span-1">
                    <div
                        class="card bg-gradient-to-br from-amber-50 to-yellow-100 dark:from-amber-950/40 dark:to-yellow-950/20 border border-amber-200 dark:border-amber-800">
                        <div class="card-body text-center py-5 lg:py-8">
                            <div class="text-4xl lg:text-6xl mb-2 lg:mb-3 select-none">🏆</div>
                            <div
                                class="text-[10px] lg:text-[11px] font-bold uppercase tracking-widest text-amber-600 dark:text-amber-400 mb-2 lg:mb-3">
                                Nhân viên xuất sắc tháng
                                {{ str_pad($evalMonth, 2, '0', STR_PAD_LEFT) }}/{{ $evalYear }}
                            </div>
                            <div
                                class="w-12 h-12 lg:w-16 lg:h-16 rounded-full bg-amber-200 dark:bg-amber-800 flex items-center justify-center text-xl lg:text-2xl font-black text-amber-800 dark:text-amber-200 mx-auto mb-2 lg:mb-3">
                                {{ strtoupper(mb_substr($employeeOfMonth->name, 0, 2)) }}
                            </div>
                            <h3 class="text-base lg:text-xl font-black text-slate-900 dark:text-white mb-0.5">
                                {{ $employeeOfMonth->name }}</h3>
                            <p class="text-xs lg:text-sm text-slate-500 dark:text-slate-400 mb-3 lg:mb-4">
                                {{ $employeeOfMonth->branch->name ?? '—' }} · {{ $employeeOfMonth->team->name ?? '—' }}
                            </p>
                            @php $mzone = $employeeOfMonth->zone; @endphp
                            @if($isAdmin)
                            <div
                                class="inline-flex items-center gap-2 bg-white/70 dark:bg-slate-800/50 rounded-full px-3 lg:px-4 py-1.5 lg:py-2 mb-1">
                                <span
                                    class="text-xl lg:text-2xl font-extrabold {{ $zoneScoreColor($mzone) }}">{{ $employeeOfMonth->display_score }}</span>
                                <span class="text-xs text-slate-500">điểm</span>
                                @if (($employeeOfMonth->surplus_points ?? 0) > 0)
                                    <span
                                        class="text-sm font-bold text-emerald-500 dark:text-emerald-400">+{{ $employeeOfMonth->surplus_points }}
                                        HS</span>
                                @endif
                            </div>
                            @endif
                            <div class="mb-3">
                                <span class="text-xs px-2 py-0.5 rounded-full font-semibold {{ $zoneBadgeCss($mzone) }}">
                                    {{ $zoneEmoji($mzone) }} {{ \App\Models\MonthlyEmployeeScore::zoneLabel($mzone) }}
                                </span>
                            </div>
                            <div>
                                <a href="{{ route('employees.show', $employeeOfMonth) }}"
                                    class="btn-secondary btn-sm text-xs">
                                    <i class="bi bi-eye"></i> Xem hồ sơ
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Monthly leaderboard --}}
                <div class="lg:col-span-2">
                    <div class="card h-full">
                        <div class="card-header">
                            <h3 class="font-semibold text-slate-900 dark:text-white">
                                Top nhân viên tháng {{ str_pad($evalMonth, 2, '0', STR_PAD_LEFT) }}/{{ $evalYear }}
                            </h3>
                        </div>
                        <div class="card-body sm:max-h-[90vh] sm:overflow-y-auto">
                            <div class="ranking-scroll-list-sm grid grid-cols-1 sm:grid-cols-1 gap-3">
                                @forelse($monthlyRanking as $i => $emp)
                                    @php
                                        $z = $emp->zone ?? 'green';
                                        $rank = $emp->rank;
                                        $isTop = $rank <= 3;
                                        $surplus = $emp->surplus_points ?? 0;
                                        $delay = min(0.05 + $i * 0.04, 0.5);
                                    @endphp
                                    <div class="leaderboard-card {{ $isTop ? 'leaderboard-card-top' : $zoneMobileBg($z) }}"
                                        style="animation-delay:{{ $delay }}s">

                                        <div class="leaderboard-card-rank leaderboard-medal w-7 text-center flex-shrink-0">
                                            @if ($rankMedal($rank))
                                                <span class="text-lg select-none">{{ $rankMedal($rank) }}</span>
                                            @else
                                                <span class="text-sm font-bold text-slate-400">{{ $rank }}</span>
                                            @endif
                                        </div>

                                        <div
                                            class="w-9 h-9 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-bold {{ $rankAvatarClass($rank, $z) }}">
                                            {{ strtoupper(mb_substr($emp->name, 0, 2)) }}
                                        </div>

                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <a href="{{ route('employees.show', $emp) }}"
                                                    class="text-sm font-semibold hover:underline truncate {{ $rankScoreClass($rank, $z) }}">
                                                    {{ $emp->name }}
                                                </a>
                                                <span
                                                    class="hidden sm:inline-flex items-center gap-0.5 text-[10px] font-bold px-1.5 py-0.5 rounded-full flex-shrink-0 {{ $zoneBadgeCss($z) }}">
                                                    {{ $zoneEmoji($z) }}
                                                    {{ \App\Models\MonthlyEmployeeScore::zoneLabel($z) }}
                                                </span>
                                            </div>
                                            <p class="hidden sm:block text-[10px] text-slate-400 mt-0.5">{{ $emp->code }}</p>
                                        </div>

                                        @if($isAdmin)
                                        <div class="leaderboard-card-score flex-shrink-0 text-right">
                                            <p class="font-extrabold text-base {{ $rankScoreClass($rank, $z) }}">
                                                {{ $emp->display_score }}
                                            </p>
                                            @if ($surplus > 0)
                                                <p
                                                    class="text-[10px] font-semibold text-emerald-500 dark:text-emerald-400">
                                                    +{{ $surplus }} HS</p>
                                            @endif
                                        </div>
                                        @endif
                                    </div>
                                @empty
                                    <div class="py-10 text-center text-slate-400">
                                        <p class="text-sm">Chưa có dữ liệu tháng này</p>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-body text-center py-12 text-slate-400">
                    <div
                        class="w-14 h-14 mx-auto mb-3 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                        <i class="bi bi-trophy text-2xl text-slate-400"></i>
                    </div>
                    <p class="text-sm">Chưa có dữ liệu điểm tháng
                        {{ str_pad($evalMonth, 2, '0', STR_PAD_LEFT) }}/{{ $evalYear }}</p>
                    <p class="text-xs mt-1 text-slate-300 dark:text-slate-600">Chạy <code
                            class="bg-slate-100 dark:bg-slate-700 px-1 rounded">php artisan scores:reset-monthly</code> để
                        khởi tạo.</p>
                </div>
            </div>
        @endif
    </div>

    {{-- ══ TAB: YEARLY ════════════════════════════════════════════════════════ --}}
    <div id="ranking-panel-yearly" class="ranking-panel hidden">
        <div class="card mb-4">
            <div class="card-body py-3">
                <form method="GET" action="{{ route('rankings.index') }}" class="flex flex-wrap items-end gap-4">
                    <input type="hidden" name="eval_month" value="{{ $evalMonth }}">
                    <input type="hidden" name="eval_year" value="{{ $evalYear }}">
                    <div>
                        <label class="form-label text-xs mb-1">Chọn năm</label>
                        <select name="eval_year_only" class="form-select text-sm w-28">
                            @foreach ($yearOptions as $y)
                                <option value="{{ $y }}" {{ $y == $evalYearOnly ? 'selected' : '' }}>
                                    {{ $y }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" onclick="setTab('yearly')" class="btn-primary text-sm">
                        <i class="bi bi-search"></i> Xem
                    </button>
                </form>
            </div>
        </div>

        @if ($employeeOfYear)
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                {{-- Yearly spotlight --}}
                <div class="lg:col-span-1">
                    <div
                        class="card bg-gradient-to-br from-pcrm-50 to-indigo-100 dark:from-pcrm-950/40 dark:to-indigo-950/20 border border-pcrm-200 dark:border-pcrm-800">
                        <div class="card-body text-center py-5 lg:py-8">
                            <div class="text-4xl lg:text-6xl mb-2 lg:mb-3 select-none">🥇</div>
                            <div
                                class="text-[10px] lg:text-[11px] font-bold uppercase tracking-widest text-pcrm-600 dark:text-pcrm-400 mb-2 lg:mb-3">
                                Nhân viên xuất sắc năm {{ $evalYearOnly }}
                            </div>
                            <div
                                class="w-12 h-12 lg:w-16 lg:h-16 rounded-full bg-pcrm-200 dark:bg-pcrm-800 flex items-center justify-center text-xl lg:text-2xl font-black text-pcrm-800 dark:text-pcrm-200 mx-auto mb-2 lg:mb-3">
                                {{ strtoupper(mb_substr($employeeOfYear->name, 0, 2)) }}
                            </div>
                            <h3 class="text-base lg:text-xl font-black text-slate-900 dark:text-white mb-0.5">
                                {{ $employeeOfYear->name }}</h3>
                            <p class="text-xs lg:text-sm text-slate-500 dark:text-slate-400 mb-3 lg:mb-4">
                                {{ $employeeOfYear->branch->name ?? '—' }} · {{ $employeeOfYear->team->name ?? '—' }}
                            </p>
                            @php $yzone = $employeeOfYear->zone; @endphp
                            @if($isAdmin)
                            <div
                                class="inline-flex items-center gap-2 bg-white/70 dark:bg-slate-800/50 rounded-full px-3 lg:px-4 py-1.5 lg:py-2 mb-1">
                                <span
                                    class="text-xl lg:text-2xl font-extrabold {{ $zoneScoreColor($yzone) }}">{{ $employeeOfYear->display_score }}</span>
                                <span class="text-xs text-slate-500">điểm TB</span>
                                @if (($employeeOfYear->surplus_points ?? 0) > 0)
                                    <span
                                        class="text-sm font-bold text-emerald-500 dark:text-emerald-400">+{{ $employeeOfYear->surplus_points }}
                                        HS</span>
                                @endif
                            </div>
                            @endif
                            <div class="text-xs text-slate-400 mb-3">
                                {{ $employeeOfYear->months_logged }} tháng ghi nhận
                                @if ($employeeOfYear->months_in_red > 0)
                                    · <span class="text-red-500">{{ $employeeOfYear->months_in_red }} 🔴 redzone</span>
                                @endif
                            </div>
                            <a href="{{ route('employees.show', $employeeOfYear) }}"
                                class="btn-secondary btn-sm text-xs">
                                <i class="bi bi-eye"></i> Xem hồ sơ
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Yearly leaderboard --}}
                <div class="lg:col-span-2">
                    <div class="card h-full">
                        <div class="card-header">
                            <h3 class="font-semibold text-slate-900 dark:text-white">
                                Bảng xếp hạng năm {{ $evalYearOnly }} (điểm trung bình/tháng)
                            </h3>
                        </div>
                        <div class="card-body sm:max-h-[90vh] sm:overflow-y-auto">
                            <div class="ranking-scroll-list-sm grid grid-cols-1 sm:grid-cols-1 gap-3">
                                @forelse($yearlyRanking as $i => $emp)
                                    @php
                                        $z = $emp->zone ?? 'green';
                                        $rank = $emp->rank;
                                        $isTop = $rank <= 3;
                                        $surplus = $emp->surplus_points ?? 0;
                                        $delay = min(0.05 + $i * 0.04, 0.5);
                                    @endphp
                                    <div class="leaderboard-card {{ $isTop ? 'leaderboard-card-top' : $zoneMobileBg($z) }}"
                                        style="animation-delay:{{ $delay }}s">

                                        <div class="leaderboard-card-rank leaderboard-medal w-7 text-center flex-shrink-0">
                                            @if ($rankMedal($rank))
                                                <span class="text-lg select-none">{{ $rankMedal($rank) }}</span>
                                            @else
                                                <span class="text-sm font-bold text-slate-400">{{ $rank }}</span>
                                            @endif
                                        </div>

                                        <div
                                            class="w-9 h-9 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-bold {{ $rankAvatarClass($rank, $z) }}">
                                            {{ strtoupper(mb_substr($emp->name, 0, 2)) }}
                                        </div>

                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <a href="{{ route('employees.show', $emp) }}"
                                                    class="text-sm font-semibold hover:underline truncate {{ $rankScoreClass($rank, $z) }}">
                                                    {{ $emp->name }}
                                                </a>
                                                <span
                                                    class="hidden sm:inline-flex items-center gap-0.5 text-[10px] font-bold px-1.5 py-0.5 rounded-full flex-shrink-0 {{ $zoneBadgeCss($z) }}">
                                                    {{ $zoneEmoji($z) }}
                                                    {{ \App\Models\MonthlyEmployeeScore::zoneLabel($z) }}
                                                </span>
                                            </div>
                                            <p class="hidden sm:block text-[10px] text-slate-400 mt-0.5">
                                                {{ $emp->months_logged }} tháng
                                                @if ($emp->months_in_red > 0)
                                                    · <span class="text-red-400">{{ $emp->months_in_red }} 🔴</span>
                                                @endif
                                            </p>
                                        </div>

                                        @if($isAdmin)
                                        <div class="leaderboard-card-score flex-shrink-0 text-right">
                                            <p class="font-extrabold text-base {{ $rankScoreClass($rank, $z) }}">
                                                {{ $emp->display_score }}
                                                <span class="text-xs font-normal text-slate-400">TB</span>
                                            </p>
                                            @if ($surplus > 0)
                                                <p
                                                    class="text-[10px] font-semibold text-emerald-500 dark:text-emerald-400">
                                                    +{{ $surplus }} HS</p>
                                            @endif
                                        </div>
                                        @endif
                                    </div>
                                @empty
                                    <div class="py-10 text-center text-slate-400">
                                        <p class="text-sm">Chưa có dữ liệu năm {{ $evalYearOnly }}</p>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-body text-center py-12 text-slate-400">
                    <div
                        class="w-14 h-14 mx-auto mb-3 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center">
                        <i class="bi bi-trophy text-2xl text-slate-400"></i>
                    </div>
                    <p class="text-sm">Chưa có dữ liệu điểm năm {{ $evalYearOnly }}</p>
                </div>
            </div>
        @endif
    </div>

    <script>
        const rankTabs = ['alltime', 'teams', 'monthly', 'yearly'];

        function showRankTab(tab) {
            rankTabs.forEach(t => {
                document.getElementById('ranking-panel-' + t).classList.add('hidden');
                document.getElementById('rtab-' + t).classList.replace('btn-primary', 'btn-secondary');
            });
            document.getElementById('ranking-panel-' + tab).classList.remove('hidden');
            document.getElementById('rtab-' + tab).classList.replace('btn-secondary', 'btn-primary');
        }

        function setTab(tab) {
            sessionStorage.setItem('rankingTab', tab);
        }

        function syncMonthYear(sel) {
            document.getElementById('eval_year_hidden').value = sel.options[sel.selectedIndex].getAttribute('data-year');
        }

        document.addEventListener('DOMContentLoaded', function() {
            const saved = sessionStorage.getItem('rankingTab') || 'alltime';
            showRankTab(saved);
            sessionStorage.removeItem('rankingTab');
        });
    </script>
@endsection
