@extends('layouts.admin')

@section('title', 'Đơn & Phê duyệt')
@unless ($isMobileView ?? false)
@section('page-title', 'Đơn & Phê duyệt')
@section('breadcrumb', 'Ca làm việc & Chấm công')
@section('page-subtitle', 'Đơn nghỉ phép, đổi ca, tăng ca và điều chỉnh chấm công chờ xử lý')

@section('page-actions')
    <button onclick="openModal('createStaffRequestModal')" class="btn-primary h-9 text-xs font-black gap-1.5">
        <i class="bi bi-plus-lg"></i>
        <span>Tạo yêu cầu</span>
    </button>
@endsection
@endunless

@section('content')
    @php
        $mobileUi = (bool) ($isMobileView ?? false);
        $typeDots = [
            'attendance_correction' => 'bg-sky-500',
            'business_trip'         => 'bg-amber-500',
            'late_early'            => 'bg-orange-500',
            'leave'                 => 'bg-pcrm-500',
            'time_change'           => 'bg-violet-500',
            'overtime'              => 'bg-rose-500',
            'shift_swap'            => 'bg-emerald-500',
        ];
        $typeLabels = [
            'attendance_correction' => 'Lượt chấm công',
            'business_trip'         => 'Công tác/Ra ngoài',
            'late_early'            => 'Đi muộn về sớm',
            'leave'                 => 'Nghỉ phép',
            'time_change'           => 'Thay đổi giờ vào/ra',
            'overtime'              => 'Tăng ca',
            'shift_swap'            => 'Đổi ca làm',
        ];
        $typeColors = [
            'attendance_correction' => 'bg-sky-100 dark:bg-sky-900/30 text-sky-700 dark:text-sky-400',
            'business_trip'         => 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400',
            'late_early'            => 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400',
            'leave'                 => 'bg-pcrm-100 dark:bg-pcrm-900/30 text-pcrm-700 dark:text-pcrm-400',
            'time_change'           => 'bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-400',
            'overtime'              => 'bg-rose-100 dark:bg-rose-900/30 text-rose-700 dark:text-rose-400',
            'shift_swap'            => 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400',
        ];
        $statusTabs = [
            'pending'   => 'Chờ duyệt',
            'approved'  => 'Đã duyệt',
            'rejected'  => 'Từ chối',
            'cancelled' => 'Đã huỷ',
        ];
        $currentStatus = request('status');
        $currentType = request('type');
        $withQuery = fn(array $set, array $drop = []) => array_filter(
            array_merge(request()->except(array_merge(['page'], $drop, array_keys($set))), $set),
            fn($v) => $v !== null && $v !== ''
        );
        $activeFilters = array_filter([
            'branch_id'   => request('branch_id') ? 'Chi nhánh: ' . ($branches->firstWhere('id', (int) request('branch_id'))?->name ?? '#' . request('branch_id')) : null,
            'team_id'     => request('team_id') ? 'Đội: ' . ($teams->firstWhere('id', (int) request('team_id'))?->name ?? '#' . request('team_id')) : null,
            'employee_id' => request('employee_id') && $isApprover ? 'NV: ' . ($allEmployees->firstWhere('id', (int) request('employee_id'))?->name ?? '#' . request('employee_id')) : null,
        ]);
    @endphp

    <div class="card {{ $mobileUi ? '!bg-transparent !border-0 !shadow-none !rounded-none' : '' }}">
        {{-- Mobile: header + tab trạng thái + bộ lọc (concept phương án 01) --}}
        @php
            $monthOptions = collect(range(0, 11))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
            $currentMonth = request('month');
            $mSelect = 'w-full h-10 rounded-xl bg-white dark:bg-slate-900 border border-blue-100 dark:border-slate-700 text-[13px] text-[#334155] dark:text-slate-300 pl-3 pr-7 truncate';
        @endphp
        @if ($mobileUi)
        <section class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-blue-100/70 dark:border-slate-800 shadow-[0_4px_20px_rgba(37,99,235,0.07)]" aria-labelledby="srMobileTitle">
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2.5 min-w-0">
                    <i class="bi bi-file-earmark-text text-blue-600 text-xl"></i>
                    <h1 id="srMobileTitle" class="text-[13px] font-extrabold uppercase tracking-wide text-[#0B1F5C] dark:text-slate-200 truncate">Đơn &amp; Phê duyệt</h1>
                </div>
                @canany(['create-staff-requests', 'create-leave-requests', 'create-shift-swaps'])
                    <button type="button" onclick="openModal('createStaffRequestModal')" class="shrink-0 inline-flex items-center gap-1.5 min-h-[44px] px-4 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold shadow-[0_4px_12px_rgba(37,99,235,0.3)] active:scale-95 transition">
                        <i class="bi bi-plus-lg"></i> Tạo yêu cầu
                    </button>
                @endcanany
            </div>

            <nav class="flex gap-2 overflow-x-auto mt-4 -mx-1 px-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" aria-label="Lọc theo trạng thái">
                @php $mTabs = ['' => 'Tất cả'] + $statusTabs; @endphp
                @foreach ($mTabs as $key => $label)
                    @continue($key === 'cancelled' && ($statusCounts[$key] ?? 0) === 0)
                    @php
                        $isActiveTab = ($key === '' && !$currentStatus) || ($key !== '' && $currentStatus === $key);
                        $cnt = $key === '' ? $statusCounts->sum() : ($statusCounts[$key] ?? 0);
                        $cntClass = $isActiveTab ? 'bg-white/25 text-white' : ($key === 'pending' ? 'bg-amber-400 text-white' : ($key === 'approved' ? 'bg-sky-400 text-white' : 'bg-slate-200 text-slate-600'));
                    @endphp
                    <a href="{{ route('staff-requests.index', $key === '' ? $withQuery([], ['status']) : $withQuery(['status' => $key])) }}"
                       @if ($isActiveTab) aria-current="page" @endif
                       class="shrink-0 inline-flex items-center gap-2 h-10 px-3.5 rounded-xl text-[13px] transition active:scale-95 {{ $isActiveTab ? 'bg-blue-600 text-white font-bold shadow-[0_4px_12px_rgba(37,99,235,0.3)]' : 'bg-[#F1F5FF] dark:bg-slate-800 text-[#334155] dark:text-slate-300 font-medium' }}">
                        {{ $label }}
                        <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full text-[11px] font-bold {{ $cntClass }}">{{ number_format($cnt) }}</span>
                    </a>
                @endforeach
            </nav>

            @if ($isApprover)
                <button type="button" onclick="toggleFilterDrawer(true)" class="mt-3 inline-flex items-center gap-1.5 h-10 px-3 rounded-xl bg-white dark:bg-slate-900 border border-blue-100 dark:border-slate-700 text-[13px] text-[#334155] dark:text-slate-300">
                    <i class="bi bi-funnel"></i> Bộ lọc
                    @if (request()->anyFilled(['branch_id', 'team_id', 'employee_id']))<span class="w-2 h-2 rounded-full bg-blue-600" aria-hidden="true"></span>@endif
                </button>
            @endif
        </section>
        @endif

        @unless ($mobileUi)
        <div>
        {{-- Tab trạng thái (số đếm theo loại đang chọn) --}}
        <nav class="status-tabs" aria-label="Lọc theo trạng thái">
            <a href="{{ route('staff-requests.index', $withQuery([], ['status'])) }}"
               class="status-tab {{ !$currentStatus ? 'is-active' : '' }}" @if (!$currentStatus) aria-current="page" @endif>
                Tất cả <span class="status-tab-count">{{ number_format($statusCounts->sum()) }}</span>
            </a>
            @foreach ($statusTabs as $key => $label)
                @continue($key === 'cancelled' && ($statusCounts[$key] ?? 0) === 0)
                <a href="{{ route('staff-requests.index', $withQuery(['status' => $key])) }}"
                   class="status-tab {{ $currentStatus === $key ? 'is-active' : '' }} {{ $key === 'pending' && $isApprover && ($statusCounts[$key] ?? 0) > 0 ? 'has-attention' : '' }}"
                   @if ($currentStatus === $key) aria-current="page" @endif>
                    {{ $label }} <span class="status-tab-count">{{ number_format($statusCounts[$key] ?? 0) }}</span>
                </a>
            @endforeach
        </nav>

        {{-- Chip loại yêu cầu (số đếm theo trạng thái đang chọn) --}}
        <div class="type-chips" role="group" aria-label="Lọc theo loại yêu cầu">
            <a href="{{ route('staff-requests.index', $withQuery([], ['type'])) }}"
               class="type-chip {{ !$currentType ? 'is-active' : '' }}" @if (!$currentType) aria-current="true" @endif>
                Mọi loại <span class="type-chip-count">{{ number_format(array_sum($typeCounts->toArray())) }}</span>
            </a>
            @foreach ($typeLabels as $key => $label)
                <a href="{{ route('staff-requests.index', $withQuery(['type' => $key])) }}"
                   class="type-chip {{ $currentType === $key ? 'is-active' : '' }}" @if ($currentType === $key) aria-current="true" @endif>
                    <span class="w-2 h-2 rounded-full {{ $typeDots[$key] }}" aria-hidden="true"></span>
                    {{ $label }} <span class="type-chip-count">{{ number_format($typeCounts[$key] ?? 0) }}</span>
                </a>
            @endforeach
        </div>

        </div>
        @endunless

        @unless ($mobileUi)<div><x-table-toolbar :paginator="$requests" label="yêu cầu">
            <x-slot:info>
                @foreach ($activeFilters as $key => $text)
                    <a href="{{ route('staff-requests.index', $withQuery([], [$key])) }}" class="filter-chip" title="Bỏ bộ lọc này">
                        {{ $text }} <i class="bi bi-x" aria-hidden="true"></i><span class="sr-only">Bỏ bộ lọc</span>
                    </a>
                @endforeach
            </x-slot:info>
            @if ($isApprover)
                <button type="button" onclick="toggleFilterDrawer(true)" class="btn-secondary relative">
                    <i class="bi bi-funnel"></i>
                    <span>Bộ lọc</span>
                    @if (request()->anyFilled(['branch_id', 'team_id', 'employee_id']))
                        <span class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-pcrm-600 rounded-full" aria-hidden="true"></span>
                    @endif
                </button>
            @endif
        </x-table-toolbar></div>@endunless

        <div class="card-body p-0 {{ $mobileUi ? '!px-0' : '' }}">
            {{-- Desktop: bảng --}}
            <div class="hidden lg:block table-container border-0 rounded-none">
                <table class="table-base">
                    <caption class="sr-only">Danh sách yêu cầu của nhân viên</caption>
                    <thead>
                        <tr>
                            <th class="table-th lg:max-xl:hidden" scope="col">Mã</th>
                            @if ($isApprover)<th class="table-th min-w-[150px] 2xl:min-w-[170px]" scope="col">Nhân viên</th>@endif
                            <th class="table-th" scope="col">Loại</th>
                            <th class="table-th" scope="col">Ngày / thời gian</th>
                            <th class="table-th lg:max-xl:hidden" scope="col">Nội dung</th>
                            <th class="table-th" scope="col">Trạng thái</th>
                            <th class="table-th text-right" scope="col">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($requests as $r)
                            <tr class="table-tr-hover cursor-pointer" onclick="openRequestDetailModal('{{ $r['source'] }}', {{ $r['id'] }})">
                                <td class="table-td whitespace-nowrap font-mono text-xs text-slate-500 dark:text-slate-400 lg:max-xl:hidden">{{ $r['code'] }}</td>
                                @if ($isApprover)
                                    <td class="table-td">
                                        <div class="flex items-center gap-2.5">
                                            <x-employee-avatar :employee="$r['employee']" size="w-8 h-8" />
                                            <div class="min-w-0">
                                                <p class="font-medium text-slate-900 dark:text-white">{{ $r['employee']?->name ?? '—' }}</p>
                                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $r['employee']?->team?->name ?? ($r['employee']?->branch?->name ?? '—') }}</p>
                                            </div>
                                        </div>
                                    </td>
                                @endif
                                <td class="table-td whitespace-nowrap">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $typeColors[$r['type_key']] ?? 'badge-neutral' }}">{{ $r['type_label'] }}</span>
                                </td>
                                <td class="table-td text-sm text-slate-700 dark:text-slate-300">{{ $r['work_date_label'] }}</td>
                                <td class="table-td text-sm lg:max-xl:hidden">
                                    <div class="max-w-xs lg:max-2xl:max-w-[10rem] truncate text-slate-600 dark:text-slate-300" title="{{ $r['summary'] }}">{{ $r['summary'] }}</div>
                                    @if ($r['status'] === 'rejected' && $r['rejection_reason'])
                                        <div class="max-w-xs lg:max-2xl:max-w-[10rem] truncate text-xs text-[#C94758]" title="{{ $r['rejection_reason'] }}">Lý do: {{ $r['rejection_reason'] }}</div>
                                    @endif
                                </td>
                                <td class="table-td whitespace-normal min-w-[7rem]">
                                    <span class="badge {{ $r['status_badge'] }}">{{ $r['status_label'] }}</span>
                                    @if (!empty($r['correction_outcome_label']))
                                        <span class="mt-1 block text-[11px] font-medium {{ $r['correction_outcome_label'] === 'Đã tha lỗi' ? 'text-[#168A63]' : 'text-slate-500' }}">{{ $r['correction_outcome_label'] }}</span>
                                    @endif
                                    @if ($r['status'] !== 'pending' && $r['reviewer']?->name)
                                        <span class="mt-1 block max-w-[8rem] truncate text-[11px] text-slate-400" title="Xử lý bởi {{ $r['reviewer']->name }}">bởi {{ $r['reviewer']->name }}</span>
                                    @endif
                                </td>
                                <td class="table-td text-right whitespace-nowrap">
                                    <div class="inline-flex items-center justify-end gap-0.5" onclick="event.stopPropagation()">
                                        @if ($r['status'] === 'pending')
                                            @can($r['approve_permission'])
                                                @if ($r['type_key'] === 'late_early')
                                                    <button type="button" class="row-action row-action-success" title="Duyệt" aria-label="Duyệt yêu cầu {{ $r['code'] }}"
                                                            onclick="openApproveLateEarlyModal({{ Illuminate\Support\Js::from($r['approve_route']) }}, {{ Illuminate\Support\Js::from($r['code']) }})">
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
                                                @else
                                                    <button type="button" class="row-action row-action-success" title="Duyệt" aria-label="Duyệt yêu cầu {{ $r['code'] }}"
                                                            onclick="openApproveStaffRequestModal({{ Illuminate\Support\Js::from($r['approve_route']) }}, {{ Illuminate\Support\Js::from($r['code']) }}, {{ Illuminate\Support\Js::from($r['type_label']) }}, {{ Illuminate\Support\Js::from($r['employee']?->name ?? '') }})">
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
                                                @endif
                                                <button type="button" class="row-action row-action-danger" title="Từ chối" aria-label="Từ chối yêu cầu {{ $r['code'] }}"
                                                        onclick="openRejectStaffRequestModal({{ Illuminate\Support\Js::from($r['reject_route']) }}, {{ Illuminate\Support\Js::from($r['code']) }})">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            @endcan
                                            @if ($r['can_manage_own'])
                                                <button type="button" class="row-action row-action-danger" title="Huỷ yêu cầu" aria-label="Huỷ yêu cầu {{ $r['code'] }}"
                                                        onclick="openCancelStaffRequestModal({{ Illuminate\Support\Js::from($r['destroy_route']) }}, {{ Illuminate\Support\Js::from($r['code']) }})">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            @endif
                                        @elseif (!empty($r['destroy_route']))
                                            <button type="button" class="row-action row-action-danger" title="Xoá yêu cầu" aria-label="Xoá yêu cầu {{ $r['code'] }}"
                                                    data-destroy="{{ $r['destroy_route'] }}"
                                                    data-code="{{ $r['code'] }}"
                                                    data-status="{{ $r['status'] }}"
                                                    data-note="{{ $r['reversal_note'] ?? '' }}"
                                                    onclick="openDeleteHubModal(this)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        @endif
                                        <button type="button" class="row-action" title="Xem chi tiết" aria-label="Xem chi tiết yêu cầu {{ $r['code'] }}"
                                                onclick="openRequestDetailModal('{{ $r['source'] }}', {{ $r['id'] }})">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isApprover ? 7 : 6 }}" class="table-td py-16 text-center text-slate-400 dark:text-slate-500">
                                    <i class="bi bi-inbox mb-3 block text-4xl" aria-hidden="true"></i>
                                    <p class="text-sm font-medium text-slate-600 dark:text-slate-300">{{ ($currentStatus || $currentType || $activeFilters) ? 'Không có yêu cầu nào khớp bộ lọc' : 'Chưa có yêu cầu nào' }}</p>
                                    @if ($currentStatus || $currentType || $activeFilters)
                                        <a href="{{ route('staff-requests.index') }}" class="mt-3 inline-flex items-center gap-1.5 text-sm text-pcrm-600 dark:text-pcrm-400 hover:underline">
                                            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Xoá bộ lọc
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Mobile/tablet: thẻ yêu cầu, bấm để xem chi tiết + thao tác --}}
            @php
                $typeIcons = [
                    'attendance_correction' => 'bi-fingerprint',
                    'business_trip' => 'bi-briefcase',
                    'late_early' => 'bi-clock-history',
                    'leave' => 'bi-person-walking',
                    'time_change' => 'bi-arrow-left-right',
                    'overtime' => 'bi-moon-stars',
                    'shift_swap' => 'bi-arrow-left-right',
                ];
                $statusPill = [
                    'pending' => ['bg-[#FFF4E5] text-[#D97706]', 'bi-clock'],
                    'approved' => ['bg-emerald-50 text-emerald-700', 'bi-check-circle'],
                    'rejected' => ['bg-red-50 text-red-700', 'bi-x-circle'],
                    'cancelled' => ['bg-slate-100 text-slate-600', 'bi-slash-circle'],
                ];
            @endphp
            @if ($mobileUi)
            <div class="space-y-3 mt-4">
                @forelse ($requests as $r)
                    @php [$pillClass, $pillIcon] = $statusPill[$r['status']] ?? $statusPill['cancelled']; @endphp
                    <article onclick="openRequestDetailModal('{{ $r['source'] }}', {{ $r['id'] }})"
                             class="cursor-pointer bg-white dark:bg-slate-900 rounded-2xl p-4 border border-blue-100/70 dark:border-slate-800 shadow-[0_4px_20px_rgba(37,99,235,0.07)] active:scale-[0.99] transition">
                        <div class="flex items-start gap-3">
                            <i class="bi {{ $typeIcons[$r['type_key']] ?? 'bi-file-earmark-text' }} text-[26px] text-blue-600 w-9 text-center shrink-0 leading-none mt-1"></i>
                            <div class="min-w-0 flex-1">
                                <h2 class="text-lg font-bold text-[#0B1F5C] dark:text-white truncate">{{ $r['type_label'] }}</h2>
                                <p class="text-xs text-[#475569] dark:text-slate-400 break-all">Mã đơn: #{{ $r['code'] }}</p>
                            </div>
                            <span class="shrink-0 inline-flex items-center gap-1 px-2 py-1 rounded-full text-[11px] font-bold {{ $pillClass }}"><i class="bi {{ $pillIcon }}"></i> {{ $r['status_label'] }}</span>
                        </div>
                        <dl class="mt-3 pt-1 border-t border-slate-100 dark:border-slate-800 text-[13px]">
                            @if ($isApprover)
                                <div class="grid grid-cols-[1.25rem_5.5rem_minmax(0,1fr)] items-start gap-x-2 py-1.5">
                                    <i class="bi bi-person text-[#64748B] text-center"></i>
                                    <dt class="text-[#475569] dark:text-slate-400">Nhân viên</dt>
                                    <dd class="min-w-0 text-[#1E293B] dark:text-slate-200 truncate">{{ $r['employee']?->name ?? '—' }}</dd>
                                </div>
                            @endif
                            @foreach (collect($r['details'] ?? [])->take(2) as $row)
                                <div class="grid grid-cols-[1.25rem_5.5rem_minmax(0,1fr)] items-start gap-x-2 py-1.5">
                                    <i class="bi {{ $row['icon'] }} text-[#64748B] text-center"></i>
                                    <dt class="text-[#475569] dark:text-slate-400">{{ $row['label'] }}</dt>
                                    <dd class="min-w-0 text-[#1E293B] dark:text-slate-200">{{ $row['value'] }}</dd>
                                </div>
                            @endforeach
                            @if ($r['status'] === 'rejected' && $r['rejection_reason'])
                                <div class="grid grid-cols-[1.25rem_5.5rem_minmax(0,1fr)] items-start gap-x-2 py-1.5">
                                    <i class="bi bi-exclamation-circle text-red-500 text-center"></i>
                                    <dt class="text-[#475569]">Lý do từ chối</dt>
                                    <dd class="min-w-0 text-red-600 line-clamp-2">{{ $r['rejection_reason'] }}</dd>
                                </div>
                            @endif
                        </dl>
                    </article>
                @empty
                    <div class="bg-white dark:bg-slate-900 rounded-2xl p-8 border border-blue-100 dark:border-slate-800 text-center text-sm text-slate-500 dark:text-slate-400">
                        <i class="bi bi-inbox mb-2 block text-3xl text-blue-300" aria-hidden="true"></i>
                        {{ ($currentStatus || $currentType || $activeFilters || request('month')) ? 'Không có yêu cầu nào khớp bộ lọc' : 'Chưa có yêu cầu nào' }}
                    </div>
                @endforelse
            </div>
            @else
            {{-- Tablet (giao diện web responsive): danh sách mục, bấm để xem chi tiết + thao tác --}}
            <ul class="divide-y divide-slate-100 dark:divide-slate-700/60 lg:hidden">
                @forelse ($requests as $r)
                    <li>
                        <button type="button" onclick="openRequestDetailModal('{{ $r['source'] }}', {{ $r['id'] }})"
                                class="flex w-full items-start gap-3 px-4 py-3.5 text-left hover:bg-slate-50 dark:hover:bg-slate-800/60"
                                aria-label="Xem yêu cầu {{ $r['code'] }}">
                            <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full {{ $typeDots[$r['type_key']] ?? 'bg-slate-400' }}" aria-hidden="true"></span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center justify-between gap-2">
                                    <span class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $isApprover ? ($r['employee']?->name ?? '—') : $r['type_label'] }}</span>
                                    <span class="badge {{ $r['status_badge'] }} shrink-0">{{ $r['status_label'] }}</span>
                                </span>
                                <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">
                                    @if ($isApprover){{ $r['type_label'] }} · @endif{{ $r['work_date_label'] }}
                                </span>
                                <span class="mt-0.5 block truncate text-xs text-slate-400">{{ $r['summary'] }}</span>
                            </span>
                            <i class="bi bi-chevron-right mt-1 text-slate-400" aria-hidden="true"></i>
                        </button>
                    </li>
                @empty
                    <li class="px-4 py-12 text-center text-sm text-slate-500 dark:text-slate-400">
                        <i class="bi bi-inbox mb-2 block text-3xl text-slate-300" aria-hidden="true"></i>
                        {{ ($currentStatus || $currentType || $activeFilters) ? 'Không có yêu cầu nào khớp bộ lọc' : 'Chưa có yêu cầu nào' }}
                    </li>
                @endforelse
            </ul>
            @endif
        </div>
        @if ($requests->hasPages())
            <div class="card-footer">
                {{ $requests->links() }}
            </div>
        @endif
    </div>
