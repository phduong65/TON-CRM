@extends('layouts.admin')

@php
    $zoneMeta = [
        'red'    => ['label' => 'Redzone',    'desc' => 'Nguy hiểm',   'range' => '< ' . $orangeMin,                          'note' => 'Cần xử lý ngay: trao đổi, nhắc nhở, xem xét kỷ luật'],
        'orange' => ['label' => 'Orangezone', 'desc' => 'Cảnh báo',    'range' => $orangeMin . '–' . ($yellowMin - 1),        'note' => 'Có nguy cơ rơi vào Redzone — theo dõi sát'],
        'yellow' => ['label' => 'Yellowzone', 'desc' => 'Khá',         'range' => $yellowMin . '–' . ($greenMin - 1),         'note' => 'Cần chú ý thêm'],
        'green'  => ['label' => 'Greenzone',  'desc' => 'Tốt',         'range' => '≥ ' . $greenMin,                           'note' => 'Điểm cao, ít vi phạm'],
    ];
    $total = $zoneCounts->sum();
    $periodLabel = str_pad($month, 2, '0', STR_PAD_LEFT) . '/' . $year;
    $baseQuery = fn(array $set = [], array $drop = []) => array_filter(
        array_merge(request()->except(array_merge(['page'], $drop, array_keys($set))), $set),
        fn($v) => $v !== null && $v !== ''
    );
    $activeFilters = array_filter([
        'branch_id' => request('branch_id') ? 'Chi nhánh: ' . ($branches->firstWhere('id', (int) request('branch_id'))?->name ?? '#' . request('branch_id')) : null,
        'team_id'   => request('team_id') ? 'Đội: ' . ($teams->firstWhere('id', (int) request('team_id'))?->name ?? '#' . request('team_id')) : null,
        'search'    => request('search') ? 'Tìm: "' . request('search') . '"' : null,
    ]);
    $isWorkZone = in_array($zone, ['red', 'orange'], true);
@endphp

@section('title', 'Vùng điểm & Redzone')
@section('page-title', 'Vùng điểm & Redzone')
@section('breadcrumb', 'Kỷ luật / Redzone')

@section('page-subtitle')
    Điểm tháng = {{ $defaultScore }} − điểm trừ đã duyệt trong tháng. Redzone: dưới {{ $orangeMin }} điểm.
@endsection

@section('page-actions')
    <form method="GET" action="{{ route('redzone.index') }}" id="redzonePeriodForm" class="flex items-center gap-2">
        @foreach (request()->only(['zone', 'branch_id', 'team_id', 'search']) as $k => $v)
            @if ($v !== null && $v !== '') <input type="hidden" name="{{ $k }}" value="{{ $v }}"> @endif
        @endforeach
        <label for="redzoneMonth" class="sr-only">Tháng đánh giá</label>
        <select id="redzoneMonth" name="month" class="form-input w-auto text-sm" onchange="redzoneSyncYear(this); this.form.submit()">
            @foreach ($monthOptions as $opt)
                <option value="{{ $opt['month'] }}" data-year="{{ $opt['year'] }}" @selected($opt['month'] == $month && $opt['year'] == $year)>{{ $opt['label'] }}</option>
            @endforeach
        </select>
        <input type="hidden" name="year" id="redzoneYear" value="{{ $year }}">
        <noscript><button type="submit" class="btn-secondary">Xem</button></noscript>
    </form>
@endsection

