@extends('layouts.admin')

@section('title', 'Phiếu phạt')
@section('page-title', 'Phiếu phạt')
@section('page-subtitle', 'Theo dõi và xử lý phiếu phạt theo trạng thái, nhân viên và vi phạm')
@section('breadcrumb', 'Kỷ luật')

@section('page-actions')
    @can('create-penalties')
        <button onclick="openModal('createPenaltyModal')" class="btn-primary">
            <i class="bi bi-plus-lg"></i>
            <span>Tạo phiếu phạt</span>
        </button>
    @endcan
@endsection

@section('content')
    @php
        $statusMeta = [
            'pending'  => ['Chờ duyệt', 'badge-warning', 'bi-clock'],
            'approved' => ['Đã duyệt', 'badge-success', 'bi-check-circle-fill'],
            'rejected' => ['Từ chối', 'badge-danger', 'bi-x-circle-fill'],
            'revoked'  => ['Đã thu hồi', 'badge-neutral', 'bi-arrow-counterclockwise'],
        ];
        $currentStatus = request('status');
        $allCount = $statusCounts->sum();
        $tabQuery = fn($status) => array_filter(
            array_merge(request()->except(['status', 'page']), ['status' => $status]),
            fn($v) => $v !== null && $v !== ''
        );
        $activeFilters = array_filter([
            'search'    => request('search') ? 'Tìm: "' . request('search') . '"' : null,
            'date_from' => request('date_from') ? 'Từ ' . \Illuminate\Support\Carbon::parse(request('date_from'))->format('d/m/Y') : null,
            'date_to'   => request('date_to') ? 'Đến ' . \Illuminate\Support\Carbon::parse(request('date_to'))->format('d/m/Y') : null,
        ]);
    @endphp

    <div class="card">
        {{-- Tab trạng thái: lọc qua GET, giữ nguyên tìm kiếm/khoảng ngày --}}
        <nav class="status-tabs" aria-label="Lọc theo trạng thái">
            <a href="{{ route('penalties.index', $tabQuery(null)) }}"
               class="status-tab {{ !$currentStatus ? 'is-active' : '' }}" @if (!$currentStatus) aria-current="page" @endif>
                Tất cả <span class="status-tab-count">{{ number_format($allCount) }}</span>
            </a>
            @foreach ($statusMeta as $key => [$label])
                <a href="{{ route('penalties.index', $tabQuery($key)) }}"
                   class="status-tab {{ $currentStatus === $key ? 'is-active' : '' }} {{ $key === 'pending' && ($statusCounts[$key] ?? 0) > 0 ? 'has-attention' : '' }}"
                   @if ($currentStatus === $key) aria-current="page" @endif>
                    {{ $label }} <span class="status-tab-count">{{ number_format($statusCounts[$key] ?? 0) }}</span>
                </a>
            @endforeach
        </nav>

        <x-table-toolbar :paginator="$penalties" label="phiếu">
            <x-slot:info>
                @foreach ($activeFilters as $key => $text)
                    <a href="{{ route('penalties.index', request()->except([$key, 'page'])) }}" class="filter-chip" title="Bỏ bộ lọc này">
                        {{ $text }} <i class="bi bi-x" aria-hidden="true"></i><span class="sr-only">Bỏ bộ lọc</span>
                    </a>
                @endforeach
            </x-slot:info>

            <form action="{{ route('penalties.index') }}" method="GET" class="relative" role="search">
                @foreach (request()->only(['status', 'date_from', 'date_to']) as $k => $v)
                    @if ($v !== null && $v !== '') <input type="hidden" name="{{ $k }}" value="{{ $v }}"> @endif
                @endforeach
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none" aria-hidden="true"></i>
                <input type="search" name="search" value="{{ request('search') }}" class="form-input h-9 w-56 pl-8 text-sm"
                       placeholder="Tên, mã NV hoặc mã phiếu…" aria-label="Tìm phiếu phạt">
            </form>
            <button type="button" onclick="toggleFilterDrawer(true)" class="btn-secondary relative">
                <i class="bi bi-funnel"></i>
                <span>Bộ lọc</span>
                @if (request()->anyFilled(['date_from', 'date_to']))
                    <span class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-pcrm-600 rounded-full" aria-hidden="true"></span>
                @endif
            </button>
        </x-table-toolbar>

        <div class="card-body p-0">
            <div class="table-container border-0 rounded-none">
                <table class="table-base">
                    <caption class="sr-only">Danh sách phiếu phạt{{ $currentStatus ? ' — ' . ($statusMeta[$currentStatus][0] ?? $currentStatus) : '' }}</caption>
                    <thead>
                        <tr>
                            <th class="table-th lg:max-xl:hidden" scope="col">Mã phiếu</th>
                            <th class="table-th min-w-[170px] xl:min-w-[190px]" scope="col" data-mcard-title>Nhân viên / Đội</th>
                            <th class="table-th" scope="col">Vi phạm</th>
                            <th class="table-th text-right" scope="col">Điểm / Tiền</th>
                            <th class="table-th lg:max-xl:hidden" scope="col">Thời gian</th>
                            <th class="table-th" scope="col">Trạng thái</th>
                            <th class="table-th text-right" scope="col">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($penalties as $penalty)
                            @php
                                [$badgeLbl, $badgeCls, $badgeIcon] = $statusMeta[$penalty->status] ?? [$penalty->status, 'badge-neutral', 'bi-info-circle'];
                                $code = $penalty->code ?? '#' . $penalty->id;
                                $penaltyMembers = $penalty->members
                                    ->map(fn($m) => ['employee_id' => $m->employee_id, 'points_deducted' => $m->points_deducted])
                                    ->values()->toArray();
                                $penaltyAttachments = $penalty->attachments
                                    ->map(fn($a) => ['id' => $a->id, 'filename' => $a->filename, 'type' => $a->type, 'url' => $a->url])
                                    ->values()->toArray();
                                $hasPendingAppeal = $penalty->appeals->isNotEmpty();
                            @endphp
                            <tr class="table-tr-hover cursor-pointer" onclick="openPenaltyDetail({{ $penalty->id }})">
                                <td class="table-td whitespace-nowrap lg:max-xl:hidden">
                                    <a href="{{ route('penalties.show', $penalty) }}" onclick="event.stopPropagation()"
                                       class="font-mono text-xs font-medium text-pcrm-600 dark:text-pcrm-400 hover:underline">{{ $code }}</a>
                                </td>
                                <td class="table-td">
                                    <div class="flex items-center gap-2.5">
                                    <x-employee-avatar :employee="$penalty->employee" size="w-8 h-8" />
                                    <div class="min-w-0">
                                    <div class="font-medium text-slate-900 dark:text-white">{{ $penalty->employee->name ?? 'N/A' }}</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ $penalty->employee?->team?->name ?? 'Chưa gán đội' }}@if ($penalty->employee?->branch) · {{ $penalty->employee->branch->name }}@endif
                                    </div>
                                    </div>
                                    </div>
                                    @if ($penalty->members->count() > 0)
                                        <span class="mt-1 inline-flex items-center gap-1 text-[11px] font-medium text-slate-500 dark:text-slate-400" title="Phiếu áp dụng cho nhiều người">
                                            <i class="bi bi-people" aria-hidden="true"></i> +{{ $penalty->members->count() }} người liên đới
                                        </span>
                                    @endif
                                </td>
                                <td class="table-td text-sm">
                                    <div class="max-w-xs lg:max-2xl:max-w-[11rem] truncate text-slate-800 dark:text-slate-200" title="{{ $penalty->violation->name ?? '' }}">{{ $penalty->violation->name ?? 'N/A' }}</div>
                                    @if ($penalty->description)
                                        <div class="max-w-xs lg:max-2xl:max-w-[11rem] truncate text-xs text-slate-400 dark:text-slate-500" title="{{ $penalty->description }}">{{ $penalty->description }}</div>
                                    @endif
                                </td>
                                <td class="table-td text-right whitespace-nowrap">
                                    <span class="points-chip">-{{ number_format($penalty->total_points_deducted) }}</span>
                                    @if ($penalty->total_money_deducted > 0)
                                        <span class="mt-1 block text-xs font-medium tabular-nums text-slate-700 dark:text-slate-300">{{ number_format($penalty->total_money_deducted, 0, ',', '.') }}₫</span>
                                    @endif
                                </td>
                                <td class="table-td whitespace-nowrap text-xs text-slate-500 dark:text-slate-400 lg:max-xl:hidden">
                                    <span class="block text-slate-700 dark:text-slate-300">{{ $penalty->created_at->format('d/m/Y') }}</span>
                                    @if ($penalty->status === 'pending')
                                        <span class="text-[#C98219] dark:text-amber-400" title="Thời gian chờ duyệt">chờ {{ $penalty->created_at->diffForHumans(null, true) }}</span>
                                    @else
                                        {{ $penalty->created_at->format('H:i') }}
                                    @endif
                                </td>
                                <td class="table-td whitespace-nowrap">
                                    <span class="{{ $badgeCls }}"><i class="bi {{ $badgeIcon }}" aria-hidden="true"></i> {{ $badgeLbl }}</span>
                                    @if ($hasPendingAppeal)
                                        <span class="mt-1 flex items-center gap-1 text-[11px] font-medium text-[#1686B8]">
                                            <i class="bi bi-chat-left-text" aria-hidden="true"></i> Có khiếu nại
                                        </span>
                                    @endif
                                </td>
                                <td class="table-td text-right whitespace-nowrap">
                                    <div class="inline-flex items-center justify-end gap-0.5" onclick="event.stopPropagation()">
                                        <a href="{{ route('penalties.show', $penalty) }}" class="row-action" title="Xem chi tiết" aria-label="Xem chi tiết phiếu {{ $code }}">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @if ($penalty->status === 'pending')
                                            @can('create-penalties')
                                                <button type="button" title="Sửa" aria-label="Sửa phiếu {{ $code }}" data-ep-id="{{ $penalty->id }}"
                                                    data-ep-violation="{{ $penalty->violation_id }}"
                                                    data-ep-regulation="{{ $penalty->violation?->regulation_id ?? 0 }}"
                                                    data-ep-employee="{{ $penalty->employee_id }}"
                                                    data-ep-points="{{ $penalty->total_points_deducted }}"
                                                    data-ep-money="{{ $penalty->total_money_deducted }}"
                                                    data-ep-desc="{{ $penalty->description ?? '' }}"
                                                    data-ep-members="{{ json_encode($penaltyMembers) }}"
                                                    data-ep-attachments="{{ json_encode($penaltyAttachments) }}"
                                                    onclick="epOpenFromBtn(this)"
                                                    class="row-action">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                            @endcan
                                            @can('delete-penalties')
                                                <button type="button" title="Xoá" aria-label="Xoá phiếu {{ $code }}"
                                                    onclick="openDeletePenaltyModal({{ $penalty->id }}, {{ Illuminate\Support\Js::from($code) }})"
                                                    class="row-action row-action-danger">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            @endcan
                                        @elseif ($penalty->status === 'approved')
                                            @can('revoke-penalties')
                                                <button type="button" title="Thu hồi" aria-label="Thu hồi phiếu {{ $code }}"
                                                    onclick="openRevokePenaltyModal({{ $penalty->id }}, {{ Illuminate\Support\Js::from($code) }})"
                                                    class="row-action row-action-danger">
                                                    <i class="bi bi-arrow-counterclockwise"></i>
                                                </button>
                                            @endcan
                                            @if ($penalty->employee?->user_id === auth()->id())
                                                <button type="button" title="Khiếu nại" aria-label="Khiếu nại phiếu {{ $code }}"
                                                    onclick="openAppealPenaltyModal({{ $penalty->id }}, {{ Illuminate\Support\Js::from($code) }})"
                                                    class="row-action">
                                                    <i class="bi bi-chat-left-text"></i>
                                                </button>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="table-td py-16 text-center text-slate-400 dark:text-slate-500">
                                    <i class="bi bi-clipboard-check mb-3 block text-4xl" aria-hidden="true"></i>
                                    @if ($currentStatus || $activeFilters)
                                        <p class="text-sm font-medium text-slate-600 dark:text-slate-300">Không có phiếu phạt nào khớp bộ lọc</p>
                                        <a href="{{ route('penalties.index') }}" class="mt-3 inline-flex items-center gap-1.5 text-sm text-pcrm-600 dark:text-pcrm-400 hover:underline">
                                            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Xoá bộ lọc
                                        </a>
                                    @else
                                        <p class="text-sm font-medium text-slate-600 dark:text-slate-300">Chưa có phiếu phạt nào</p>
                                        @can('create-penalties')
                                            <button onclick="openModal('createPenaltyModal')"
                                                class="mt-3 inline-flex items-center gap-1.5 text-sm text-pcrm-600 dark:text-pcrm-400 hover:underline">
                                                <i class="bi bi-plus-lg" aria-hidden="true"></i> Tạo phiếu đầu tiên
                                            </button>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($penalties->hasPages())
            <div class="card-footer">
                {{ $penalties->links() }}
            </div>
        @endif
    </div>