@endsection

@push('modals')
<!-- Request Detail Modal -->
<div id="requestDetailModal" class="hidden fixed inset-0 bg-black/50 z-[120] flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('requestDetailModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-hidden flex flex-col">
        <!-- Modal Header -->
        <div class="flex items-center justify-between px-4 py-3 sm:px-6 sm:py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-file-earmark-text text-pcrm-600"></i> Chi tiết yêu cầu <span id="dtRequestCode" class="font-mono text-slate-550 text-sm"></span>
            </h3>
            <button onclick="closeModal('requestDetailModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-4 sm:p-6 space-y-4 overflow-y-auto flex-1">
            <!-- Employee Info -->
            <div id="dtEmployeeSection" class="flex items-center gap-3 p-3 bg-slate-50/50 dark:bg-slate-900/20 rounded-xl border border-slate-100 dark:border-slate-800/60 hidden">
                <div class="w-9 h-9 rounded-full bg-pcrm-100 dark:bg-pcrm-900/50 flex items-center justify-center text-pcrm-700 dark:text-pcrm-400 font-bold text-sm shrink-0 overflow-hidden">
                    <img id="dtEmployeeAvatarImg" src="" alt="" class="hidden w-full h-full object-cover">
                    <span id="dtEmployeeAvatar"></span>
                </div>
                <div class="min-w-0">
                    <h4 id="dtEmployeeName" class="font-bold text-slate-900 dark:text-white text-sm truncate"></h4>
                    <p id="dtEmployeeBranch" class="text-[10px] text-slate-400 dark:text-slate-550 truncate"></p>
                </div>
            </div>

            <!-- Fields Grid -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-[10px] uppercase font-bold text-slate-400 dark:text-slate-500 tracking-wide">Loại yêu cầu</label>
                    <div class="mt-1">
                        <span id="dtRequestTypeBadge" class="text-xs font-semibold px-2.5 py-1 rounded-full"></span>
                    </div>
                </div>
                <div>
                    <label class="text-[10px] uppercase font-bold text-slate-400 dark:text-slate-500 tracking-wide">Trạng thái</label>
                    <div class="mt-1">
                        <span id="dtRequestStatusBadge" class="badge"></span>
                    </div>
                </div>
            </div>

            <div>
                <label class="text-[10px] uppercase font-bold text-slate-400 dark:text-slate-500 tracking-wide">Thời gian áp dụng</label>
                <p id="dtRequestTime" class="text-sm font-semibold text-slate-800 dark:text-slate-200 mt-1"></p>
            </div>

            <div id="dtLeaveShiftsSection" class="hidden">
                <label class="text-[10px] uppercase font-bold text-slate-400 dark:text-slate-500 tracking-wide">Ca xin nghỉ</label>
                <ul id="dtLeaveShiftsList" class="mt-1 space-y-1.5"></ul>
            </div>

            <div class="p-3 bg-slate-50/50 dark:bg-slate-900/20 rounded-xl border border-slate-100 dark:border-slate-800/60 space-y-1">
                <label class="text-[10px] uppercase font-bold text-slate-400 dark:text-slate-500 tracking-wide">Nội dung / Lý do</label>
                <p id="dtRequestSummary" class="text-xs text-slate-700 dark:text-slate-350 leading-relaxed break-words whitespace-pre-wrap"></p>
            </div>

            <!-- Outcomes & Reviewer Info -->
            <div id="dtOutcomeSection" class="p-3 bg-emerald-50/50 dark:bg-emerald-950/15 border border-emerald-150/40 rounded-xl space-y-1 hidden">
                <label class="text-[10px] uppercase font-bold text-emerald-600 dark:text-emerald-400 tracking-wide">Kết quả phê duyệt</label>
                <div class="flex items-center gap-2 mt-1">
                    <span id="dtOutcomeBadge" class="badge text-xs"></span>
                </div>
            </div>

            <div id="dtReviewerSection" class="text-xs text-slate-500 dark:text-slate-400 hidden">
                Người duyệt: <strong id="dtReviewerName"></strong>
            </div>

            <div id="dtRejectionSection" class="p-3 bg-red-50/50 dark:bg-red-950/15 border border-red-150/40 rounded-xl space-y-1 hidden">
                <label class="text-[10px] uppercase font-bold text-red-600 dark:text-red-400 tracking-wide">Lý do từ chối</label>
                <p id="dtRejectionReason" class="text-xs text-red-700 dark:text-red-400 break-words whitespace-pre-wrap"></p>
            </div>
        </div>

        <!-- Modal Footer Actions -->
        <div id="dtModalActions" class="px-4 py-3 sm:px-6 bg-slate-50 dark:bg-slate-900/40 border-t border-slate-200 dark:border-slate-750 flex items-center justify-end gap-2 shrink-0">
            <!-- Appended dynamically via JS -->
        </div>
    </div>
