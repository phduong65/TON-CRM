@extends('layouts.admin')

@section('title', 'Thưởng điểm')
@section('page-title', 'Thưởng điểm')
@section('breadcrumb', 'Thưởng phạt / Thưởng điểm')

@section('content')
    <div class="page-header">
        <div>
            <p class="page-subtitle">Nhấn vào phiếu để xem chi tiết và thực hiện duyệt</p>
        </div>
        @can('create-rewards')
        <button onclick="openModal('createRewardModal')" class="btn-primary">
            <i class="bi bi-plus-circle"></i>
            <span>Tạo phiếu thưởng</span>
        </button>
        @endcan
    </div>

    {{-- Filter bar --}}
    <div class="card mb-4">
        <div class="px-4 py-3">
            <form action="{{ route('rewards.index') }}" method="GET" class="flex flex-wrap gap-2 items-end">
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Tìm kiếm</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-input h-9 text-sm w-48"
                           placeholder="Tên NV, mã phiếu...">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Loại thưởng</label>
                    <select name="reward_type_id" class="form-input h-9 text-sm">
                        <option value="">Tất cả loại</option>
                        @foreach($rewardTypes as $rt)
                            <option value="{{ $rt->id }}" @selected(request('reward_type_id') == $rt->id)>
                                {{ $rt->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Trạng thái</label>
                    <select name="status" class="form-input h-9 text-sm">
                        <option value="">Tất cả</option>
                        <option value="pending"  @selected(request('status') === 'pending')>Chờ duyệt</option>
                        <option value="approved" @selected(request('status') === 'approved')>Đã duyệt</option>
                        <option value="rejected" @selected(request('status') === 'rejected')>Từ chối</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Từ ngày</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-input h-9 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Đến ngày</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-input h-9 text-sm">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="btn-primary h-9 px-4 text-sm">
                        <i class="bi bi-funnel text-xs"></i> Lọc
                    </button>
                    @if(request()->anyFilled(['search', 'status', 'reward_type_id', 'date_from', 'date_to']))
                    <a href="{{ route('rewards.index') }}" class="btn-secondary h-9 px-4 text-sm inline-flex items-center gap-1">
                        <i class="bi bi-x-circle text-xs"></i> Xóa lọc
                    </a>
                    @endif
                </div>
                <div class="ml-auto flex items-end">
                    <p class="text-xs text-slate-400 dark:text-slate-500">{{ $rewards->total() }} kết quả</p>
                </div>
            </form>
        </div>
    </div>

    {{-- Reward Cards --}}
    @if($rewards->isEmpty())
        <div class="card">
            <div class="py-16 text-center text-slate-400 dark:text-slate-500">
                <i class="bi bi-gift text-4xl mb-3 block"></i>
                <p class="text-sm font-medium">Chưa có phiếu thưởng nào</p>
                @can('create-rewards')
                    <button onclick="openModal('createRewardModal')"
                        class="mt-4 inline-flex items-center gap-1.5 text-sm text-pcrm-600 dark:text-pcrm-400 hover:underline">
                        <i class="bi bi-plus-circle"></i> Tạo phiếu đầu tiên
                    </button>
                @endcan
            </div>
        </div>
    @else
        <div class="space-y-2">
            @foreach($rewards as $reward)
                @php
                    $borderColor = match($reward->status) {
                        'pending'  => 'border-l-amber-400 dark:border-l-amber-500',
                        'approved' => 'border-l-emerald-500 dark:border-l-emerald-400',
                        'rejected' => 'border-l-red-500 dark:border-l-red-400',
                        default    => 'border-l-slate-300',
                    };
                    $dotColor = match($reward->status) {
                        'pending'  => 'bg-amber-400',
                        'approved' => 'bg-emerald-500',
                        'rejected' => 'bg-red-500',
                        default    => 'bg-slate-300',
                    };
                    $statusMap = [
                        'pending'  => ['badge-warning', 'Chờ duyệt'],
                        'approved' => ['badge-success text-[#6ee7b7]', 'Đã duyệt'],
                        'rejected' => ['badge-danger',  'Từ chối'],
                    ];
                    [$badgeCls, $badgeLbl] = $statusMap[$reward->status] ?? ['badge-neutral', $reward->status];
                @endphp

                <div class="card border-l-4 {{ $borderColor }} cursor-pointer
                    hover:shadow-md hover:-translate-y-px transition-all duration-150 group"
                    onclick="openRewardDetail({{ $reward->id }})"
                    <div class="px-5 py-4 flex items-start gap-4">

                        {{-- Status dot --}}
                        <div class="shrink-0 mt-1">
                            <div class="w-2.5 h-2.5 rounded-full {{ $dotColor }}
                                @if($reward->status === 'pending') animate-pulse @endif">
                            </div>
                        </div>

                        {{-- Main content --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                <span class="font-semibold text-slate-900 dark:text-white text-sm">
                                    {{ $reward->employee?->name ?? 'N/A' }}
                                </span>
                                @if($reward->employee?->code)
                                    <span class="text-xs text-slate-400 dark:text-slate-500 font-mono">
                                        {{ $reward->employee->code }}
                                    </span>
                                @endif
                                <span class="{{ $badgeCls }} text-xs">{{ $badgeLbl }}</span>
                            </div>

                            <p class="text-sm text-slate-600 dark:text-slate-300 mb-0.5">
                                <i class="bi bi-award text-xs text-slate-400 mr-0.5"></i>
                                {{ $reward->rewardType?->name ?? 'N/A' }}
                            </p>

                            @if($reward->description)
                                <p class="text-xs text-slate-400 dark:text-slate-500 truncate mt-0.5">
                                    {{ $reward->description }}
                                </p>
                            @endif

                            @if($reward->members->count() > 0)
                                <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">
                                    <i class="bi bi-people text-xs mr-0.5"></i>
                                    +{{ $reward->members->count() }} thành viên đi kèm
                                </p>
                            @endif
                        </div>

                        {{-- Right: points + date + actions --}}
                        <div class="shrink-0 text-right flex flex-col items-end gap-1.5">
                            <span class="text-base font-bold text-emerald-600 dark:text-emerald-400">
                                +{{ number_format($reward->total_points_awarded) }}đ
                            </span>

                            <span class="text-xs text-slate-400 dark:text-slate-500">
                                {{ $reward->created_at->format('d/m/Y') }}
                            </span>

                            {{-- Quick actions (pending only, stop propagation) --}}
                            @if($reward->status === 'pending')
                                <div class="flex items-center gap-1 transition-opacity" onclick="event.stopPropagation()">
                                    @can('delete-rewards')
                                        <button type="button" title="Xóa"
                                            onclick="openDeleteRewardModal({{ $reward->id }}, '{{ $reward->code }}')"
                                            class="w-7 h-7 flex items-center justify-center rounded-md text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                                            <i class="bi bi-trash-fill text-sm"></i>
                                        </button>
                                    @endcan
                                </div>
                            @endif
                        </div>

                    </div>
                </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($rewards->hasPages())
            <div class="mt-4">
                {{ $rewards->links() }}
            </div>
        @endif
    @endif

@endsection

@push('modals')
    @include('rewards.partials.detail-modal')
    @include('rewards.partials.create-modal')
    @include('rewards.partials.delete-modal')
@endpush

@push('scripts')
<script>
const rewardTypeDefaults = @json($rewardTypeDefaults);

let rewardMemberIndex = {{ old('members') ? count(old('members')) : 0 }};

function _buildRwEmpOptions() {
    const sel = document.getElementById('rw_main_employee');
    if (!sel) return '';
    return Array.from(sel.options).slice(1).map(o =>
        `<option value="${o.value}" data-branch="${o.dataset.branch||''}" data-team="${o.dataset.team||''}">${o.textContent.trim()}</option>`
    ).join('');
}

function rwOnBranchFilter() {
    const branchId = document.getElementById('rw_filter_branch').value;
    const teamSel  = document.getElementById('rw_filter_team');
    Array.from(teamSel.options).forEach(opt => {
        if (!opt.value) return;
        const match = !branchId || String(opt.dataset.branch) === String(branchId);
        opt.hidden = !match; opt.disabled = !match;
    });
    const cur = teamSel.options[teamSel.selectedIndex];
    if (cur && cur.value && cur.hidden) teamSel.value = '';
    rwFilterEmployees();
}

function rwFilterEmployees() {
    const branchId = document.getElementById('rw_filter_branch').value;
    const teamId   = document.getElementById('rw_filter_team').value;
    const search   = (document.getElementById('rw_emp_search').value || '').toLowerCase().trim();
    const sel      = document.getElementById('rw_main_employee');
    Array.from(sel.options).forEach(opt => {
        if (!opt.value) return;
        const show = (!branchId || String(opt.dataset.branch) === String(branchId))
                  && (!teamId   || String(opt.dataset.team)   === String(teamId))
                  && (!search   || opt.textContent.toLowerCase().includes(search));
        opt.hidden = !show; opt.disabled = !show;
    });
    const cur = sel.options[sel.selectedIndex];
    if (cur && cur.value && cur.hidden) sel.value = '';
}

function rwFilterMemberSelect(input) {
    const search = (input.value || '').toLowerCase().trim();
    const row    = input.closest('.reward-member-row');
    const sel    = row ? row.querySelector('select') : null;
    if (!sel) return;
    Array.from(sel.options).forEach(opt => {
        if (!opt.value) return;
        const match = !search || opt.textContent.toLowerCase().includes(search);
        opt.hidden = !match; opt.disabled = !match;
    });
    const cur = sel.options[sel.selectedIndex];
    if (cur && cur.value && cur.hidden) sel.value = '';
}

function addRewardMemberRow() {
    const idx  = rewardMemberIndex++;
    const container = document.getElementById('rewardMembersContainer');
    const row  = document.createElement('div');
    row.className = 'reward-member-row rounded-lg border border-slate-200 dark:border-slate-700 p-2 space-y-1.5';
    row.innerHTML = `
        <div class="relative">
            <i class="bi bi-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
            <input type="text" class="form-input pl-7 text-sm py-1.5" placeholder="Tìm nhân viên..."
                   oninput="rwFilterMemberSelect(this)">
        </div>
        <div class="flex items-center gap-2">
            <select name="members[${idx}][employee_id]" class="form-input flex-1 text-sm">
                <option value="">-- Chọn nhân viên --</option>
                ${_buildRwEmpOptions()}
            </select>
            <input type="number" name="members[${idx}][points_awarded]"
                   class="form-input w-24 text-sm" value="10" min="0" placeholder="Điểm">
            <input type="text" name="members[${idx}][note]"
                   class="form-input flex-1 text-sm" placeholder="Ghi chú...">
            <button type="button" onclick="this.closest('.reward-member-row').remove()"
                    class="text-red-400 hover:text-red-600 shrink-0">
                <i class="bi bi-x-circle"></i>
            </button>
        </div>
    `;
    container.appendChild(row);
}

document.getElementById('createRewardTypeId')?.addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    const pts = selected.dataset.points;
    if (pts !== undefined) {
        document.getElementById('createRewardPoints').value = pts;
    }
});

function openDeleteRewardModal(id, code) {
    document.getElementById('deleteRewardCode').textContent = code;
    document.getElementById('deleteRewardForm').action = '/rewards/' + id;
    openModal('deleteRewardModal');
}

@if($errors->any() && old('_modal'))
document.addEventListener('DOMContentLoaded', function() {
    openModal('{{ old("_modal") }}');
});
@endif
</script>
@endpush
