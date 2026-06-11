@extends('layouts.admin')

@section('title', 'Bảng xếp hạng')
@section('page-title', 'Bảng xếp hạng')
@section('breadcrumb', 'Phân tích / Xếp hạng')

@php
    // ── Zone helpers (used throughout this view) ──────────────────────────────
    $zoneRowBg = fn(string $z) => match ($z) {
        'green' => 'bg-emerald-50/40 dark:bg-emerald-950/10',
        'yellow' => 'bg-yellow-50/40 dark:bg-yellow-950/10',
        'orange' => 'bg-orange-50/40 dark:bg-orange-950/10',
        'red' => 'bg-red-50/50 dark:bg-red-950/15',
        default => '',
    };
    $zoneBorder = fn(string $z) => match ($z) {
        'green' => 'border-l-emerald-400',
        'yellow' => 'border-l-yellow-400',
        'orange' => 'border-l-orange-400',
        'red' => 'border-l-red-500',
        default => 'border-l-transparent',
    };
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
    // Top-3 overrides (take precedence over zone)
    $podiumRowBg = [
        'bg-gradient-to-r from-amber-50 to-amber-50/0 dark:from-amber-950/40 dark:to-transparent',
        'bg-gradient-to-r from-slate-100 to-slate-50/0 dark:from-slate-700/40 dark:to-transparent',
        'bg-gradient-to-r from-orange-50 to-orange-50/0 dark:from-orange-950/30 dark:to-transparent',
    ];
    $podiumBorder = ['border-l-amber-400', 'border-l-slate-400', 'border-l-orange-500'];
    $podiumMedal = ['🥇', '🥈', '🥉'];
    $podiumLabel = ['Hạng nhất', 'Hạng nhì', 'Hạng ba'];
    $podiumAvatarBg = [
        'bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300',
        'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300',
        'bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300',
    ];
@endphp