</div>

<!-- Helper forms for modal actions -->
<form id="modalApproveForm" method="POST" class="hidden">
    @csrf
</form>
<form id="modalDeleteForm" method="POST" class="hidden">
    @csrf
    @method('DELETE')
</form>

{{-- Xác nhận duyệt / huỷ yêu cầu — thay cho confirm() của trình duyệt --}}
<div id="approveStaffRequestModal" class="hidden fixed inset-0 bg-black/50 z-[130] flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('approveStaffRequestModal')">
    <div class="pcrm-dialog max-w-md" role="dialog" aria-modal="true" aria-labelledby="approveSrTitle">
        <div class="pcrm-dialog-head">
            <span class="pcrm-dialog-icon bg-[#e8f5ef] text-[#168A63] dark:bg-[#168A63]/20 dark:text-[#34d399]" aria-hidden="true"><i class="bi bi-check2-circle"></i></span>
            <div class="min-w-0 flex-1">
                <h3 id="approveSrTitle" class="pcrm-dialog-title">Duyệt yêu cầu <span id="approveSrCode" class="font-mono"></span></h3>
                <p id="approveSrSub" class="pcrm-dialog-sub"></p>
            </div>
            <button type="button" onclick="closeModal('approveStaffRequestModal')" class="pcrm-dialog-close" aria-label="Đóng"><i class="bi bi-x-lg"></i></button>
        </div>
        <form id="approveSrForm" method="POST" class="pcrm-dialog-form">
            @csrf
            <div class="pcrm-dialog-foot justify-end">
                <div class="flex items-center gap-2">
                    <button type="button" onclick="closeModal('approveStaffRequestModal')" class="btn-secondary">Hủy</button>
                    <button type="submit" class="btn-primary"><i class="bi bi-check2"></i> Xác nhận duyệt</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div id="cancelStaffRequestModal" class="hidden fixed inset-0 bg-black/50 z-[130] flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('cancelStaffRequestModal')">
    <div class="pcrm-dialog max-w-md" role="dialog" aria-modal="true" aria-labelledby="cancelSrTitle">
        <div class="pcrm-dialog-head">
            <span class="pcrm-dialog-icon bg-[#fde8ea] text-[#C94758] dark:bg-[#C94758]/20 dark:text-[#fb7185]" aria-hidden="true"><i class="bi bi-trash"></i></span>
            <div class="min-w-0 flex-1">
                <h3 id="cancelSrTitle" class="pcrm-dialog-title">Huỷ yêu cầu <span id="cancelSrCode" class="font-mono"></span></h3>
                <p class="pcrm-dialog-sub">Yêu cầu chưa xử lý sẽ bị gỡ khỏi hàng đợi duyệt</p>
            </div>
            <button type="button" onclick="closeModal('cancelStaffRequestModal')" class="pcrm-dialog-close" aria-label="Đóng"><i class="bi bi-x-lg"></i></button>
        </div>
        <form id="cancelSrForm" method="POST" class="pcrm-dialog-form">
            @csrf
            @method('DELETE')
            <div class="pcrm-dialog-foot justify-end">
                <div class="flex items-center gap-2">
                    <button type="button" onclick="closeModal('cancelStaffRequestModal')" class="btn-secondary">Giữ lại</button>
                    <button type="submit" class="btn-danger"><i class="bi bi-trash"></i> Huỷ yêu cầu</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Overlay for filter drawer -->
<div id="filterDrawerOverlay" class="fixed inset-0 bg-black/40 z-40 hidden opacity-0 transition-opacity duration-300 pointer-events-none" onclick="toggleFilterDrawer(false)"></div>