@section('content')
<div class="space-y-5">

    {{-- ─── Phân bố vùng điểm: thanh tỷ lệ + tiêu chí ─────────────────────────── --}}
    <section class="card" aria-labelledby="zoneDistTitle">
        <div class="card-header flex flex-wrap items-center justify-between gap-2">
            <h2 id="zoneDistTitle">Phân bố tháng {{ $periodLabel }}</h2>
            <span class="text-xs text-slate-500 dark:text-slate-400">{{ number_format($total) }} nhân viên đang làm việc{{ $activeFilters ? ' (theo bộ lọc)' : '' }}</span>
        </div>
        <div class="card-body space-y-4">
            <div class="zone-bar" role="img"
                 aria-label="@foreach ($zoneMeta as $k => $z){{ $z['label'] }} {{ $zoneCounts[$k] }} người{{ $loop->last ? '' : ', ' }}@endforeach">
                @foreach ($zoneMeta as $k => $z)
                    @if ($zoneCounts[$k] > 0)
                        <span class="zone-bar-seg is-{{ $k }}" style="width: {{ $total ? round($zoneCounts[$k] / $total * 100, 2) : 0 }}%"></span>
                    @endif
                @endforeach
            </div>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                @foreach ($zoneMeta as $k => $z)
                    @php $pct = $total ? round($zoneCounts[$k] / $total * 100) : 0; @endphp
                    <a href="{{ route('redzone.index', $baseQuery(['zone' => $k])) }}"
                       class="zone-stat is-{{ $k }} {{ $zone === $k ? 'is-active' : '' }}" @if ($zone === $k) aria-current="true" @endif>
                        <span class="flex items-center justify-between gap-2">
                            <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-900 dark:text-white">
                                <span class="zone-dot is-{{ $k }}" aria-hidden="true"></span>{{ $z['label'] }}
                            </span>
                            <span class="text-xs text-slate-500 dark:text-slate-400 tabular-nums">{{ $z['range'] }} điểm</span>
                        </span>
                        <span class="mt-2 flex items-baseline gap-2">
                            <span class="zone-stat-value tabular-nums">{{ $zoneCounts[$k] }}</span>
                            <span class="text-xs text-slate-500 dark:text-slate-400">người · {{ $pct }}%</span>
                        </span>
                        <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">{{ $z['desc'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ─── Cảnh báo Redzone liên tiếp ────────────────────────────────────────── --}}
    @if (count($consecutiveRedzoneIds) > 0)
        <div class="record-status is-rejected" role="alert">
            <span class="record-status-icon" aria-hidden="true"><i class="bi bi-exclamation-octagon-fill"></i></span>
            <div class="min-w-0 text-sm text-slate-700 dark:text-slate-300">
                <p class="font-semibold text-slate-900 dark:text-white">{{ count($consecutiveRedzoneIds) }} nhân viên ở Redzone {{ $consecutiveMonths }} tháng liên tiếp</p>
                <p class="mt-0.5">Đây là ngưỡng cảnh báo xử lý đặc biệt theo cấu hình hệ thống. Ưu tiên trao đổi trực tiếp và xem lại lịch sử vi phạm.</p>
                @if ($zone !== 'red')
                    <a href="{{ route('redzone.index', $baseQuery(['zone' => 'red'])) }}" class="mt-1.5 inline-flex items-center gap-1 text-sm font-semibold text-[#C94758] hover:underline">
                        Xem danh sách Redzone <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </a>
                @endif
            </div>
        </div>
    @endif

    {{-- ─── Worklist vùng đang chọn ───────────────────────────────────────────── --}}
    <section class="card" aria-labelledby="zoneListTitle">
        <div class="card-header">
            <h2 id="zoneListTitle" class="flex items-center gap-2">
                <span class="zone-dot is-{{ $zone }}" aria-hidden="true"></span>
                {{ $zoneMeta[$zone]['label'] }} — {{ $zoneMeta[$zone]['desc'] }}
            </h2>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $zoneMeta[$zone]['note'] }}. Điểm {{ $zoneMeta[$zone]['range'] }} · tháng {{ $periodLabel }}.</p>
        </div>

        <x-table-toolbar :count="$zoneEmployees->count()" label="nhân viên">
            <x-slot:info>
                @foreach ($activeFilters as $key => $text)
                    <a href="{{ route('redzone.index', $baseQuery([], [$key])) }}" class="filter-chip" title="Bỏ bộ lọc này">
                        {{ $text }} <i class="bi bi-x" aria-hidden="true"></i><span class="sr-only">Bỏ bộ lọc</span>
                    </a>
                @endforeach
            </x-slot:info>
            <form method="GET" action="{{ route('redzone.index') }}" class="flex flex-wrap items-center gap-2" role="search">
                @foreach (request()->only(['zone', 'month', 'year']) as $k => $v)
                    @if ($v !== null && $v !== '') <input type="hidden" name="{{ $k }}" value="{{ $v }}"> @endif
                @endforeach
                <label for="rzBranch" class="sr-only">Chi nhánh</label>
                <select id="rzBranch" name="branch_id" class="form-input h-9 w-auto text-sm" onchange="this.form.team_id && (this.form.team_id.value = ''); this.form.submit()">
                    <option value="">Mọi chi nhánh</option>
                    @foreach ($branches as $b)
                        <option value="{{ $b->id }}" @selected(request('branch_id') == $b->id)>{{ $b->name }}</option>
                    @endforeach
                </select>
                <label for="rzTeam" class="sr-only">Đội</label>
                <select id="rzTeam" name="team_id" class="form-input h-9 w-auto text-sm" onchange="this.form.submit()">
                    <option value="">Mọi đội</option>
                    @foreach ($teams as $t)
                        <option value="{{ $t->id }}" @selected(request('team_id') == $t->id)>{{ $t->name }}</option>
                    @endforeach
                </select>
                <div class="relative">
                    <i class="bi bi-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400" aria-hidden="true"></i>
                    <input type="search" name="search" value="{{ request('search') }}" class="form-input h-9 w-48 pl-8 text-sm"
                           placeholder="Tên hoặc mã NV…" aria-label="Tìm nhân viên">
                </div>
            </form>
        </x-table-toolbar>

        <div class="card-body p-0">
            <div class="table-container border-0 rounded-none">
                <table class="table-base">
                    <caption class="sr-only">Nhân viên {{ $zoneMeta[$zone]['label'] }} tháng {{ $periodLabel }}, {{ $isWorkZone ? 'điểm thấp nhất trước' : 'điểm cao nhất trước' }}</caption>
                    <thead>
                        <tr>
                            <th class="table-th w-10 text-center" scope="col">#</th>
                            <th class="table-th min-w-[180px]" scope="col" data-mcard-title>Nhân viên</th>
                            <th class="table-th lg:max-xl:hidden" scope="col">Chi nhánh / Đội</th>
                            <th class="table-th min-w-[140px]" scope="col">Điểm tháng · xu hướng</th>
                            <th class="table-th" scope="col">Nguyên nhân trừ điểm</th>
                            @if ($zone === 'red')<th class="table-th" scope="col">Cảnh báo</th>@endif
                            <th class="table-th text-right" scope="col">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($zoneEmployees as $i => $emp)
                            @php
                                $isConsecutive = $zone === 'red' && in_array($emp->id, $consecutiveRedzoneIds);
                                $reasons = $reasonsByEmp[$emp->id] ?? collect();
                                $scorePct = $defaultScore > 0 ? max(0, min(100, round($emp->monthly_final_score / $defaultScore * 100))) : 0;
                            @endphp
                            <tr class="table-tr-hover {{ $isConsecutive ? 'is-flagged' : '' }}">
                                <td class="table-td text-center font-mono text-xs text-slate-400">{{ $i + 1 }}</td>
                                <td class="table-td">
                                    <div class="flex items-center gap-2.5">
                                        <x-employee-avatar :employee="$emp" size="w-8 h-8"
                                            :fallback="match ($zone) {
                                                'red' => 'bg-[#fde8ea] text-[#c94758]',
                                                'orange' => 'bg-[#fdeee2] text-[#b45309]',
                                                'yellow' => 'bg-[#fdf6dc] text-[#92700c]',
                                                default => 'bg-[#e8f5ef] text-[#168a63]',
                                            }" />
                                        <div class="min-w-0">
                                            <a href="{{ route('employees.show', $emp) }}" class="block truncate font-medium text-slate-900 hover:text-pcrm-600 dark:text-white dark:hover:text-pcrm-400">{{ $emp->name }}</a>
                                            <span class="font-mono text-xs text-slate-400">{{ $emp->code }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="table-td lg:max-xl:hidden">
                                    <div class="text-sm text-slate-700 dark:text-slate-300">{{ $emp->branch->name ?? '—' }}</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ $emp->team->name ?? 'Chưa gán đội' }}</div>
                                </td>
                                <td class="table-td">
                                    <div class="flex items-center gap-2.5">
                                        <span class="zone-score is-{{ $zone }} tabular-nums">{{ $emp->monthly_final_score }}</span>
                                        <span class="h-1.5 w-16 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700" role="progressbar"
                                              aria-valuemin="0" aria-valuemax="{{ $defaultScore }}" aria-valuenow="{{ $emp->monthly_final_score }}"
                                              aria-label="{{ $emp->monthly_final_score }}/{{ $defaultScore }} điểm">
                                            <span class="zone-bar-seg is-{{ $zone }} block h-full" style="width: {{ $scorePct }}%"></span>
                                        </span>
                                    </div>
                                    <span class="mt-1 block text-xs" title="Tháng trước: {{ $emp->prev_final_score }} điểm">
                                        @if ($emp->score_change < 0)
                                            <span class="font-semibold text-[#C94758]"><i class="bi bi-arrow-down" aria-hidden="true"></i> {{ abs($emp->score_change) }}</span>
                                        @elseif ($emp->score_change > 0)
                                            <span class="font-semibold text-[#168A63]"><i class="bi bi-arrow-up" aria-hidden="true"></i> {{ $emp->score_change }}</span>
                                        @else
                                            <span class="text-slate-400">Không đổi</span>
                                        @endif
                                        <span class="text-slate-400">so với tháng trước ({{ $emp->prev_final_score }})</span>
                                    </span>
                                </td>
                                <td class="table-td whitespace-normal min-w-[150px] text-sm">
                                    @forelse ($reasons->take(2) as $r)
                                        <span class="block truncate max-w-[12rem] 2xl:max-w-[16rem]" title="{{ $r['name'] }} — {{ $r['count'] }} lần, -{{ $r['points'] }} điểm">
                                            {{ $r['name'] }} <span class="text-xs text-slate-400">×{{ $r['count'] }} · -{{ $r['points'] }}</span>
                                        </span>
                                    @empty
                                        <span class="text-xs text-slate-400">Không bị trừ điểm</span>
                                    @endforelse
                                    @if ($reasons->count() > 2)
                                        <span class="text-xs text-slate-400">+{{ $reasons->count() - 2 }} lỗi khác</span>
                                    @endif
                                </td>
                                @if ($zone === 'red')
                                    <td class="table-td whitespace-nowrap">
                                        @if ($isConsecutive)
                                            <span class="badge-danger"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> {{ $consecutiveMonths }} tháng liên tiếp</span>
                                        @else
                                            <span class="text-xs text-slate-400">Tháng đầu</span>
                                        @endif
                                    </td>
                                @endif
                                <td class="table-td text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-0.5">
                                        <a href="{{ route('employees.show', $emp) }}" class="row-action" title="Xem hồ sơ" aria-label="Xem hồ sơ {{ $emp->name }}">
                                            <i class="bi bi-person-vcard"></i>
                                        </a>
                                        @can('view-employees')
                                            <a href="{{ route('employees.penalties', $emp) }}" class="row-action" title="Lịch sử vi phạm" aria-label="Lịch sử vi phạm của {{ $emp->name }}">
                                                <i class="bi bi-clock-history"></i>
                                            </a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $zone === 'red' ? 7 : 6 }}" class="table-td py-14 text-center text-slate-400 dark:text-slate-500">
                                    <i class="bi {{ $isWorkZone ? 'bi-shield-check text-[#168A63]' : 'bi-people' }} mb-3 block text-4xl" aria-hidden="true"></i>
                                    <p class="text-sm font-medium text-slate-600 dark:text-slate-300">Không có nhân viên nào trong {{ $zoneMeta[$zone]['label'] }} tháng {{ $periodLabel }}</p>
                                    @if ($activeFilters)
                                        <a href="{{ route('redzone.index', request()->only(['zone', 'month', 'year'])) }}" class="mt-3 inline-flex items-center gap-1.5 text-sm text-pcrm-600 dark:text-pcrm-400 hover:underline">
                                            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Xoá bộ lọc
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
function redzoneSyncYear(select) {
    document.getElementById('redzoneYear').value = select.options[select.selectedIndex].getAttribute('data-year');
}
</script>
@endpush