@section('content')
    <div class="page-header">
        <div>
            <div class="inline-flex items-center gap-2">
                <span
                    class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-pcrm-100 dark:bg-pcrm-900/50 text-pcrm-700 dark:text-pcrm-400">
                    <i class="bi bi-trophy-fill text-lg"></i>
                </span>
                <h3 class="page-title">Bảng xếp hạng</h3>
            </div>
        </div>
        <div class="flex gap-2 flex-wrap" role="tablist">
            <button type="button" class="btn-secondary ranking-tab-btn" onclick="showRankTab('alltime')" id="rtab-alltime"
                role="tab">
                <i class="bi bi-list"></i><span>Tất cả thời gian</span>
            </button>
            <button type="button" class="btn btn-primary ranking-tab-btn" onclick="showRankTab('teams')" id="rtab-teams"
                role="tab">
                <i class="bi bi-people"></i><span>Đội nhóm</span>
            </button>
            <button type="button" class="btn btn-primary ranking-tab-btn" onclick="showRankTab('monthly')" id="rtab-monthly"
                role="tab">
                <i class="bi bi-calendar"></i><span>Tháng</span>
            </button>
            <button type="button" class="btn btn-primary ranking-tab-btn" onclick="showRankTab('yearly')" id="rtab-yearly"
                role="tab">
                <i class="bi bi-calendar"></i><span>Năm</span>
            </button>
        </div>
    </div>

    {{-- ══ SHARED: reusable ranking table macro ═══════════════════════════════
         Parameters passed via PHP block before each include-like block:
           $rows         — iterable of employee objects
           $scoreKey     — attribute name for the score to display ('total_score'|'display_score')
           $showZone     — bool: show zone badge next to score
           $showTeam     — bool: show team column
           $colSpan      — total column count for empty state
           $emptyMsg     — empty state message
    ──────────────────────────────────────────────────────────────────────── --}}

    {{-- ══ TAB: ALL-TIME ══════════════════════════════════════════════════════ --}}
    <div id="ranking-panel-alltime" class="ranking-panel">
        <div class="card overflow-hidden">
            <div class="card-header">
                <h3 class="font-semibold text-slate-900 dark:text-white">Xếp hạng điểm tích luỹ</h3>
                <p class="text-xs text-slate-400 mt-0.5">Zone hiển thị theo tháng hiện tại</p>
            </div>

            {{-- Desktop table --}}
            <div class="hidden sm:block">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th class="table-th w-14 pl-4">#</th>
                            <th class="table-th">Nhân viên</th>
                            <th class="table-th">Chi nhánh · Đội</th>
                            <th class="table-th text-center">Tổng điểm</th>
                            <th class="table-th text-center w-28">Zone tháng này</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employees as $index => $emp)
                            @php
                                $zone = $emp->zone ?: 'green';
                                $score = $emp->total_score ?: 0;
                                $isTop = $index < 3;
                                $rowBg = $isTop ? $podiumRowBg[$index] : $zoneRowBg($zone);
                                $bdCls = 'border-l-4 ' . ($isTop ? $podiumBorder[$index] : $zoneBorder($zone));
                            @endphp
                            <tr class="{{ $rowBg }} transition-colors" style="animation-delay:{{ $index * 30 }}ms">
                                {{-- Rank cell with left border --}}
                                <td class="table-td pl-4 {{ $bdCls }}">
                                    @if ($isTop)
                                        <div
                                            class="flex items-center justify-center w-9 h-9 rounded-full
                                        {{ $index === 0 ? 'bg-amber-100 dark:bg-amber-900/40' : ($index === 1 ? 'bg-slate-200 dark:bg-slate-700' : 'bg-orange-100 dark:bg-orange-900/30') }}
                                        text-xl select-none">
                                            {{ $podiumMedal[$index] }}</div>
                                    @else
                                        <span
                                            class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-slate-100 dark:bg-slate-700 text-xs font-bold text-slate-500 dark:text-slate-400">{{ $index + 1 }}</span>
                                    @endif
                                </td>
                                {{-- Name --}}
                                <td class="table-td">
                                    <div class="flex items-center gap-2.5">
                                        <div
                                            class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0
                                        {{ $isTop ? $podiumAvatarBg[$index] : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300' }}">
                                            {{ strtoupper(mb_substr($emp->name, 0, 2)) }}
                                        </div>
                                        <div>
                                            <a href="{{ route('employees.show', $emp) }}"
                                                class="font-semibold text-sm hover:underline
                                            {{ $isTop ? ($index === 0 ? 'text-amber-700 dark:text-amber-400' : ($index === 1 ? 'text-slate-600 dark:text-slate-300' : 'text-orange-700 dark:text-orange-400')) : 'text-slate-900 dark:text-white' }}">
                                                {{ $emp->name }}
                                            </a>
                                            @if ($isTop)
                                                <div
                                                    class="text-[10px] font-semibold uppercase tracking-wider mt-0.5
                                                {{ $index === 0 ? 'text-amber-500' : ($index === 1 ? 'text-slate-400' : 'text-orange-500') }}">
                                                    {{ $podiumLabel[$index] }}
                                                </div>
                                            @else
                                                <div class="text-xs text-slate-400">{{ $emp->code }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                {{-- Branch · Team --}}
                                <td class="table-td text-sm text-slate-500 dark:text-slate-400">
                                    {{ $emp->branch->name ?? '—' }}
                                    @if ($emp->team)
                                        · {{ $emp->team->name }}
                                    @endif
                                </td>
                                {{-- Score --}}
                                <td class="table-td text-center">
                                    <span
                                        class="text-lg font-extrabold {{ $isTop ? ($index === 0 ? 'text-amber-600 dark:text-amber-400' : ($index === 1 ? 'text-slate-500 dark:text-slate-300' : 'text-orange-600 dark:text-orange-400')) : $zoneScoreColor($zone) }}">
                                        {{ number_format($score) }}
                                    </span>
                                </td>
                                {{-- Zone badge --}}
                                <td class="table-td text-center">
                                    <span
                                        class="inline-flex items-center gap-1 text-xs px-2.5 py-1 rounded-full font-semibold {{ $zoneBadgeCss($zone) }}">
                                        {{ $zone === 'green' ? '🟢' : ($zone === 'yellow' ? '🟡' : ($zone === 'orange' ? '🟠' : '🔴')) }}
                                        {{ \App\Models\MonthlyEmployeeScore::zoneLabel($zone) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="table-td text-center py-10 text-slate-400"><i
                                        class="ph-trophy text-3xl mb-2 block"></i>Chưa có dữ liệu</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Mobile cards --}}
            <div class="sm:hidden divide-y divide-slate-100 dark:divide-slate-700/50">
                @forelse($employees as $index => $emp)
                    @php
                        $zone = $emp->zone ?? 'green';
                        $score = $emp->total_score ?? 0;
                        $isTop = $index < 3;
                    @endphp
                    <a href="{{ route('employees.show', $emp) }}"
                        class="flex items-center gap-3 px-4 py-3 {{ $isTop ? $podiumRowBg[$index] : $zoneRowBg($zone) }} border-l-4 {{ $isTop ? $podiumBorder[$index] : $zoneBorder($zone) }}">
                        <div class="flex-shrink-0">
                            @if ($isTop)
                                <div
                                    class="w-10 h-10 rounded-full flex items-center justify-center text-2xl
                                {{ $index === 0 ? 'bg-amber-100 dark:bg-amber-900/40' : ($index === 1 ? 'bg-slate-200 dark:bg-slate-700' : 'bg-orange-100 dark:bg-orange-900/30') }}">
                                    {{ $podiumMedal[$index] }}
                                </div>
                            @else
                                <div
                                    class="w-10 h-10 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-sm font-bold text-slate-500">
                                    {{ $index + 1 }}</div>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-sm text-slate-900 dark:text-white truncate">{{ $emp->name }}
                            </div>
                            <div class="text-xs text-slate-400 truncate">{{ $emp->branch->name ?? '—' }} ·
                                {{ $emp->team->name ?? '—' }}</div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <div
                                class="font-extrabold text-base {{ $isTop ? ($index === 0 ? 'text-amber-600 dark:text-amber-400' : ($index === 1 ? 'text-slate-500' : 'text-orange-600 dark:text-orange-400')) : $zoneScoreColor($zone) }}">
                                {{ number_format($score) }}
                            </div>
                            <div
                                class="text-[10px] mt-0.5 {{ $zoneBadgeCss($zone) }} inline-block px-1.5 py-0.5 rounded-full font-medium">
                                {{ \App\Models\MonthlyEmployeeScore::zoneLabel($zone) }}
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="text-center py-10 text-slate-400"><i class="ph-trophy text-3xl mb-2 block"></i>Chưa có dữ
                        liệu</div>
                @endforelse
            </div>

            @if ($employees->hasPages())
                <div class="card-footer">{{ $employees->links() }}</div>
            @endif
        </div>
    </div>

    {{-- ══ TAB: TEAMS ═════════════════════════════════════════════════════════ --}}
    <div id="ranking-panel-teams" class="ranking-panel hidden">
        <div class="card overflow-hidden">
            <div class="card-header">
                <h3 class="font-semibold text-slate-900 dark:text-white">Xếp hạng đội nhóm</h3>
            </div>
            <div class="hidden sm:block">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th class="table-th w-14 pl-4">#</th>
                            <th class="table-th">Đội nhóm</th>
                            <th class="table-th">Chi nhánh</th>
                            <th class="table-th text-center">Số NV</th>
                            <th class="table-th text-center">Điểm TB</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($teams as $index => $team)
                            @php $isTop = $index < 3; @endphp
                            <tr class="{{ $isTop ? $podiumRowBg[$index] : 'table-tr-hover' }}">
                                <td
                                    class="table-td pl-4 border-l-4 {{ $isTop ? $podiumBorder[$index] : 'border-l-transparent' }}">
                                    @if ($isTop)
                                        <div
                                            class="flex items-center justify-center w-9 h-9 rounded-full
                                        {{ $index === 0 ? 'bg-amber-100 dark:bg-amber-900/40' : ($index === 1 ? 'bg-slate-200 dark:bg-slate-700' : 'bg-orange-100 dark:bg-orange-900/30') }}
                                        text-xl select-none">
                                            {{ $podiumMedal[$index] }}</div>
                                    @else
                                        <span
                                            class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-slate-100 dark:bg-slate-700 text-xs font-bold text-slate-500">{{ $index + 1 }}</span>
                                    @endif
                                </td>
                                <td
                                    class="table-td font-semibold text-sm {{ $isTop && $index === 0 ? 'text-amber-700 dark:text-amber-400' : ($isTop && $index === 1 ? 'text-slate-600 dark:text-slate-300' : ($isTop ? 'text-orange-700 dark:text-orange-400' : 'text-slate-900 dark:text-white')) }}">
                                    {{ $team->name }}
                                    @if ($isTop)
                                        <div
                                            class="text-[10px] font-semibold uppercase tracking-wider mt-0.5 {{ $index === 0 ? 'text-amber-500' : ($index === 1 ? 'text-slate-400' : 'text-orange-500') }}">
                                            {{ $podiumLabel[$index] }}</div>
                                    @endif
                                </td>
                                <td class="table-td text-sm text-slate-500 dark:text-slate-400">
                                    {{ $team->branch->name ?? '—' }}</td>
                                <td class="table-td text-center text-sm">{{ $team->employees_count ?? 0 }}</td>
                                <td class="table-td text-center">
                                    <span
                                        class="text-lg font-extrabold {{ $isTop ? ($index === 0 ? 'text-amber-600 dark:text-amber-400' : ($index === 1 ? 'text-slate-500 dark:text-slate-300' : 'text-orange-600 dark:text-orange-400')) : 'text-slate-900 dark:text-white' }}">
                                        {{ number_format($team->average_score, 1) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="table-td text-center py-10 text-slate-400"><i
                                        class="ph-user-squares text-3xl mb-2 block"></i>Chưa có đội nhóm</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="sm:hidden divide-y divide-slate-100 dark:divide-slate-700/50">
                @forelse($teams as $index => $team)
                    @php $isTop = $index < 3; @endphp
                    <div
                        class="flex items-center gap-3 px-4 py-3 {{ $isTop ? $podiumRowBg[$index] : '' }} border-l-4 {{ $isTop ? $podiumBorder[$index] : 'border-l-transparent' }}">
                        <div class="flex-shrink-0">
                            @if ($isTop)
                                <div
                                    class="w-10 h-10 rounded-full flex items-center justify-center text-2xl
                                {{ $index === 0 ? 'bg-amber-100 dark:bg-amber-900/40' : ($index === 1 ? 'bg-slate-200 dark:bg-slate-700' : 'bg-orange-100 dark:bg-orange-900/30') }}">
                                    {{ $podiumMedal[$index] }}
                                </div>
                            @else
                                <div
                                    class="w-10 h-10 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-sm font-bold text-slate-500">
                                    {{ $index + 1 }}</div>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-sm text-slate-900 dark:text-white truncate">{{ $team->name }}
                            </div>
                            <div class="text-xs text-slate-400 truncate">{{ $team->branch->name ?? '—' }} ·
                                {{ $team->employees_count ?? 0 }} người</div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <div
                                class="font-extrabold text-base {{ $isTop ? ($index === 0 ? 'text-amber-600 dark:text-amber-400' : ($index === 1 ? 'text-slate-500' : 'text-orange-600 dark:text-orange-400')) : 'text-slate-900 dark:text-white' }}">
                                {{ number_format($team->average_score, 1) }}
                            </div>
                            <div class="text-[10px] text-slate-400">điểm TB</div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-10 text-slate-400"><i class="ph-user-squares text-3xl mb-2 block"></i>Chưa
                        có đội nhóm</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ══ TAB: MONTHLY AWARD ═════════════════════════════════════════════════ --}}
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
                        <i class="ph-magnifying-glass"></i> Xem
                    </button>
                </form>
            </div>
        </div>

        @if ($employeeOfMonth)
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
                {{-- Spotlight --}}
                <div class="lg:col-span-1">
                    <div
                        class="card bg-gradient-to-br from-amber-50 to-yellow-100 dark:from-amber-950/40 dark:to-yellow-950/20 border-amber-200 dark:border-amber-800 border h-full">
                        <div class="card-body text-center py-8">
                            <div class="text-6xl mb-2 select-none">🏆</div>
                            <div
                                class="text-[11px] font-bold uppercase tracking-widest text-amber-600 dark:text-amber-400 mb-3">
                                Nhân viên xuất sắc tháng
                                {{ str_pad($evalMonth, 2, '0', STR_PAD_LEFT) }}/{{ $evalYear }}
                            </div>
                            <div
                                class="w-16 h-16 rounded-full bg-amber-200 dark:bg-amber-800 flex items-center justify-center text-2xl font-black text-amber-800 dark:text-amber-200 mx-auto mb-3">
                                {{ strtoupper(mb_substr($employeeOfMonth->name, 0, 2)) }}
                            </div>
                            <h3 class="text-xl font-black text-slate-900 dark:text-white mb-0.5">
                                {{ $employeeOfMonth->name }}</h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">
                                {{ $employeeOfMonth->branch->name ?? '—' }} · {{ $employeeOfMonth->team->name ?? '—' }}
                            </p>
                            @php $mzone = $employeeOfMonth->zone; @endphp
                            <div
                                class="inline-flex items-center gap-2 bg-white/70 dark:bg-slate-800/50 rounded-full px-4 py-2 mb-3">
                                <span
                                    class="text-2xl font-extrabold {{ $zoneScoreColor($mzone) }}">{{ $employeeOfMonth->display_score }}</span>
                                <span class="text-xs text-slate-500">điểm</span>
                                <span class="text-xs px-2 py-0.5 rounded-full font-semibold {{ $zoneBadgeCss($mzone) }}">
                                    {{ \App\Models\MonthlyEmployeeScore::zoneLabel($mzone) }}
                                </span>
                            </div>
                            <div>
                                <a href="{{ route('employees.show', $employeeOfMonth) }}"
                                    class="btn-secondary btn-sm text-xs">
                                    <i class="ph-eye"></i> Xem hồ sơ
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Monthly leaderboard --}}
                <div class="lg:col-span-2">
                    <div class="card overflow-hidden h-full">
                        <div class="card-header">
                            <h3 class="font-semibold text-slate-900 dark:text-white">
                                Top nhân viên tháng {{ str_pad($evalMonth, 2, '0', STR_PAD_LEFT) }}/{{ $evalYear }}
                            </h3>
                        </div>
                        <table class="table-base">
                            <thead>
                                <tr>
                                    <th class="table-th w-14 pl-4">#</th>
                                    <th class="table-th">Nhân viên</th>
                                    <th class="table-th text-center">Điểm · Zone</th>
                                    <th class="table-th text-center hidden sm:table-cell">Trừ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($monthlyRanking->take(10) as $i => $emp)
                                    @php
                                        $z = $emp->zone ?? 'green';
                                        $isTop = $i < 3;
                                        $rowBg = $isTop ? $podiumRowBg[$i] : $zoneRowBg($z);
                                        $bdCls = 'border-l-4 ' . ($isTop ? $podiumBorder[$i] : $zoneBorder($z));
                                    @endphp
                                    <tr class="{{ $rowBg }}">
                                        <td class="table-td pl-4 {{ $bdCls }}">
                                            @if ($isTop)
                                                <div
                                                    class="flex items-center justify-center w-8 h-8 rounded-full text-lg
                                            {{ $i === 0 ? 'bg-amber-100 dark:bg-amber-900/40' : ($i === 1 ? 'bg-slate-200 dark:bg-slate-700' : 'bg-orange-100 dark:bg-orange-900/30') }}">
                                                    {{ $podiumMedal[$i] }}
                                                </div>
                                            @else
                                                <span
                                                    class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-slate-100 dark:bg-slate-700 text-xs font-bold text-slate-500">{{ $i + 1 }}</span>
                                            @endif
                                        </td>
                                        <td class="table-td">
                                            <a href="{{ route('employees.show', $emp) }}"
                                                class="font-semibold text-sm hover:underline
                                        {{ $isTop ? ($i === 0 ? 'text-amber-700 dark:text-amber-400' : ($i === 1 ? 'text-slate-600 dark:text-slate-300' : 'text-orange-700 dark:text-orange-400')) : 'text-slate-900 dark:text-white' }}">
                                                {{ $emp->name }}
                                            </a>
                                            <div class="text-xs text-slate-400">{{ $emp->code }}</div>
                                        </td>
                                        <td class="table-td text-center">
                                            <span
                                                class="font-extrabold text-base {{ $isTop ? ($i === 0 ? 'text-amber-600 dark:text-amber-400' : ($i === 1 ? 'text-slate-500 dark:text-slate-300' : 'text-orange-600 dark:text-orange-400')) : $zoneScoreColor($z) }}">
                                                {{ $emp->display_score }}
                                            </span>
                                            <div class="mt-0.5">
                                                <span
                                                    class="text-[10px] px-1.5 py-0.5 rounded-full font-medium {{ $zoneBadgeCss($z) }}">
                                                    {{ $z === 'green' ? '🟢' : ($z === 'yellow' ? '🟡' : ($z === 'orange' ? '🟠' : '🔴')) }}
                                                    {{ \App\Models\MonthlyEmployeeScore::zoneLabel($z) }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="table-td text-center hidden sm:table-cell text-sm">
                                            @if (($emp->deducted ?? 0) > 0)
                                                <span class="text-red-500 font-medium">-{{ $emp->deducted }}</span>
                                            @else
                                                <span class="text-slate-300 dark:text-slate-600">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="table-td text-center py-8 text-slate-400">Chưa có dữ
                                            liệu tháng này</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-body text-center py-12 text-slate-400">
                    <i class="ph-trophy text-4xl mb-2 block"></i>
                    <p>Chưa có dữ liệu điểm tháng {{ str_pad($evalMonth, 2, '0', STR_PAD_LEFT) }}/{{ $evalYear }}</p>
                    <p class="text-xs mt-1">Chạy <code class="bg-slate-100 dark:bg-slate-700 px-1 rounded">php artisan
                            scores:reset-monthly</code> để khởi tạo.</p>
                </div>
            </div>
        @endif
    </div>

    {{-- ══ TAB: YEARLY AWARD ══════════════════════════════════════════════════ --}}
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
                        <i class="ph-magnifying-glass"></i> Xem
                    </button>
                </form>
            </div>
        </div>

        @if ($employeeOfYear)
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                {{-- Yearly spotlight --}}
                <div class="lg:col-span-1">
                    <div
                        class="card bg-gradient-to-br from-pcrm-50 to-indigo-100 dark:from-pcrm-950/40 dark:to-indigo-950/20 border-pcrm-200 dark:border-pcrm-800 border h-full">
                        <div class="card-body text-center py-8">
                            <div class="text-6xl mb-2 select-none">🥇</div>
                            <div
                                class="text-[11px] font-bold uppercase tracking-widest text-pcrm-600 dark:text-pcrm-400 mb-3">
                                Nhân viên xuất sắc năm {{ $evalYearOnly }}
                            </div>
                            <div
                                class="w-16 h-16 rounded-full bg-pcrm-200 dark:bg-pcrm-800 flex items-center justify-center text-2xl font-black text-pcrm-800 dark:text-pcrm-200 mx-auto mb-3">
                                {{ strtoupper(mb_substr($employeeOfYear->name, 0, 2)) }}
                            </div>
                            <h3 class="text-xl font-black text-slate-900 dark:text-white mb-0.5">
                                {{ $employeeOfYear->name }}</h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">
                                {{ $employeeOfYear->branch->name ?? '—' }} · {{ $employeeOfYear->team->name ?? '—' }}
                            </p>
                            @php $yzone = $employeeOfYear->zone; @endphp
                            <div
                                class="inline-flex items-center gap-2 bg-white/70 dark:bg-slate-800/50 rounded-full px-4 py-2 mb-1">
                                <span
                                    class="text-2xl font-extrabold {{ $zoneScoreColor($yzone) }}">{{ $employeeOfYear->display_score }}</span>
                                <span class="text-xs text-slate-500">điểm TB</span>
                            </div>
                            <div class="text-xs text-slate-400 mb-3">
                                {{ $employeeOfYear->months_logged }} tháng ghi nhận
                                @if ($employeeOfYear->months_in_red > 0)
                                    · <span class="text-red-500">{{ $employeeOfYear->months_in_red }}🔴 redzone</span>
                                @endif
                            </div>
                            <a href="{{ route('employees.show', $employeeOfYear) }}"
                                class="btn-secondary btn-sm text-xs">
                                <i class="ph-eye"></i> Xem hồ sơ
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Yearly leaderboard --}}
                <div class="lg:col-span-2">
                    <div class="card overflow-hidden h-full">
                        <div class="card-header">
                            <h3 class="font-semibold text-slate-900 dark:text-white">
                                Bảng xếp hạng năm {{ $evalYearOnly }} (điểm trung bình/tháng)
                            </h3>
                        </div>
                        <table class="table-base">
                            <thead>
                                <tr>
                                    <th class="table-th w-14 pl-4">#</th>
                                    <th class="table-th">Nhân viên</th>
                                    <th class="table-th text-center">Điểm TB · Zone</th>
                                    <th class="table-th text-center hidden sm:table-cell">Tháng</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($yearlyRanking->take(10) as $i => $emp)
                                    @php
                                        $z = $emp->zone ?? 'green';
                                        $isTop = $i < 3;
                                        $rowBg = $isTop ? $podiumRowBg[$i] : $zoneRowBg($z);
                                        $bdCls = 'border-l-4 ' . ($isTop ? $podiumBorder[$i] : $zoneBorder($z));
                                    @endphp
                                    <tr class="{{ $rowBg }}">
                                        <td class="table-td pl-4 {{ $bdCls }}">
                                            @if ($isTop)
                                                <div
                                                    class="flex items-center justify-center w-8 h-8 rounded-full text-lg
                                            {{ $i === 0 ? 'bg-amber-100 dark:bg-amber-900/40' : ($i === 1 ? 'bg-slate-200 dark:bg-slate-700' : 'bg-orange-100 dark:bg-orange-900/30') }}">
                                                    {{ $podiumMedal[$i] }}
                                                </div>
                                            @else
                                                <span
                                                    class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-slate-100 dark:bg-slate-700 text-xs font-bold text-slate-500">{{ $i + 1 }}</span>
                                            @endif
                                        </td>
                                        <td class="table-td">
                                            <a href="{{ route('employees.show', $emp) }}"
                                                class="font-semibold text-sm hover:underline
                                        {{ $isTop ? ($i === 0 ? 'text-amber-700 dark:text-amber-400' : ($i === 1 ? 'text-slate-600 dark:text-slate-300' : 'text-orange-700 dark:text-orange-400')) : 'text-slate-900 dark:text-white' }}">
                                                {{ $emp->name }}
                                            </a>
                                            <div class="text-xs text-slate-400">{{ $emp->code }}</div>
                                        </td>
                                        <td class="table-td text-center">
                                            <span
                                                class="font-extrabold text-base {{ $isTop ? ($i === 0 ? 'text-amber-600 dark:text-amber-400' : ($i === 1 ? 'text-slate-500 dark:text-slate-300' : 'text-orange-600 dark:text-orange-400')) : $zoneScoreColor($z) }}">
                                                {{ $emp->display_score }}
                                            </span>
                                            <div class="mt-0.5">
                                                <span
                                                    class="text-[10px] px-1.5 py-0.5 rounded-full font-medium {{ $zoneBadgeCss($z) }}">
                                                    {{ $z === 'green' ? '🟢' : ($z === 'yellow' ? '🟡' : ($z === 'orange' ? '🟠' : '🔴')) }}
                                                    {{ \App\Models\MonthlyEmployeeScore::zoneLabel($z) }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="table-td text-center hidden sm:table-cell">
                                            <span class="text-sm text-slate-500">{{ $emp->months_logged }}</span>
                                            @if ($emp->months_in_red > 0)
                                                <span class="ml-1 text-xs text-red-500">·
                                                    {{ $emp->months_in_red }}🔴</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="table-td text-center py-8 text-slate-400">Chưa có dữ
                                            liệu năm {{ $evalYearOnly }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-body text-center py-12 text-slate-400">
                    <i class="ph-medal text-4xl mb-2 block"></i>
                    <p>Chưa có dữ liệu điểm năm {{ $evalYearOnly }}</p>
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