<!-- Right-Side Filter Drawer -->
<aside id="filterDrawer" class="fixed inset-y-0 right-0 z-50 w-full max-w-xs sm:max-w-sm bg-white dark:bg-slate-900 border-l border-slate-200 dark:border-slate-800 shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col">
    <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between flex-shrink-0">
        <h3 class="text-sm font-black text-slate-855 dark:text-slate-100 uppercase tracking-wide flex items-center gap-1.5">
            <i class="bi bi-funnel text-pcrm-600"></i> Bộ lọc tìm kiếm
        </h3>
        <button type="button" onclick="toggleFilterDrawer(false)" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-750 transition-colors">
            <i class="bi bi-x-lg text-sm"></i>
        </button>
    </div>
    <form action="{{ route('staff-requests.index') }}" method="GET" class="flex-1 flex flex-col overflow-y-auto">
        <div class="p-5 space-y-4 flex-1">
            @if($isApprover)
            <div>
                <label class="block text-xs font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wider mb-2">Chi nhánh</label>
                <select name="branch_id" class="form-input text-sm w-full">
                    <option value="">Tất cả</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}" @selected(request('branch_id') == $b->id)>{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wider mb-2">Đội nhóm</label>
                <select name="team_id" class="form-input text-sm w-full">
                    <option value="">Tất cả</option>
                    @foreach($teams as $t)
                        <option value="{{ $t->id }}" @selected(request('team_id') == $t->id)>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-employee-combobox name="employee_id" :employees="$employees" :selected="request('employee_id')"
                    label="Nhân viên" placeholder="Tìm theo tên, mã NV..." :compact="true" />
            </div>
            @endif
            <div>
                <label class="block text-xs font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wider mb-2">Loại yêu cầu</label>
                <select name="type" class="form-input text-sm w-full">
                    <option value="">Tất cả</option>
                    <option value="attendance_correction" @selected(request('type') === 'attendance_correction')>Lượt chấm công</option>
                    <option value="business_trip" @selected(request('type') === 'business_trip')>Công tác/Ra ngoài</option>
                    <option value="late_early" @selected(request('type') === 'late_early')>Đi muộn về sớm</option>
                    <option value="leave" @selected(request('type') === 'leave')>Nghỉ phép</option>
                    <option value="time_change" @selected(request('type') === 'time_change')>Thay đổi giờ vào/ra</option>
                    <option value="overtime" @selected(request('type') === 'overtime')>Tăng ca</option>
                    <option value="shift_swap" @selected(request('type') === 'shift_swap')>Đổi ca làm</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wider mb-2">Trạng thái</label>
                <select name="status" class="form-input text-sm w-full">
                    <option value="">Tất cả</option>
                    <option value="pending" @selected(request('status') === 'pending')>Chờ duyệt</option>
                    <option value="approved" @selected(request('status') === 'approved')>Đã duyệt</option>
                    <option value="rejected" @selected(request('status') === 'rejected')>Từ chối</option>
                </select>
            </div>
        </div>
        <div class="p-5 border-t border-slate-100 dark:border-slate-800/80 flex items-center gap-3 bg-slate-50/50 dark:bg-slate-950/20 flex-shrink-0">
            <button type="button" onclick="resetFilters()" class="btn-secondary flex-1 py-2.5 px-3 text-xs font-bold">Đặt lại</button>
            <button type="submit" class="btn-primary flex-1 py-2.5 px-3 text-xs font-bold">Áp dụng</button>
        </div>
    </form>
</aside>

