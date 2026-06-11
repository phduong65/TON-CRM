@extends('layouts.admin')

@section('title', 'Phân vùng điểm')
@section('page-title', 'Phân vùng điểm')
@section('breadcrumb', 'Phân tích / Phân vùng điểm')

@section('content')

    {{-- ── Header ──────────────────────────────────────────────────────────────── --}}
    <div class="page-header flex-wrap gap-3 mb-6">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-medium text-slate-500 dark:text-slate-400">
                Tháng <strong class="text-slate-700 dark:text-slate-200 font-semibold">{{ str_pad($month, 2, '0', STR_PAD_LEFT) }}/{{ $year }}</strong>
            </span>
            <span class="inline-flex items-center gap-1.5 text-xs font-medium bg-white dark:bg-slate-800 text-emerald-700 dark:text-emerald-400 px-2.5 py-1 rounded-full border border-slate-200 dark:border-slate-700">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 flex-shrink-0"></span>Green ≥{{ $greenMin }}đ
            </span>
            <span class="inline-flex items-center gap-1.5 text-xs font-medium bg-white dark:bg-slate-800 text-yellow-700 dark:text-yellow-400 px-2.5 py-1 rounded-full border border-slate-200 dark:border-slate-700">
                <span class="w-1.5 h-1.5 rounded-full bg-yellow-400 flex-shrink-0"></span>Yellow {{ $yellowMin }}–{{ $greenMin-1 }}đ
            </span>
            <span class="inline-flex items-center gap-1.5 text-xs font-medium bg-white dark:bg-slate-800 text-orange-700 dark:text-orange-400 px-2.5 py-1 rounded-full border border-slate-200 dark:border-slate-700">
                <span class="w-1.5 h-1.5 rounded-full bg-orange-500 flex-shrink-0"></span>Orange {{ $orangeMin }}–{{ $yellowMin-1 }}đ
            </span>
            <span class="inline-flex items-center gap-1.5 text-xs font-medium bg-white dark:bg-slate-800 text-red-700 dark:text-red-400 px-2.5 py-1 rounded-full border border-slate-200 dark:border-slate-700">
                <span class="w-1.5 h-1.5 rounded-full bg-red-500 flex-shrink-0"></span>Red &lt;{{ $orangeMin }}đ
            </span>
        </div>
        <form method="GET" action="{{ route('redzone.index') }}" class="flex items-center gap-2">
            <select name="month" class="form-select form-input w-auto text-sm">
                @foreach($monthOptions as $opt)
                    <option value="{{ $opt['month'] }}" data-year="{{ $opt['year'] }}"
                        {{ ($opt['month'] == $month && $opt['year'] == $year) ? 'selected' : '' }}>
                        {{ $opt['label'] }}
                    </option>
                @endforeach
            </select>
            <input type="hidden" name="year" id="yearInput" value="{{ $year }}">
            <button type="submit" class="btn btn-primary text-sm">
                <i class="bi bi-filter"></i> Lọc
            </button>
        </form>
    </div>

    {{-- ── Zone Summary Cards ───────────────────────────────────────────────────── --}}
    @php
        $totalEmployees = array_sum(array_map(fn($g) => count($g), $byZone));
        $zoneConfig = [
            'green'  => [
                'label'   => 'Greenzone',
                'desc'    => 'Xuất sắc',
                'range'   => '≥'.$greenMin.'đ',
                'bar'     => 'bg-emerald-500',
                'bg'      => 'bg-emerald-50 dark:bg-emerald-950/25',
                'border'  => 'border-emerald-200 dark:border-emerald-800/50',
                'ring'    => 'ring-emerald-300 dark:ring-emerald-700',
                'num'     => 'text-emerald-600 dark:text-emerald-400',
                'desc_c'  => 'text-emerald-700/70 dark:text-emerald-400/60',
                'dot'     => 'bg-emerald-500',
                'icon'    => 'ph-seal-check',
                'icon_c'  => 'text-emerald-500 dark:text-emerald-400',
            ],
            'yellow' => [
                'label'   => 'Yellowzone',
                'desc'    => 'Khá',
                'range'   => $yellowMin.'–'.($greenMin-1).'đ',
                'bar'     => 'bg-yellow-400',
                'bg'      => 'bg-yellow-50 dark:bg-yellow-950/25',
                'border'  => 'border-yellow-200 dark:border-yellow-800/50',
                'ring'    => 'ring-yellow-300 dark:ring-yellow-700',
                'num'     => 'text-yellow-600 dark:text-yellow-400',
                'desc_c'  => 'text-yellow-700/70 dark:text-yellow-400/60',
                'dot'     => 'bg-yellow-400',
                'icon'    => 'ph-trend-up',
                'icon_c'  => 'text-yellow-500 dark:text-yellow-400',
            ],
            'orange' => [
                'label'   => 'Orangezone',
                'desc'    => 'Cảnh báo',
                'range'   => $orangeMin.'–'.($yellowMin-1).'đ',
                'bar'     => 'bg-orange-500',
                'bg'      => 'bg-orange-50 dark:bg-orange-950/25',
                'border'  => 'border-orange-200 dark:border-orange-800/50',
                'ring'    => 'ring-orange-300 dark:ring-orange-700',
                'num'     => 'text-orange-600 dark:text-orange-400',
                'desc_c'  => 'text-orange-700/70 dark:text-orange-400/60',
                'dot'     => 'bg-orange-500',
                'icon'    => 'ph-warning',
                'icon_c'  => 'text-orange-500 dark:text-orange-400',
            ],
            'red'    => [
                'label'   => 'Redzone',
                'desc'    => 'Nguy hiểm',
                'range'   => '<'.$orangeMin.'đ',
                'bar'     => 'bg-red-500',
                'bg'      => 'bg-red-50 dark:bg-red-950/25',
                'border'  => 'border-red-200 dark:border-red-800/50',
                'ring'    => 'ring-red-300 dark:ring-red-700',
                'num'     => 'text-red-600 dark:text-red-400',
                'desc_c'  => 'text-red-700/70 dark:text-red-400/60',
                'dot'     => 'bg-red-500',
                'icon'    => 'ph-warning-octagon',
                'icon_c'  => 'text-red-500 dark:text-red-400',
            ],
        ];
    @endphp

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        @foreach($zoneConfig as $zoneKey => $z)
        @php $count = count($byZone[$zoneKey]); $pct = $totalEmployees > 0 ? round($count / $totalEmployees * 100) : 0; @endphp
        <button type="button"
                onclick="showZone('{{ $zoneKey }}')"
                id="summary-card-{{ $zoneKey }}"
                class="zone-summary-card group text-left w-full rounded-xl border {{ $z['border'] }} {{ $z['bg'] }} p-4 transition-all duration-200 cursor-pointer hover:shadow-md focus:outline-none focus-visible:ring-2 {{ $z['ring'] }}">
            {{-- Label + icon --}}
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-1.5">
                    <i class="{{ $z['icon'] }} {{ $z['icon_c'] }} text-base flex-shrink-0"></i>
                    <span class="text-xs font-bold {{ $z['num'] }} uppercase tracking-wide">{{ $z['label'] }}</span>
                </div>
                <span class="text-xs {{ $z['desc_c'] }} font-medium">{{ $z['range'] }}</span>
            </div>
            {{-- Count + percentage --}}
            <div class="flex items-end justify-between gap-2 mb-3">
                <span class="text-[2.5rem] font-black {{ $z['num'] }} leading-none tabular-nums">{{ $count }}</span>
                <div class="text-right pb-1">
                    <div class="text-xs {{ $z['desc_c'] }} leading-tight">{{ $z['desc'] }}</div>
                    <div class="text-sm font-bold {{ $z['num'] }} tabular-nums">{{ $pct }}%</div>
                </div>
            </div>
            {{-- Progress bar --}}
            <div class="w-full bg-white/50 dark:bg-slate-700/30 rounded-full h-1.5 overflow-hidden">
                <div class="{{ $z['bar'] }} h-1.5 rounded-full transition-all duration-700"
                     style="width: {{ $pct }}%"></div>
            </div>
        </button>
        @endforeach
    </div>

    {{-- ── Consecutive Redzone Alert ────────────────────────────────────────────── --}}
    @if(count($consecutiveRedzoneIds) > 0)
    <div class="rounded-xl border border-red-200 dark:border-red-800/70 bg-red-50 dark:bg-red-950/40 overflow-hidden mb-6">
        <div class="flex gap-3 p-4">
            <div class="flex-shrink-0 mt-0.5">
                <div class="w-8 h-8 rounded-lg bg-red-100 dark:bg-red-900/60 flex items-center justify-center">
                    <i class="ph-warning-octagon text-base text-red-600 dark:text-red-400 dash-redzone-blink"></i>
                </div>
            </div>
            <div class="min-w-0 flex-1">
                <p class="font-semibold text-red-800 dark:text-red-300 text-sm leading-snug">
                    {{ count($consecutiveRedzoneIds) }} nhân viên ở Redzone liên tiếp {{ $consecutiveMonths }} tháng.
                    <span class="font-normal text-red-700/80 dark:text-red-400/80">Cần xử lý khẩn cấp.</span>
                </p>
                <div class="flex flex-wrap gap-1.5 mt-2.5">
                    @foreach($byZone['red'] as $emp)
                        @if(in_array($emp->id, $consecutiveRedzoneIds))
                        <a href="{{ route('employees.show', $emp) }}"
                           class="inline-flex items-center gap-1 text-xs font-semibold bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-800 px-2.5 py-1 rounded-full hover:bg-red-200 dark:hover:bg-red-800/60 transition-colors">
                            <i class="ph-user text-xs"></i>{{ $emp->name }}
                        </a>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
        <div class="h-px bg-gradient-to-r from-red-400 via-red-300 to-transparent opacity-40 mx-4"></div>
    </div>
    @endif

    {{-- ── Pill Tab Navigation ──────────────────────────────────────────────────── --}}
    <div class="flex items-center gap-2 overflow-x-auto mb-3">
        @php
            $tabDefs = [
                ['id' => 'green',  'label' => 'Greenzone',  'dot' => 'bg-emerald-500'],
                ['id' => 'yellow', 'label' => 'Yellowzone', 'dot' => 'bg-yellow-400'],
                ['id' => 'orange', 'label' => 'Orangezone', 'dot' => 'bg-orange-500'],
                ['id' => 'red',    'label' => 'Redzone',    'dot' => 'bg-red-500'],
            ];
        @endphp
        @foreach($tabDefs as $tab)
        @php $cnt = count($byZone[$tab['id']]); @endphp
        <button type="button"
                id="zone-tab-{{ $tab['id'] }}"
                onclick="showZone('{{ $tab['id'] }}')"
                class="zone-tab inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold whitespace-nowrap border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:border-slate-300 dark:hover:border-slate-600 transition-all duration-150 focus:outline-none"
                role="tab">
            <span class="w-2 h-2 rounded-full {{ $tab['dot'] }} flex-shrink-0"></span>
            {{ $tab['label'] }}
            <span class="zone-tab-count min-w-[1.25rem] h-5 px-1.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-bold inline-flex items-center justify-center transition-colors">{{ $cnt }}</span>
        </button>
        @endforeach
    </div>

    {{-- ── Zone Panels ──────────────────────────────────────────────────────────── --}}
    @foreach([
        ['zone' => 'green',  'title' => 'Greenzone',  'desc' => 'Xuất sắc — điểm cao, ít vi phạm',     'bar' => 'bg-emerald-500', 'score_text' => 'text-emerald-600 dark:text-emerald-400', 'avatar_bg' => 'bg-emerald-100 dark:bg-emerald-900/40', 'avatar_text' => 'text-emerald-700 dark:text-emerald-300'],
        ['zone' => 'yellow', 'title' => 'Yellowzone', 'desc' => 'Khá — cần chú ý hơn',                 'bar' => 'bg-yellow-400',  'score_text' => 'text-yellow-600 dark:text-yellow-400',   'avatar_bg' => 'bg-yellow-100 dark:bg-yellow-900/40',   'avatar_text' => 'text-yellow-700 dark:text-yellow-300'],
        ['zone' => 'orange', 'title' => 'Orangezone', 'desc' => 'Cảnh báo — cần cải thiện ngay',       'bar' => 'bg-orange-500',  'score_text' => 'text-orange-600 dark:text-orange-400',   'avatar_bg' => 'bg-orange-100 dark:bg-orange-900/40',   'avatar_text' => 'text-orange-700 dark:text-orange-300'],
        ['zone' => 'red',    'title' => 'Redzone',    'desc' => 'Nguy hiểm — cần xử lý khẩn cấp',     'bar' => 'bg-red-500',     'score_text' => 'text-red-600 dark:text-red-400',         'avatar_bg' => 'bg-red-100 dark:bg-red-900/40',         'avatar_text' => 'text-red-700 dark:text-red-300'],
    ] as $zd)
    <div id="zone-panel-{{ $zd['zone'] }}" class="zone-panel hidden">
        <div class="card">
            @if(count($byZone[$zd['zone']]) === 0)
                <div class="card-body py-20 text-center">
                    <div class="w-14 h-14 rounded-2xl bg-slate-100 dark:bg-slate-700/50 flex items-center justify-center mx-auto mb-4">
                        <i class="ph-users text-2xl text-slate-300 dark:text-slate-500"></i>
                    </div>
                    <h3 class="font-semibold text-slate-600 dark:text-slate-300 text-sm mb-1">Không có nhân viên nào trong {{ $zd['title'] }}</h3>
                    <p class="text-xs text-slate-400 dark:text-slate-500">Tháng {{ str_pad($month, 2, '0', STR_PAD_LEFT) }}/{{ $year }}</p>
                </div>
            @else
                {{-- Panel header --}}
                <div class="px-5 py-3.5 border-b border-slate-100 dark:border-slate-700 flex items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <span class="font-semibold text-slate-800 dark:text-slate-100 text-sm">{{ $zd['desc'] }}</span>
                        <span class="text-slate-400 dark:text-slate-500 text-xs ml-2 font-mono">{{ count($byZone[$zd['zone']]) }} nhân viên · Tháng {{ str_pad($month, 2, '0', STR_PAD_LEFT) }}/{{ $year }}</span>
                    </div>
                </div>

                {{-- Table --}}
                <div class="overflow-x-auto">
                    <table class="table-base">
                        <thead>
                            <tr>
                                <th class="table-th w-10 text-center">#</th>
                                <th class="table-th">Nhân viên</th>
                                <th class="table-th hidden md:table-cell">Chi nhánh / Đội</th>
                                <th class="table-th text-center">Điểm tháng</th>
                                <th class="table-th text-center">Điểm trừ</th>
                                @if($zd['zone'] === 'red')
                                <th class="table-th text-center">Cảnh báo</th>
                                @endif
                                <th class="table-th w-12"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($byZone[$zd['zone']] as $i => $emp)
                            @php
                                $isConsecutive = $zd['zone'] === 'red' && in_array($emp->id, $consecutiveRedzoneIds);
                                $pct = $defaultScore > 0 ? max(0, min(100, $emp->monthly_final_score / $defaultScore * 100)) : 0;
                            @endphp
                            <tr class="table-tr-hover {{ $isConsecutive ? 'bg-red-50/40 dark:bg-red-950/15' : '' }}">
                                <td class="table-td text-center text-slate-400 dark:text-slate-500 text-xs font-mono w-10">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</td>

                                <td class="table-td">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg {{ $zd['avatar_bg'] }} flex items-center justify-center {{ $zd['avatar_text'] }} font-bold text-xs flex-shrink-0 leading-none">
                                            {{ strtoupper(mb_substr($emp->name, 0, 2)) }}
                                        </div>
                                        <div class="min-w-0">
                                            <a href="{{ route('employees.show', $emp) }}"
                                               class="font-semibold text-slate-800 dark:text-slate-100 hover:text-pcrm-600 dark:hover:text-pcrm-400 text-sm leading-tight block truncate">
                                                {{ $emp->name }}
                                            </a>
                                            <span class="text-xs text-slate-400 dark:text-slate-500 font-mono">{{ $emp->code }}</span>
                                        </div>
                                    </div>
                                </td>

                                <td class="table-td hidden md:table-cell">
                                    <div class="text-sm text-slate-600 dark:text-slate-300">{{ $emp->branch->name ?? '-' }}</div>
                                    <div class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">{{ $emp->team->name ?? '-' }}</div>
                                </td>

                                <td class="table-td text-center">
                                    <div class="inline-flex flex-col items-center gap-1.5">
                                        <span class="text-xl font-black {{ $zd['score_text'] }} leading-none tabular-nums">{{ $emp->monthly_final_score }}</span>
                                    </div>
                                </td>

                                <td class="table-td text-center">
                                    @if($emp->monthly_deducted > 0)
                                        <span class="inline-flex items-center gap-1 text-lg font-bold text-red-600 dark:text-red-400  dark:border-red-900/60 px-2 py-0.5 rounded-md tabular-nums">
                                            <i class="bi bi-dash text-xl"></i>{{ $emp->monthly_deducted }}
                                        </span>
                                    @else
                                        <span class="text-slate-300 dark:text-slate-600 text-sm">-</span>
                                    @endif
                                </td>

                                @if($zd['zone'] === 'red')
                                <td class="table-td text-center">
                                    @if($isConsecutive)
                                        <span class="inline-flex items-center gap-1 text-xs font-bold text-red-700 dark:text-red-300 bg-red-100 dark:bg-red-900/50 border border-red-200 dark:border-red-800 px-2.5 py-1 rounded-full">
                                            <i class="ph-warning text-xs"></i>{{ $consecutiveMonths }}T LT
                                        </span>
                                    @else
                                        <span class="text-slate-300 dark:text-slate-600 text-xs">-</span>
                                    @endif
                                </td>
                                @endif

                                <td class="table-td text-right">
                                    <a href="{{ route('employees.show', $emp) }}"
                                       class="btn-ghost btn-sm text-xs">
                                        <i class="ph-arrow-right"></i>
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
    @endforeach

    <script>
        const zones = ['green', 'yellow', 'orange', 'red'];

        const zoneColors = {
            green:  '#10b981',
            yellow: '#eab308',
            orange: '#f97316',
            red:    '#ef4444',
        };

        function showZone(zone) {
            // Panels
            zones.forEach(z => {
                document.getElementById('zone-panel-' + z).classList.add('hidden');
            });
            document.getElementById('zone-panel-' + zone).classList.remove('hidden');

            // Tabs — reset all, activate current
            document.querySelectorAll('.zone-tab').forEach(btn => {
                btn.style.background = '';
                btn.style.color = '';
                btn.style.borderColor = '';
                const cnt = btn.querySelector('.zone-tab-count');
                if (cnt) { cnt.style.background = ''; cnt.style.color = ''; }
            });

            const activeBtn = document.getElementById('zone-tab-' + zone);
            if (activeBtn) {
                const color = zoneColors[zone];
                activeBtn.style.background = color;
                activeBtn.style.borderColor = color;
                activeBtn.style.color = '#fff';
                const cnt = activeBtn.querySelector('.zone-tab-count');
                if (cnt) { cnt.style.background = 'rgba(255,255,255,0.25)'; cnt.style.color = '#fff'; }
            }

            // Summary cards — dim inactive, highlight active
            document.querySelectorAll('.zone-summary-card').forEach(card => {
                card.style.opacity = '0.5';
                card.style.transform = 'scale(0.97)';
            });
            const activeCard = document.getElementById('summary-card-' + zone);
            if (activeCard) {
                activeCard.style.opacity = '1';
                activeCard.style.transform = 'scale(1)';
            }
        }

        // Sync year hidden input when month select changes
        document.querySelector('select[name="month"]')?.addEventListener('change', function () {
            const selected = this.options[this.selectedIndex];
            document.getElementById('yearInput').value = selected.getAttribute('data-year');
        });

        // Init: show greenzone first
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.zone-summary-card').forEach(card => {
                card.style.transition = 'all 0.2s ease';
            });
            showZone('green');
        });
    </script>
@endsection