@endsection

@push('modals')
    @include('penalties.partials.detail-modal')
    @include('penalties.partials.create-modal')
    @include('penalties.partials.edit-modal')
    @include('penalties.partials.delete-modal')
    @include('penalties.partials.revoke-modal')
    @include('penalties.partials.appeal-modal')

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
        <form action="{{ route('penalties.index') }}" method="GET" class="flex-1 flex flex-col overflow-y-auto">
            <div class="p-5 space-y-4 flex-1">
                <div>
                    <label class="block text-xs font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wider mb-2">Tìm kiếm</label>
                    <div class="relative w-full">
                        <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                        <input type="text" name="search" value="{{ request('search') }}"
                               class="form-input pl-8 text-sm w-full" placeholder="Tên NV, mã phiếu...">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wider mb-2">Trạng thái</label>
                    <select name="status" class="form-input text-sm w-full">
                        <option value="">Tất cả</option>
                        <option value="pending" @selected(request('status') === 'pending')>Chờ duyệt</option>
                        <option value="approved" @selected(request('status') === 'approved')>Đã duyệt</option>
                        <option value="rejected" @selected(request('status') === 'rejected')>Từ chối</option>
                        <option value="revoked" @selected(request('status') === 'revoked')>Đã thu hồi</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wider mb-2">Từ ngày</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-input text-sm w-full">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wider mb-2">Đến ngày</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-input text-sm w-full">
                </div>
            </div>
            <div class="p-5 border-t border-slate-100 dark:border-slate-800/80 flex items-center gap-3 bg-slate-50/50 dark:bg-slate-950/20 flex-shrink-0">
                <button type="button" onclick="resetFilters()" class="btn-secondary flex-1 py-2.5 px-3 text-xs font-bold">Đặt lại</button>
                <button type="submit" class="btn-primary flex-1 py-2.5 px-3 text-xs font-bold">Áp dụng</button>
            </div>
        </form>
    </aside>