<div id="createStaffRequestModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('createStaffRequestModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-plus-circle text-pcrm-600"></i> Tạo yêu cầu
            </h3>
            <button onclick="closeModal('createStaffRequestModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>

        <div class="px-4 sm:px-6 py-4 sm:py-5 space-y-4">
            <div>
                <label class="form-label">Loại yêu cầu <span class="text-red-500">*</span></label>
                <select id="srTypeSelect" class="form-input" onchange="srSwitchType(this.value)">
                    <option value="attendance_correction">Lượt chấm công</option>
                    <option value="business_trip">Công tác/Ra ngoài</option>
                    <option value="late_early">Đi muộn về sớm</option>
                    <option value="leave">Nghỉ phép</option>
                    <option value="time_change">Thay đổi giờ vào/ra</option>
                    <option value="overtime">Tăng ca</option>
                    <option value="shift_swap">Đổi ca làm</option>
                </select>
            </div>

            {{-- ── 4 loại dùng chung form → staff-requests.store ── --}}
            <form id="srStaffForm" action="{{ route('staff-requests.store') }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="_modal" value="srStaffForm">
                <input type="hidden" name="type" id="srStaffType" value="{{ old('type', 'attendance_correction') }}">

                @if($isApprover)
                <div>
                    <x-employee-combobox name="employee_id" :employees="$employees"
                        label="Nhân viên" placeholder="Chọn nhân viên cần tạo yêu cầu..." />
                    @error('employee_id') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                @endif

                <div>
                    <label class="form-label">Ngày <span class="text-red-500">*</span></label>
                    <input type="date" name="work_date" class="form-input" required>
                </div>

                {{-- attendance_correction --}}
                <div class="sr-field-group" data-type="attendance_correction">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Giờ vào (nếu cần sửa)</label>
                            <input type="time" name="check_in_at" class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Giờ ra (nếu cần sửa)</label>
                            <input type="time" name="check_out_at" class="form-input">
                        </div>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Nhập ít nhất 1 trong 2 ô — hệ thống sẽ bổ sung/sửa lại lượt chấm công của ngày đã chọn.</p>
                </div>

                {{-- business_trip --}}
                <div class="sr-field-group hidden" data-type="business_trip">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Từ giờ <span class="text-red-500">*</span></label>
                            <input type="time" name="from_time" class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Đến giờ <span class="text-red-500">*</span></label>
                            <input type="time" name="to_time" class="form-input">
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="form-label">Địa điểm <span class="text-red-500">*</span></label>
                        <input type="text" name="location" class="form-input" placeholder="VD: Gặp khách tại quận 1">
                    </div>
                </div>

                {{-- late_early --}}
                <div class="sr-field-group hidden" data-type="late_early">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Loại <span class="text-red-500">*</span></label>
                            <select name="mode" class="form-input">
                                <option value="late">Đến muộn</option>
                                <option value="early">Về sớm</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Số phút <span class="text-red-500">*</span></label>
                            <input type="number" name="minutes" class="form-input" min="1" max="480">
                        </div>
                    </div>
                </div>

                {{-- time_change --}}
                <div class="sr-field-group hidden" data-type="time_change">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Giờ vào mới <span class="text-red-500">*</span></label>
                            <input type="time" name="new_check_in" class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Giờ ra mới <span class="text-red-500">*</span></label>
                            <input type="time" name="new_check_out" class="form-input">
                        </div>
                    </div>
                </div>

                {{-- overtime --}}
                <div class="sr-field-group hidden" data-type="overtime">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Từ giờ <span class="text-red-500">*</span></label>
                            <input type="time" name="ot_from_time" class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Đến giờ <span class="text-red-500">*</span></label>
                            <input type="time" name="ot_to_time" class="form-input">
                        </div>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Sau khi được duyệt, số giờ tăng ca sẽ được cộng trực tiếp vào công của ngày đã chọn.
                        Nếu "Đến giờ" sớm hơn hoặc bằng "Từ giờ", hệ thống tự hiểu là tăng ca qua đêm sang ngày hôm sau (VD 23:00–03:00 = 4 giờ).</p>
                </div>

                <div>
                    <label class="form-label">Lý do <span class="text-red-500">*</span></label>
                    <textarea name="reason" rows="3" class="form-input" placeholder="Lý do..." required></textarea>
                    @error('reason') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                    <button type="button" onclick="closeModal('createStaffRequestModal')" class="btn-secondary">Hủy</button>
                    <button type="submit" class="btn-primary"><i class="bi bi-send"></i> Gửi yêu cầu</button>
                </div>
            </form>

            {{-- ── Nghỉ phép — dùng đúng route/field của LeaveRequestsController ── --}}
            <form id="srLeaveForm" action="{{ route('leave-requests.store') }}" method="POST" class="space-y-4 hidden"
                  data-own-employee-id="{{ auth()->user()->employee?->id }}" data-shifts-url="{{ route('leave-requests.shifts-for-range') }}">
                @csrf
                <input type="hidden" name="_modal" value="srLeaveForm">
                @if($isApprover)
                <div>
                    <x-employee-combobox name="employee_id" :employees="$employees" :selected="old('employee_id')"
                        label="Nhân viên" placeholder="Chọn nhân viên cần tạo đơn..." />
                </div>
                @endif
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Ngày bắt đầu <span class="text-red-500">*</span></label>
                        <input type="date" name="date_from" id="srLeaveDateFrom" class="form-input" value="{{ old('date_from') }}" required>
                    </div>
                    <div>
                        <label class="form-label">Đến ngày <span class="text-red-500">*</span></label>
                        <input type="date" name="date_to" id="srLeaveDateTo" class="form-input" value="{{ old('date_to') }}" required>
                    </div>
                </div>
                <div>
                    <label class="form-label">Loại nghỉ phép <span class="text-red-500">*</span></label>
                    <select name="type" class="form-input" id="srLeaveType" required>
                        <option value="annual" @selected(old('type', 'annual') === 'annual')>Nghỉ phép năm</option>
                        <option value="unpaid" @selected(old('type') === 'unpaid')>Nghỉ không lương</option>
                    </select>
                    <div id="srLeaveBalanceNote" class="mt-1.5 hidden">
                        <span id="srLeaveBalanceBadge" class="badge"></span>
                    </div>
                    @error('type') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <input type="hidden" id="srLeavePartialToggle" name="is_partial_day" value="{{ old('is_partial_day', 0) }}">

                <div id="srLeavePartialWrap" class="hidden space-y-3">
                    <div id="srLeavePartialModeGroup" class="flex items-center gap-4 text-sm">
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="radio" name="partial_mode" value="shifts" id="srLeaveModeShifts"
                                   class="border-slate-300 text-pcrm-600 focus:ring-pcrm-500"
                                   @checked(old('partial_mode', 'shifts') === 'shifts')>
                            <span id="srLeaveModeShiftsLabel">Nghỉ theo ca cụ thể</span>
                        </label>
                        <label id="srLeaveModeCustomLabel" class="hidden flex items-center gap-1.5 cursor-pointer">
                            <input type="radio" name="partial_mode" value="custom_time" id="srLeaveModeCustom"
                                   class="border-slate-300 text-pcrm-600 focus:ring-pcrm-500"
                                   @checked(old('partial_mode') === 'custom_time')>
                            <span>Nghỉ nửa ngày (theo giờ)</span>
                        </label>
                    </div>

                    <div id="srLeaveShiftPickerWrap">
                        <label class="form-label">Chọn ca cần nghỉ <span class="text-red-500">*</span></label>
                        <select id="srLeaveShiftSelect" name="shift_schedule_ids[]" multiple></select>
                        <p id="srLeaveNoShiftHint" class="hidden mt-1.5 text-xs text-amber-600 dark:text-amber-400">
                            <i class="bi bi-exclamation-triangle-fill mr-1"></i>Không tìm thấy ca đã xếp cho nhân viên này trong khoảng ngày đã chọn — vui lòng liên hệ quản lý xếp ca trước, hoặc chọn "Nghỉ cả ngày" thay vì "Chỉ nghỉ một phần".
                        </p>
                        @error('shift_schedule_ids') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    <div id="srLeaveCustomTimeWrap" class="hidden space-y-2">
                        <div>
                            <label class="form-label">Ca cần nghỉ nửa ngày <span class="text-red-500">*</span></label>
                            <select id="srLeaveCustomShiftSelect" name="shift_schedule_id"></select>
                            @error('shift_schedule_id') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" id="srLeaveCustomMorningBtn" class="btn-secondary text-xs px-2.5 py-1.5">Nghỉ buổi sáng</button>
                            <button type="button" id="srLeaveCustomAfternoonBtn" class="btn-secondary text-xs px-2.5 py-1.5">Nghỉ buổi chiều</button>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">Từ giờ <span class="text-red-500">*</span></label>
                                <input type="time" id="srLeaveFromTime" name="from_time" class="form-input" value="{{ old('from_time') }}">
                                @error('from_time') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="form-label">Đến giờ <span class="text-red-500">*</span></label>
                                <input type="time" id="srLeaveToTime" name="to_time" class="form-input" value="{{ old('to_time') }}">
                                @error('to_time') <p class="form-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div id="srLeaveDurationInfo" class="hidden mt-1 rounded-lg bg-slate-50 dark:bg-slate-800/60 px-3 py-2 text-xs text-slate-600 dark:text-slate-300"></div>
                        <p id="srLeaveCustomShiftHint" class="hidden mt-1 text-xs text-amber-600 dark:text-amber-400">
                            <i class="bi bi-exclamation-triangle-fill mr-1"></i>Không tìm thấy ca đã xếp cho nhân viên này vào đúng ngày đã chọn.
                        </p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-employee-combobox name="handover_employee_id" :employees="$allEmployees" :selected="old('handover_employee_id')"
                            label="Người nhận bàn giao" placeholder="Tìm theo tên, mã NV..." />
                    </div>
                    <div>
                        <label class="form-label">Số điện thoại</label>
                        <input type="text" name="handover_phone" class="form-input" value="{{ old('handover_phone') }}" placeholder="SĐT người nhận bàn giao...">
                    </div>
                </div>
                <div>
                    <label class="form-label">Nội dung trao đổi</label>
                    <textarea name="handover_note" rows="2" class="form-input" placeholder="Nội dung bàn giao, trao đổi công việc...">{{ old('handover_note') }}</textarea>
                </div>
                <div>
                    <label class="form-label">Lý do <span class="text-red-500">*</span></label>
                    <textarea name="reason" rows="3" class="form-input" placeholder="Lý do xin nghỉ..." required>{{ old('reason') }}</textarea>
                    @error('reason') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <script type="application/json" id="srLeaveBalanceData">@json($annualLeaveBalances)</script>
                <script type="application/json" id="srLeaveOfficeData">@json($officeFlags)</script>
                <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                    <button type="button" onclick="closeModal('createStaffRequestModal')" class="btn-secondary">Hủy</button>
                    <button type="submit" class="btn-primary"><i class="bi bi-send"></i> Gửi đơn</button>
                </div>
            </form>

            {{-- ── Đổi ca làm — cần chọn đúng ca cụ thể, tạo từ trang Xếp ca ── --}}
            <div id="srSwapNotice" class="hidden space-y-4">
                <div class="rounded-lg bg-slate-50 dark:bg-slate-700/50 px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                    <i class="bi bi-info-circle text-pcrm-500 mr-1"></i>
                    Yêu cầu đổi ca cần chọn đúng ca làm việc cụ thể của bạn và đồng nghiệp — vui lòng tạo từ trang <strong>Xếp ca</strong>.
                </div>
                <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                    <button type="button" onclick="closeModal('createStaffRequestModal')" class="btn-secondary">Đóng</button>
                    <a href="{{ route('shift-schedules.index') }}" class="btn-primary"><i class="bi bi-calendar-week"></i> Đến trang Xếp ca</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="rejectStaffRequestModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('rejectStaffRequestModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-semibold text-slate-900 dark:text-white">Từ chối yêu cầu <span id="rejectStaffRequestCode"></span></h3>
            <button onclick="closeModal('rejectStaffRequestModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form id="rejectStaffRequestForm" method="POST" class="px-4 sm:px-6 py-4 sm:py-5 space-y-4">
            @csrf
            <div>
                <label class="form-label">Lý do từ chối <span class="text-red-500">*</span></label>
                <textarea name="rejection_reason" rows="3" class="form-input" required></textarea>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('rejectStaffRequestModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-medium">Từ chối</button>
            </div>
        </form>
    </div>
</div>

<div id="approveLateEarlyModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('approveLateEarlyModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-semibold text-slate-900 dark:text-white">Duyệt yêu cầu <span id="approveLateEarlyCode"></span></h3>
            <button onclick="closeModal('approveLateEarlyModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form id="approveLateEarlyForm" method="POST" class="px-4 sm:px-6 py-4 sm:py-5 space-y-4">
            @csrf
            <div>
                <label class="form-label">Kết quả duyệt <span class="text-red-500">*</span></label>
                <select name="outcome" class="form-input" required>
                    <option value="normal">Công thường (tính đủ công, quên đi muộn/về sớm)</option>
                    <option value="actual">Trừ giờ thực tế (vẫn tính đi muộn/về sớm)</option>
                </select>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('approveLateEarlyModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-primary"><i class="bi bi-check-lg"></i> Duyệt</button>
            </div>
        </form>
    </div>
</div>

{{-- Xoá yêu cầu (đã duyệt/từ chối). Với yêu cầu đã duyệt, nêu rõ tác động sẽ được ĐẢO NGƯỢC. --}}
<div id="deleteHubModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('deleteHubModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-semibold text-slate-900 dark:text-white">Xoá yêu cầu <span id="deleteHubCode"></span></h3>
            <button onclick="closeModal('deleteHubModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form id="deleteHubForm" method="POST" class="px-4 sm:px-6 py-4 sm:py-5 space-y-4">
            @csrf @method('DELETE')
            <p class="text-sm text-slate-600 dark:text-slate-300">Bạn có chắc muốn xoá yêu cầu này?</p>
            <div id="deleteHubNote" class="hidden rounded-lg bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 px-3 py-2 text-xs text-amber-700 dark:text-amber-300">
                <i class="bi bi-arrow-counterclockwise mr-1"></i><span id="deleteHubNoteText"></span>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('deleteHubModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-medium"><i class="bi bi-trash mr-1"></i>Xoá</button>
            </div>
        </form>
    </div>
</div>
@endpush

@push('scripts')
<script>
function srSwitchType(type) {
    document.getElementById('srStaffType').value = type;

    const staffForm = document.getElementById('srStaffForm');
    const leaveForm  = document.getElementById('srLeaveForm');
    const swapNotice = document.getElementById('srSwapNotice');

    staffForm.classList.add('hidden');
    leaveForm.classList.add('hidden');
    swapNotice.classList.add('hidden');

    if (type === 'leave') {
        leaveForm.classList.remove('hidden');
    } else if (type === 'shift_swap') {
        swapNotice.classList.remove('hidden');
    } else {
        staffForm.classList.remove('hidden');
        document.querySelectorAll('.sr-field-group').forEach(function(el) {
            el.classList.toggle('hidden', el.dataset.type !== type);
        });
    }
}

function openRejectStaffRequestModal(actionUrl, code) {
    document.getElementById('rejectStaffRequestCode').textContent = code;
    document.getElementById('rejectStaffRequestForm').action = actionUrl;
    openModal('rejectStaffRequestModal');
}

function openApproveStaffRequestModal(actionUrl, code, typeLabel, employee) {
    document.getElementById('approveSrForm').action = actionUrl;
    document.getElementById('approveSrCode').textContent = code;
    document.getElementById('approveSrSub').textContent = [typeLabel, employee].filter(Boolean).join(' · ');
    openModal('approveStaffRequestModal');
}

function openCancelStaffRequestModal(actionUrl, code) {
    document.getElementById('cancelSrForm').action = actionUrl;
    document.getElementById('cancelSrCode').textContent = code;
    openModal('cancelStaffRequestModal');
}

function openApproveLateEarlyModal(actionUrl, code) {
    document.getElementById('approveLateEarlyCode').textContent = code;
    document.getElementById('approveLateEarlyForm').action = actionUrl;
    openModal('approveLateEarlyModal');
}

// Xoá yêu cầu đã duyệt/từ chối. Với yêu cầu đã duyệt, hiện chú thích tác động sẽ được đảo ngược
// (data-note do server tính theo từng loại — hoàn phép/trừ tăng ca/khôi phục lịch/chấm công...).
function openDeleteHubModal(btn) {
    document.getElementById('deleteHubCode').textContent = btn.dataset.code || '';
    document.getElementById('deleteHubForm').action = btn.dataset.destroy;

    const note = (btn.dataset.note || '').trim();
    const box = document.getElementById('deleteHubNote');
    document.getElementById('deleteHubNoteText').textContent = note;
    box.classList.toggle('hidden', note === '');

    openModal('deleteHubModal');
}

// Nghỉ một phần có 2 mode:
// - "shifts" (mặc định, mọi nhân viên): chọn nhiều ca cụ thể (có thể nhiều ngày) — dùng cho NV
//   part-time/đa ca (VD làm 2 ca/ngày, chỉ nghỉ 1 ca).
// - "custom_time" (chỉ khối văn phòng, is_office=true): nghỉ nửa ngày theo khung giờ cụ thể trong
//   đúng 1 ca/1 ngày (VD NV văn phòng làm 1 ca 8h/ngày, chỉ nghỉ buổi sáng).
// Cả 2 đều gọi chung AJAX shiftsForRange mỗi khi đổi ngày/nhân viên — không tải sẵn toàn bộ ca.
const srLeaveOfficeFlags = JSON.parse(document.getElementById('srLeaveOfficeData')?.textContent || '{}');
let srLeaveShiftOptions = [];
let srLeaveShiftTS = null;       // Tom Select — chọn nhiều ca cụ thể (mode "shifts")
let srLeaveCustomShiftTS = null; // Tom Select — chọn đúng 1 ca (mode "custom_time")
let srLeaveShiftsRequestId = 0;
const srLeaveOldSelectedIds = @json(old('shift_schedule_ids', []));
const srLeaveOldShiftScheduleId = @json(old('shift_schedule_id'));

function srInitLeaveTomSelects() {
    if (typeof TomSelect === 'undefined' || srLeaveShiftTS) return;

    srLeaveShiftTS = new TomSelect('#srLeaveShiftSelect', {
        plugins: ['remove_button'],
        placeholder: 'Gõ để tìm ca theo ngày/tên ca, hoặc bấm để xem danh sách...',
        maxItems: null,
        maxOptions: null,
        render: {
            option: function (data, escape) {
                return '<div>' + escape(data.text)
                    + (data.shift_type === 'parttime' ? ' <span class="badge badge-neutral text-[10px]">part-time</span>' : '')
                    + '</div>';
            },
        },
        onChange: function () { srRefreshLeaveBalanceNote(); },
    });

    srLeaveCustomShiftTS = new TomSelect('#srLeaveCustomShiftSelect', {
        maxItems: 1,
        placeholder: '— Chọn ca —',
        onChange: srApplyLeaveCustomShiftBounds,
    });
}

function srGetLeaveEmployeeId() {
    const form = document.getElementById('srLeaveForm');
    if (!form) return null;
    const empHidden = form.querySelector('[name="employee_id"]');
    return (empHidden ? empHidden.value : form.dataset.ownEmployeeId) || null;
}

// toggleSrLeavePartialMode đã bị loại bỏ vì is_partial_day hiện tại được tự động kích hoạt khi có ca xếp lịch.

// Chỉ nhân viên khối văn phòng (is_office) VÀ đang chọn đúng 1 ngày mới được nghỉ nửa ngày theo
// giờ — tự chuyển về mode "theo ca cụ thể" nếu không còn hợp lệ (đổi sang NV khác/mở rộng ngày).
function srSyncLeavePartialModeVisibility() {
    const toggle = document.getElementById('srLeavePartialToggle');
    if (!toggle || toggle.value !== '1') return;

    const form = document.getElementById('srLeaveForm');
    const dateFrom = form.querySelector('[name="date_from"]').value;
    const dateTo = form.querySelector('[name="date_to"]').value || dateFrom;
    const employeeId = srGetLeaveEmployeeId();
    const isOffice = !!(employeeId && srLeaveOfficeFlags[employeeId]);
    const isSingleDay = !!dateFrom && dateFrom === dateTo;
    const customAllowed = isOffice && isSingleDay;

    const modeShiftsLabel = document.getElementById('srLeaveModeShiftsLabel');
    if (modeShiftsLabel) {
        modeShiftsLabel.textContent = customAllowed ? 'Nghỉ cả ngày (theo ca)' : 'Nghỉ theo ca cụ thể';
    }

    const modeGroup = document.getElementById('srLeavePartialModeGroup');
    if (modeGroup) {
        modeGroup.classList.toggle('hidden', !customAllowed);
    }

    document.getElementById('srLeaveModeCustomLabel').classList.toggle('hidden', !customAllowed);

    const modeShifts = document.getElementById('srLeaveModeShifts');
    const modeCustom = document.getElementById('srLeaveModeCustom');
    if (!customAllowed && modeCustom.checked) {
        modeShifts.checked = true;
    }

    const useCustom = customAllowed && modeCustom.checked;
    document.getElementById('srLeaveShiftPickerWrap').classList.toggle('hidden', useCustom);
    document.getElementById('srLeaveCustomTimeWrap').classList.toggle('hidden', !useCustom);
}

// Nạp lại toàn bộ option cho Tom Select đa lựa chọn — giữ lại các lựa chọn cũ nếu ca đó vẫn còn
// hợp lệ trong danh sách mới.
function srPopulateLeaveShiftTS(options) {
    if (!srLeaveShiftTS) return;
    const previousValues = srLeaveShiftTS.getValue(); // mảng string
    srLeaveShiftTS.clear(true);
    srLeaveShiftTS.clearOptions();
    options.forEach(function (o) {
        srLeaveShiftTS.addOption({ value: String(o.id), text: o.label, shift_type: o.shift_type });
    });
    srLeaveShiftTS.refreshOptions(false);

    const validIds = options.map(function (o) { return String(o.id); });
    let restore = previousValues.filter(function (v) { return validIds.includes(v); });
    if (srLeaveOldSelectedIds.length && restore.length === 0 && previousValues.length === 0) {
        restore = srLeaveOldSelectedIds.map(String).filter(function (v) { return validIds.includes(v); });
        srLeaveOldSelectedIds.length = 0;
    }
    srLeaveShiftTS.setValue(restore, true);
}

function srRenderLeaveCustomShiftOptions() {
    if (!srLeaveCustomShiftTS) return;
    const hint = document.getElementById('srLeaveCustomShiftHint');
    const form = document.getElementById('srLeaveForm');
    const dateFrom = form.querySelector('[name="date_from"]').value;
    const options = srLeaveShiftOptions.filter(function (o) { return o.date === dateFrom; });

    const previousValue = srLeaveCustomShiftTS.getValue() || String(srLeaveOldShiftScheduleId || '');
    srLeaveCustomShiftTS.clear(true);
    srLeaveCustomShiftTS.clearOptions();
    options.forEach(function (o) {
        srLeaveCustomShiftTS.addOption({ value: String(o.id), text: o.label, start: o.start_time || '', end: o.end_time || '', split: o.split_time || '', breakStart: o.break_start || '', breakMinutes: o.break_minutes || 0, shiftType: o.shift_type || 'fulltime', leaveAdjusted: !!o.leave_adjusted });
    });
    srLeaveCustomShiftTS.refreshOptions(false);

    const validIds = options.map(function (o) { return String(o.id); });
    if (previousValue && validIds.includes(String(previousValue))) {
        srLeaveCustomShiftTS.setValue(String(previousValue), true);
    }
    if (hint) hint.classList.toggle('hidden', options.length > 0);
    srApplyLeaveCustomShiftBounds();
}

function srApplyLeaveCustomShiftBounds() {
    const fromInput = document.getElementById('srLeaveFromTime');
    const toInput = document.getElementById('srLeaveToTime');
    if (!fromInput || !toInput) return;
    // KHÔNG dùng thuộc tính min/max native của <input type="time"> — chúng bung popup tiếng Anh
    // khó hiểu ("Value must be 12:00 or later") và chặn nhầm khi giờ nghỉ nằm ngoài khung giờ ca
    // (VD nghỉ buổi sáng 09:00–14:00 nhưng ca bắt đầu muộn hơn). Việc kiểm tra khung giờ hợp lệ do
    // server đảm nhiệm với thông báo tiếng Việt rõ ràng. Gỡ luôn min/max nếu đã set trước đó.
    ['min', 'max'].forEach(function (attr) {
        fromInput.removeAttribute(attr);
        toInput.removeAttribute(attr);
    });
    srUpdateLeaveDurationInfo();
}

// Option (ca) đang chọn ở picker "nghỉ nửa ngày theo giờ" — chứa start/end/split/break để nút
// "Nghỉ buổi sáng/chiều" và box gợi ý tính khung giờ theo đúng ca. Trả null nếu chưa chọn ca.
function srGetSelectedCustomShiftOption() {
    const value = srLeaveCustomShiftTS && srLeaveCustomShiftTS.getValue();
    return value ? srLeaveCustomShiftTS.options[value] : null;
}

// 'HH:MM' -> số phút trong ngày.
function srLeaveTimeToMinutes(t) {
    if (!t) return null;
    const parts = t.split(':');
    return parseInt(parts[0], 10) * 60 + parseInt(parts[1] || '0', 10);
}

// Số phút CÔNG thực trong [from, to] của ca đang chọn — trừ phần trùng giờ nghỉ giữa ca.
// Mirror của Shift::workMinutesInWindow() ở phía server.
function srLeaveWorkMinutesInWindow(from, to, opt) {
    const f = srLeaveTimeToMinutes(from), t = srLeaveTimeToMinutes(to);
    if (f === null || t === null || t <= f) return null;
    let minutes = t - f;
    const bs = opt.breakStart ? srLeaveTimeToMinutes(opt.breakStart) : null;
    const bm = parseInt(opt.breakMinutes || 0, 10);
    if (bs !== null && bm > 0) {
        const be = bs + bm;
        const os = Math.max(f, bs), oe = Math.min(t, be);
        if (oe > os) minutes -= (oe - os);
    }
    return Math.max(0, minutes);
}

// Tổng phút công của ca (span - giờ nghỉ) — mẫu số quy đổi công.
function srLeaveNetWorkMinutes(opt) {
    const s = srLeaveTimeToMinutes(opt.start), e = srLeaveTimeToMinutes(opt.end);
    if (s === null || e === null) return 0;
    let span = e - s;
    if (span <= 0) span += 24 * 60; // ca qua đêm
    return Math.max(0, span - parseInt(opt.breakMinutes || 0, 10));
}

// Box gợi ý: khung giờ ca + giờ nghỉ thực tế (đã trừ giờ nghỉ giữa ca) + quy đổi CÔNG bị trừ vào
// phép năm (nửa ngày = 0.5 công). Đồng nhất với trang /leave-requests.
function srUpdateLeaveDurationInfo() {
    const box = document.getElementById('srLeaveDurationInfo');
    const fromInput = document.getElementById('srLeaveFromTime');
    const toInput = document.getElementById('srLeaveToTime');
    if (!box || !fromInput || !toInput) return;

    const opt = srGetSelectedCustomShiftOption();
    if (!opt || !opt.start || !opt.end) {
        box.classList.add('hidden');
        return;
    }

    const rangeLabel = opt.leaveAdjusted ? 'Khung giờ còn lại' : 'Khung giờ ca';
    let html = '<i class="bi bi-clock-history mr-1"></i>' + rangeLabel + ': <b>' + opt.start + '–' + opt.end + '</b>';
    if (opt.leaveAdjusted) {
        html += '<br><span class="text-amber-600 dark:text-amber-400"><i class="bi bi-info-circle-fill mr-1"></i>Một phần ca này đã được duyệt nghỉ theo đơn khác — chỉ còn xin nghỉ được trong khung giờ trên. Muốn nghỉ sớm hơn, hãy xoá đơn nghỉ đã duyệt trước đó.</span>';
    }
    const from = fromInput.value, to = toInput.value;

    if (from && to) {
        const wm = srLeaveWorkMinutesInWindow(from, to, opt);
        if (wm === null) {
            html += ' · <span class="text-red-600 dark:text-red-400 font-semibold">Đến giờ phải sau Từ giờ</span>';
        } else {
            const h = Math.floor(wm / 60), m = wm % 60;
            const dur = h + ' giờ' + (m ? ' ' + m + ' phút' : '');
            html += ' · Nghỉ thực tế: <b class="text-pcrm-700 dark:text-pcrm-300">' + dur + '</b>';

            const net = srLeaveNetWorkMinutes(opt);
            if (opt.shiftType !== 'parttime' && net > 0) {
                const cong = Math.round(Math.min(1, wm / net) * 100) / 100;
                html += ' ≈ trừ <b class="text-pcrm-700 dark:text-pcrm-300">' + cong + ' công</b> phép năm';
            }

            if (from < opt.start || to > opt.end) {
                html += '<br><span class="text-red-600 dark:text-red-400 font-semibold"><i class="bi bi-exclamation-triangle-fill mr-1"></i>Giờ nghỉ đang nằm ngoài khung giờ ca — vui lòng nhập trong ' + opt.start + '–' + opt.end + '.</span>';
            }
        }
    }

    box.innerHTML = html;
    box.classList.remove('hidden');
}

function srSetLeaveCustomTimeRange(fromTime, toTime) {
    const fromInput = document.getElementById('srLeaveFromTime');
    const toInput = document.getElementById('srLeaveToTime');
    const value = srLeaveCustomShiftTS && srLeaveCustomShiftTS.getValue();
    const opt = value ? srLeaveCustomShiftTS.options[value] : null;
    if (!opt) return;
    const start = opt.start || '00:00';
    const end = opt.end || '23:59';
    fromInput.value = fromTime < start ? start : (fromTime > end ? end : fromTime);
    toInput.value = toTime > end ? end : (toTime < start ? start : toTime);
    srUpdateLeaveDurationInfo();
}

// Gọi AJAX lấy ca đã xếp của nhân viên trong khoảng ngày đang chọn (thay cho danh sách tải sẵn 14
// ngày trước - 90 ngày sau trước đây) — luôn phản ánh đúng dữ liệu mới nhất. Dùng chung endpoint
// với trang /leave-requests, đổ dữ liệu cho cả 2 Tom Select (đa lựa chọn + chọn 1 ca).
function srRefreshLeaveShifts() {
    const form = document.getElementById('srLeaveForm');
    if (!form) return;
    const hint = document.getElementById('srLeaveNoShiftHint');
    const employeeId = srGetLeaveEmployeeId();
    const dateFrom = form.querySelector('[name="date_from"]').value;
    const dateTo = form.querySelector('[name="date_to"]').value || dateFrom;

    srLeaveShiftOptions = [];
    srPopulateLeaveShiftTS([]);
    srRenderLeaveCustomShiftOptions();

    if (!employeeId || !dateFrom || !dateTo) {
        document.getElementById('srLeavePartialToggle').value = "0";
        document.getElementById('srLeavePartialWrap').classList.add('hidden');
        if (hint) hint.classList.add('hidden');
        return;
    }

    const requestId = ++srLeaveShiftsRequestId;
    const url = form.dataset.shiftsUrl + '?employee_id=' + encodeURIComponent(employeeId)
        + '&date_from=' + encodeURIComponent(dateFrom) + '&date_to=' + encodeURIComponent(dateTo);

    fetch(url, { headers: { 'Accept': 'application/json' } })
        .then(function (res) { return res.ok ? res.json() : { options: [] }; })
        .then(function (data) {
            if (requestId !== srLeaveShiftsRequestId) return; // trả lời trễ, đã có yêu cầu mới hơn
            srLeaveShiftOptions = data.options || [];
            
            const hasShifts = srLeaveShiftOptions.length > 0;
            document.getElementById('srLeavePartialToggle').value = hasShifts ? "1" : "0";
            document.getElementById('srLeavePartialWrap').classList.toggle('hidden', !hasShifts);

            if (hint) hint.classList.toggle('hidden', hasShifts);

            if (hasShifts) {
                srPopulateLeaveShiftTS(srLeaveShiftOptions);
                srRenderLeaveCustomShiftOptions();
                srSyncLeavePartialModeVisibility();
            }
        })
        .catch(function () {
            if (requestId !== srLeaveShiftsRequestId) return;
        });
}

document.addEventListener('DOMContentLoaded', function () {
    const leaveForm = document.getElementById('srLeaveForm');
    if (!leaveForm) return;

    srInitLeaveTomSelects();

    ['date_from', 'date_to'].forEach(function (name) {
        const el = leaveForm.querySelector('[name="' + name + '"]');
        if (el) el.addEventListener('change', function () {
            srRefreshLeaveShifts();
            srRefreshLeaveBalanceNote();
        });
    });
    const empHidden = leaveForm.querySelector('[name="employee_id"]');
    if (empHidden) empHidden.addEventListener('change', function () {
        srRefreshLeaveShifts();
        srRefreshLeaveBalanceNote();
    });
    const typeSelect = document.getElementById('srLeaveType');
    if (typeSelect) typeSelect.addEventListener('change', srRefreshLeaveBalanceNote);

    ['srLeaveModeShifts', 'srLeaveModeCustom'].forEach(function (id) {
        document.getElementById(id).addEventListener('change', function () {
            if (this.value === 'custom_time') {
                if (srLeaveShiftTS) srLeaveShiftTS.clear(true);
            } else {
                if (srLeaveCustomShiftTS) srLeaveCustomShiftTS.clear(true);
                document.getElementById('srLeaveFromTime').value = '';
                document.getElementById('srLeaveToTime').value = '';
            }
            srSyncLeavePartialModeVisibility();
            srRefreshLeaveBalanceNote();
        });
    });

    ['srLeaveFromTime', 'srLeaveToTime'].forEach(function (id) {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', srUpdateLeaveDurationInfo);
    });

    document.getElementById('srLeaveCustomMorningBtn').addEventListener('click', function () {
        // Nghỉ buổi sáng = từ đầu ca đến điểm chia nửa giờ công (split_time, đã tính giờ nghỉ giữa
        // ca). VD ca 09:00–18:00 nghỉ 12:00–13:00 → 09:00–14:00 (= 4h công = nửa ngày). KHÔNG hardcode
        // 12:00 vì sẽ ra 09:00–12:00 (chỉ 3h) — sai tỉ lệ nửa ngày.
        const opt = srGetSelectedCustomShiftOption();
        if (!opt) return;
        srSetLeaveCustomTimeRange(opt.start || '00:00', opt.split || opt.end || '12:00');
    });
    document.getElementById('srLeaveCustomAfternoonBtn').addEventListener('click', function () {
        // Nghỉ buổi chiều = từ điểm chia nửa giờ công đến cuối ca. VD 14:00–18:00 (= 4h công).
        const opt = srGetSelectedCustomShiftOption();
        if (!opt) return;
        srSetLeaveCustomTimeRange(opt.split || opt.start || '13:00', opt.end || '23:59');
    });

    srRefreshLeaveShifts();
    srRefreshLeaveBalanceNote();
});

// Hiển thị số ngày phép năm còn lại của nhân viên đang chọn (hoặc chính mình nếu không phải approver)
// khi chọn "Nghỉ phép năm", cảnh báo trực tiếp nếu số ngày đang xin nghỉ vượt quá số ngày còn lại.
function srRefreshLeaveBalanceNote() {
    const form = document.getElementById('srLeaveForm');
    const typeSelect = document.getElementById('srLeaveType');
    const note = document.getElementById('srLeaveBalanceNote');
    const badge = document.getElementById('srLeaveBalanceBadge');
    if (!form || !typeSelect || !note || !badge) return;

    if (typeSelect.value !== 'annual') {
        note.classList.add('hidden');
        return;
    }

    const empHidden = form.querySelector('[name="employee_id"]');
    const employeeId = (empHidden && empHidden.value) ? empHidden.value : form.dataset.ownEmployeeId;
    const balances = JSON.parse(document.getElementById('srLeaveBalanceData').textContent || '{}');
    const remaining = employeeId ? balances[employeeId] : undefined;

    badge.classList.remove('badge-success', 'badge-warning', 'badge-danger');
    note.classList.remove('hidden');

    if (!employeeId) {
        badge.classList.add('badge-warning');
        badge.innerHTML = '<i class="bi bi-info-circle-fill mr-1"></i>Chọn nhân viên để xem số ngày phép năm còn lại';
        return;
    }

    if (remaining === undefined) {
        badge.classList.add('badge-danger');
        badge.innerHTML = '<i class="bi bi-exclamation-triangle-fill mr-1"></i>Không đủ điều kiện nghỉ phép năm — hãy chọn Nghỉ không lương';
        return;
    }

    const isPartial = document.getElementById('srLeavePartialToggle').value === '1';
    if (isPartial) {
        // Nghỉ theo ca cụ thể / nghỉ nửa ngày theo giờ đều chỉ trừ đúng tỉ lệ (tính chính xác ở
        // server khi gửi đơn) — chỉ hiện số ngày phép còn lại, không ước tính số ngày xin ở đây.
        const isCustomTime = document.getElementById('srLeaveModeCustom').checked
            && !document.getElementById('srLeaveModeCustomLabel').classList.contains('hidden');
        badge.classList.add('badge-success');
        badge.innerHTML = '<i class="bi bi-calendar-check-fill mr-1"></i>Phép năm còn lại: ' + remaining + ' ngày ('
            + (isCustomTime ? 'nghỉ nửa ngày sẽ trừ theo đúng tỉ lệ giờ đã chọn' : 'nghỉ theo ca cụ thể sẽ trừ theo tổng tỉ lệ giờ của các ca đã chọn') + ')';
        return;
    }

    const dateFrom = form.querySelector('[name="date_from"]').value;
    const dateTo = form.querySelector('[name="date_to"]').value;
    let requestedDays = null;
    if (dateFrom && dateTo) {
        const diffMs = new Date(dateTo) - new Date(dateFrom);
        if (diffMs >= 0) requestedDays = Math.round(diffMs / 86400000) + 1;
    }

    if (requestedDays !== null && requestedDays > remaining) {
        badge.classList.add('badge-danger');
        badge.innerHTML = '<i class="bi bi-exclamation-triangle-fill mr-1"></i>Còn ' + remaining + ' ngày phép năm — không đủ cho ' + requestedDays + ' ngày đang xin. Hãy chọn loại nghỉ khác hoặc giảm số ngày.';
    } else if (remaining <= 2) {
        badge.classList.add('badge-warning');
        badge.innerHTML = '<i class="bi bi-info-circle-fill mr-1"></i>Phép năm còn lại: ' + remaining + ' ngày';
    } else {
        badge.classList.add('badge-success');
        badge.innerHTML = '<i class="bi bi-calendar-check-fill mr-1"></i>Phép năm còn lại: ' + remaining + ' ngày';
    }
}

@if($errors->any() && old('_modal') === 'srLeaveForm')
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('srTypeSelect').value = 'leave';
    srSwitchType('leave');
    srRefreshLeaveShifts();
    srRefreshLeaveBalanceNote();
    openModal('createStaffRequestModal');
});
@elseif($errors->any() && old('_modal') === 'srStaffForm')
document.addEventListener('DOMContentLoaded', function() {
    const t = '{{ old('type', 'attendance_correction') }}';
    document.getElementById('srTypeSelect').value = t;
    srSwitchType(t);
    openModal('createStaffRequestModal');
});
@endif

function toggleFilterDrawer(open) {
    const drawer = document.getElementById('filterDrawer');
    const overlay = document.getElementById('filterDrawerOverlay');
    if (open) {
        overlay.classList.remove('hidden');
        overlay.offsetHeight; // trigger reflow
        overlay.classList.add('opacity-100', 'pointer-events-auto');
        drawer.classList.remove('translate-x-full');
    } else {
        overlay.classList.remove('opacity-100', 'pointer-events-auto');
        drawer.classList.add('translate-x-full');
        setTimeout(() => {
            if (drawer.classList.contains('translate-x-full')) {
                overlay.classList.add('hidden');
            }
        }, 300);
    }
}

function resetFilters() {
    const drawer = document.getElementById('filterDrawer');
    const inputs = drawer.querySelectorAll('input, select');
    inputs.forEach(input => {
        if (input.type === 'text' || input.type === 'date' || input.type === 'hidden') {
            input.value = '';
        } else if (input.tagName === 'SELECT') {
            input.selectedIndex = 0;
        }
    });
    toggleFilterDrawer(false);
    window.location.href = "{{ route('staff-requests.index') }}";
}

function getTypeBadgeClass(key) {
    const classes = {
        'attendance_correction': 'bg-sky-100 dark:bg-sky-900/30 text-sky-700 dark:text-sky-400',
        'business_trip': 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400',
        'late_early': 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400',
        'leave': 'bg-pcrm-100 dark:bg-pcrm-900/30 text-pcrm-700 dark:text-pcrm-400',
        'time_change': 'bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-400',
        'overtime': 'bg-rose-100 dark:bg-rose-900/30 text-rose-700 dark:text-rose-400',
        'shift_swap': 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400'
    };
    return classes[key] || 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300';
}

function openRequestDetailModal(source, id) {
    // Show a loading state in the modal
    document.getElementById('dtRequestCode').textContent = 'Đang tải...';
    document.getElementById('dtEmployeeSection').classList.add('hidden');
    document.getElementById('dtOutcomeSection').classList.add('hidden');
    document.getElementById('dtReviewerSection').classList.add('hidden');
    document.getElementById('dtRejectionSection').classList.add('hidden');
    document.getElementById('dtModalActions').innerHTML = '';
    
    document.getElementById('dtRequestTypeBadge').textContent = 'Đang tải...';
    document.getElementById('dtRequestTypeBadge').className = 'text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 text-slate-400';
    document.getElementById('dtRequestStatusBadge').textContent = '...';
    document.getElementById('dtRequestStatusBadge').className = 'badge badge-neutral';
    document.getElementById('dtRequestTime').textContent = '';
    document.getElementById('dtLeaveShiftsSection').classList.add('hidden');
    document.getElementById('dtRequestSummary').innerHTML = '<div class="flex justify-center py-4"><i class="bi bi-arrow-repeat animate-spin text-2xl text-pcrm-500"></i></div>';
    
    openModal('requestDetailModal');

    fetch('/staff-requests/' + source + '/' + id + '/details')
        .then(response => {
            if (!response.ok) throw new Error('Không thể tải dữ liệu');
            return response.json();
        })
        .then(r => {
            // Populate the modal fields with the loaded data
            document.getElementById('dtRequestCode').textContent = r.code;
            
            // Type badge
            const typeBadge = document.getElementById('dtRequestTypeBadge');
            typeBadge.textContent = r.type_label;
            typeBadge.className = 'text-xs font-semibold px-2.5 py-1 rounded-full ' + getTypeBadgeClass(r.type_key);

            // Status badge
            const statusBadge = document.getElementById('dtRequestStatusBadge');
            statusBadge.textContent = r.status_label;
            statusBadge.className = 'badge ' + r.status_badge;

            // Time
            document.getElementById('dtRequestTime').textContent = r.work_date_label;

            // Ca xin nghỉ (chỉ đơn nghỉ theo ca cụ thể)
            const shiftsSection = document.getElementById('dtLeaveShiftsSection');
            const shiftsList = document.getElementById('dtLeaveShiftsList');
            shiftsList.innerHTML = '';
            if (r.leave_shifts && r.leave_shifts.length) {
                r.leave_shifts.forEach(function (s) {
                    const li = document.createElement('li');
                    li.className = 'flex items-center justify-between gap-2 px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-900/20 text-xs';
                    const left = document.createElement('span');
                    left.className = 'font-semibold text-slate-800 dark:text-slate-200';
                    left.textContent = s.name + (s.time ? ' · ' + s.time : '');
                    const right = document.createElement('span');
                    right.className = 'text-slate-500 dark:text-slate-400 tabular-nums shrink-0';
                    right.textContent = s.date;
                    li.appendChild(left);
                    li.appendChild(right);
                    shiftsList.appendChild(li);
                });
                shiftsSection.classList.remove('hidden');
            } else {
                shiftsSection.classList.add('hidden');
            }

            // Summary/Reason
            document.getElementById('dtRequestSummary').textContent = r.summary || '—';

            // Employee section
            const empSection = document.getElementById('dtEmployeeSection');
            if (r.employee) {
                empSection.classList.remove('hidden');
                document.getElementById('dtEmployeeName').textContent = r.employee.name;
                document.getElementById('dtEmployeeBranch').textContent = r.employee.branch ? r.employee.branch.name : '—';
                const avatarImg = document.getElementById('dtEmployeeAvatarImg');
                const avatarText = document.getElementById('dtEmployeeAvatar');
                avatarText.textContent = r.employee.name.substring(0, 2).toUpperCase();
                if (r.employee_avatar_url) {
                    avatarImg.src = r.employee_avatar_url;
                    avatarImg.alt = r.employee.name;
                    avatarImg.classList.remove('hidden');
                    avatarText.classList.add('hidden');
                } else {
                    avatarImg.classList.add('hidden');
                    avatarText.classList.remove('hidden');
                }
            } else {
                empSection.classList.add('hidden');
            }

            // Outcomes
            const outcomeSection = document.getElementById('dtOutcomeSection');
            if (r.correction_outcome_label) {
                outcomeSection.classList.remove('hidden');
                const outcomeBadge = document.getElementById('dtOutcomeBadge');
                outcomeBadge.textContent = r.correction_outcome_label;
                outcomeBadge.className = 'badge ' + (r.correction_outcome_label === 'Đã tha lỗi' ? 'badge-success' : 'badge-neutral');
            } else {
                outcomeSection.classList.add('hidden');
            }

            // Rejection reason
            const rejectSection = document.getElementById('dtRejectionSection');
            if (r.status === 'rejected' && r.rejection_reason) {
                rejectSection.classList.remove('hidden');
                document.getElementById('dtRejectionReason').textContent = r.rejection_reason;
            } else {
                rejectSection.classList.add('hidden');
            }

            // Reviewer section
            const revSection = document.getElementById('dtReviewerSection');
            if (r.status !== 'pending' && r.reviewer) {
                revSection.classList.remove('hidden');
                document.getElementById('dtReviewerName').textContent = r.reviewer.name;
            } else {
                revSection.classList.add('hidden');
            }

            // Actions Footer
            const footer = document.getElementById('dtModalActions');
            footer.innerHTML = '';

            // Close button (always present)
            const closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'py-2 px-4 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-350 font-bold text-xs hover:bg-slate-50 dark:hover:bg-slate-800 transition active:scale-[0.98]';
            closeBtn.textContent = 'Đóng';
            closeBtn.onclick = function() { closeModal('requestDetailModal'); };
            footer.appendChild(closeBtn);

            // If pending and has approve permissions
            if (r.status === 'pending') {
                const canApprove = (r.approve_permission === 'approve-staff-requests' && {{ $canApproveStaff ? 'true' : 'false' }}) ||
                                    (r.approve_permission === 'approve-leave-requests' && {{ $canApproveLeave ? 'true' : 'false' }}) ||
                                    (r.approve_permission === 'approve-shift-swaps' && {{ $canApproveSwap ? 'true' : 'false' }});
                
                if (canApprove) {
                    // Approve button
                    const appBtn = document.createElement('button');
                    appBtn.type = 'button';
                    appBtn.className = 'py-2 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition active:scale-[0.98] shadow-sm flex items-center gap-1';
                    appBtn.innerHTML = '<i class="bi bi-check-lg"></i> Duyệt';
                    appBtn.onclick = function() {
                        closeModal('requestDetailModal');
                        if (r.type_key === 'late_early') {
                            openApproveLateEarlyModal(r.approve_route, r.code);
                        } else {
                            openApproveStaffRequestModal(r.approve_route, r.code, r.type_label, r.employee_name || '');
                        }
                    };
                    footer.appendChild(appBtn);

                    // Reject button
                    const rejBtn = document.createElement('button');
                    rejBtn.type = 'button';
                    rejBtn.className = 'py-2 px-4 rounded-xl bg-red-600 hover:bg-red-700 text-white font-bold text-xs transition active:scale-[0.98] shadow-sm flex items-center gap-1';
                    rejBtn.innerHTML = '<i class="bi bi-x-lg"></i> Từ chối';
                    rejBtn.onclick = function() {
                        closeModal('requestDetailModal');
                        openRejectStaffRequestModal(r.reject_route, r.code);
                    };
                    footer.appendChild(rejBtn);
                }
            }

            // Edit button if present
            if (r.source === 'staff_request' && r.edit_data) {
                const editBtn = document.createElement('button');
                editBtn.type = 'button';
                editBtn.className = 'py-2 px-4 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-bold text-xs hover:bg-slate-100 dark:hover:bg-slate-800 transition active:scale-[0.98] flex items-center gap-1';
                editBtn.innerHTML = '<i class="bi bi-pencil"></i> Sửa';
                editBtn.onclick = function() {
                    closeModal('requestDetailModal');
                    openEditStaffRequestModal(r.edit_data);
                };
                footer.appendChild(editBtn);
            }

            // Delete/Cancel button
            if (r.can_manage_own || r.can_purge) {
                const delBtn = document.createElement('button');
                delBtn.type = 'button';
                delBtn.className = 'py-2 px-4 rounded-xl bg-red-50 hover:bg-red-100 dark:bg-red-950/20 dark:text-red-400 text-red-600 font-bold text-xs transition active:scale-[0.98] border border-red-200/30 dark:border-red-800/30 flex items-center gap-1';
                delBtn.innerHTML = '<i class="bi bi-trash"></i> ' + (r.can_purge && !r.can_manage_own ? 'Xoá' : 'Huỷ');
                delBtn.onclick = function() {
                    closeModal('requestDetailModal');
                    if (r.status === 'pending' && r.can_manage_own) {
                        openCancelStaffRequestModal(r.destroy_route, r.code);
                    } else {
                        // Đã duyệt/từ chối: dùng modal xoá có cảnh báo tác động sẽ bị đảo ngược
                        openDeleteHubModal({ dataset: { destroy: r.destroy_route, code: r.code, status: r.status, note: r.reversal_note || '' } });
                    }
                };
                footer.appendChild(delBtn);
            }
        })
        .catch(err => {
            console.error(err);
            document.getElementById('dtRequestSummary').innerHTML = '<p class="text-xs text-red-500 font-semibold text-center py-2">Đã xảy ra lỗi khi tải thông tin yêu cầu.</p>';
        });
}
</script>
@endpush