@endpush

@if ($errors->any() && old('_modal'))
@php
    $reopenPenalty = (old('_modal') === 'editPenaltyModal' && old('_edit_id'))
        ? \App\Models\Penalty::with(['members', 'attachments'])->find(old('_edit_id'))
        : null;
    $reopenMembers = $reopenPenalty
        ? $reopenPenalty->members->map(fn($m) => ['employee_id' => $m->employee_id, 'points_deducted' => $m->points_deducted])->values()->toArray()
        : [];
    $reopenAttachments = $reopenPenalty
        ? $reopenPenalty->attachments->map(fn($a) => ['id' => $a->id, 'filename' => $a->filename, 'type' => $a->type, 'url' => $a->url])->values()->toArray()
        : [];
@endphp
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    @if($reopenPenalty)
    openEditPenaltyModal(
        '{{ old('_edit_id') }}',
        '{{ old('violation_id') }}',
        '{{ old('_regulation_id') }}',
        '{{ old('employee_id') }}',
        '{{ old('points_deducted') }}',
        '{{ old('money_deducted') }}',
        {{ Illuminate\Support\Js::from(old('description')) }},
        {!! json_encode(old('members', $reopenMembers)) !!},
        {!! json_encode($reopenAttachments) !!}
    );
    @else
    openModal('{{ old('_modal', 'createPenaltyModal') }}');
    @endif
});
</script>
@endpush
@endif

@push('scripts')
<script>
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
    window.location.href = "{{ route('penalties.index') }}";
}
</script>
@endpush
