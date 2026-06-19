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
            @php
                $rwExtraKeys    = ['reward_type_id', 'status', 'date_from', 'date_to'];
                $rwFilterActive = request()->anyFilled(array_merge(['search'], $rwExtraKeys));
                $rwExtraCount   = collect($rwExtraKeys)->filter(fn($k) => request($k))->count();
            @endphp
            <form action="{{ route('rewards.index') }}" method="GET">
                <div class="flex gap-2 items-center">
                    <div class="relative flex-1 min-w-0">
                        <i class="bi bi-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                        <input type="text" name="search" value="{{ request('search') }}"
                               class="form-input pl-7 h-9 text-sm w-full" placeholder="Tên NV, mã phiếu...">
                    </div>
                    <button type="button" onclick="toggleEl('filterPanelRewards')"
                            class="sm:hidden relative h-9 w-9 flex items-center justify-center rounded-lg border shrink-0 transition-colors
                                   {{ $rwExtraCount > 0 ? 'border-pcrm-400 bg-pcrm-50 text-pcrm-700 dark:border-pcrm-600 dark:bg-pcrm-900/30 dark:text-pcrm-400' : 'border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-500 dark:text-slate-400' }}">
                        <i class="bi bi-funnel text-sm"></i>
                        @if($rwExtraCount > 0)
                            <span class="absolute -top-1.5 -right-1.5 w-4 h-4 flex items-center justify-center rounded-full bg-pcrm-600 text-white text-[9px] font-bold">{{ $rwExtraCount }}</span>
                        @endif
                    </button>
                    <button type="submit" class="hidden sm:inline-flex btn-primary h-9 px-4 text-sm gap-1.5 shrink-0">
                        <i class="bi bi-funnel text-xs"></i> Lọc
                    </button>
                    @if($rwFilterActive)
                    <a href="{{ route('rewards.index') }}" class="hidden sm:inline-flex btn-secondary h-9 px-3 text-sm items-center gap-1 shrink-0">
                        <i class="bi bi-x text-sm"></i>
                    </a>
                    @endif
                    <span class="hidden sm:block text-xs text-slate-400 dark:text-slate-500 ml-auto shrink-0">{{ $rewards->total() }} kết quả</span>
                </div>
                <div id="filterPanelRewards" class="filter-panel {{ $rwExtraCount > 0 ? 'is-active' : '' }}">
                    <div class="grid grid-cols-2 gap-2 sm:contents">
                        <div>
                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Loại thưởng</label>
                            <select name="reward_type_id" class="form-input h-9 text-sm w-full">
                                <option value="">Tất cả</option>
                                @foreach ($rewardTypes as $rt)
                                    <option value="{{ $rt->id }}" @selected(request('reward_type_id') == $rt->id)>{{ $rt->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Trạng thái</label>
                            <select name="status" class="form-input h-9 text-sm w-full">
                                <option value="">Tất cả</option>
                                        <option value="pending"  @selected(request('status') === 'pending')>Chờ duyệt</option>
                                <option value="approved" @selected(request('status') === 'approved')>Đã duyệt</option>
                                <option value="rejected" @selected(request('status') === 'rejected')>Từ chối</option>
                                <option value="revoked"  @selected(request('status') === 'revoked')>Đã thu hồi</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Từ ngày</label>
                            <input type="date" name="date_from" value="{{ request('date_from') }}"
                                   class="form-input h-9 text-sm w-full">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Đến ngày</label>
                            <input type="date" name="date_to" value="{{ request('date_to') }}"
                                   class="form-input h-9 text-sm w-full">
                        </div>
                    </div>
                    <div class="filter-mobile-actions">
                        <button type="submit" class="btn-primary h-9 px-4 text-sm flex-1 gap-1">
                            <i class="bi bi-funnel text-xs"></i> Áp dụng
                        </button>
                        @if($rwFilterActive)
                        <a href="{{ route('rewards.index') }}" class="btn-secondary h-9 px-3 inline-flex items-center gap-1 text-sm shrink-0">
                            <i class="bi bi-x text-sm"></i> Xóa
                        </a>
                        @endif
                        <span class="ml-auto text-xs text-slate-400 dark:text-slate-500 shrink-0">{{ $rewards->total() }}</span>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Reward Cards --}}
    @if ($rewards->isEmpty())
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
            @foreach ($rewards as $reward)
                @php
                    $borderColor = match ($reward->status) {
                        'pending'  => 'border-l-amber-400 dark:border-l-amber-500',
                        'approved' => 'border-l-emerald-500 dark:border-l-emerald-400',
                        'rejected' => 'border-l-red-500 dark:border-l-red-400',
                        'revoked'  => 'border-l-slate-400 dark:border-l-slate-500',
                        default    => 'border-l-slate-300',
                    };
                    $dotColor = match ($reward->status) {
                        'pending'  => 'bg-amber-400',
                        'approved' => 'bg-emerald-500',
                        'rejected' => 'bg-red-500',
                        'revoked'  => 'bg-slate-400',
                        default    => 'bg-slate-300',
                    };
                    $statusMap = [
                        'pending'  => ['badge-warning', 'Chờ duyệt'],
                        'approved' => ['badge-success', 'Đã duyệt'],
                        'rejected' => ['badge-danger', 'Từ chối'],
                        'revoked'  => ['bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-400 px-1.5 py-0.5 rounded text-xs font-medium', 'Đã thu hồi'],
                    ];
                    [$badgeCls, $badgeLbl] = $statusMap[$reward->status] ?? ['badge-neutral', $reward->status];

                    // Determine display name for target
                    $targetDisplay = match ($reward->target_type ?? 'individual') {
                        'all'    => 'Tất cả nhân viên',
                        'branch' => \App\Models\Branch::find($reward->target_id)?->name ?? 'Chi nhánh',
                        'team'   => \App\Models\Team::find($reward->target_id)?->name ?? 'Đội nhóm',
                        default  => $reward->employee?->name ?? 'N/A',
                    };
                    $targetCode = match ($reward->target_type ?? 'individual') {
                        'all'    => null,
                        'branch' => 'Chi nhánh',
                        'team'   => 'Đội nhóm',
                        default  => $reward->employee?->code,
                    };
                @endphp

                <div class="card border-l-4 {{ $borderColor }} cursor-pointer
                    hover:shadow-md hover:-translate-y-px transition-all duration-150 group"
                    onclick="openRewardDetail({{ $reward->id }})">
                    <div class="px-5 py-4 flex items-start gap-4">

                        {{-- Status dot --}}
                        <div class="shrink-0 mt-1">
                            <div
                                class="w-2.5 h-2.5 rounded-full {{ $dotColor }}
                                @if ($reward->status === 'pending') animate-pulse @endif">
                            </div>
                        </div>

                        {{-- Main content --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                <span class="font-semibold text-slate-900 dark:text-white text-sm">
                                    {{ $targetDisplay }}
                                </span>
                                @if ($targetCode)
                                    <span class="text-xs text-slate-400 dark:text-slate-500 font-mono">
                                        {{ $targetCode }}
                                    </span>
                                @endif
                                <span class="{{ $badgeCls }} text-xs">{{ $badgeLbl }}</span>
                            </div>

                            <p class="text-sm text-slate-600 dark:text-slate-300 mb-0.5">
                                <i class="bi bi-award text-xs text-slate-400 mr-0.5"></i>
                                {{ $reward->rewardType?->name ?? 'N/A' }}
                            </p>

                            @if ($reward->description)
                                <p class="text-xs text-slate-400 dark:text-slate-500 truncate mt-0.5">
                                    {{ $reward->description }}
                                </p>
                            @endif

                            @if ($reward->members->count() > 0)
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
                            @if ($reward->status === 'pending')
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
        </div>
    @endforeach
    </div>

    {{-- Pagination --}}
    @if ($rewards->hasPages())
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

        function rwUpdateTargetUI() {
            const type = document.querySelector('input[name="target_type"]:checked')?.value || 'individual';
            document.getElementById('rw_target_individual').classList.toggle('hidden', type !== 'individual');
            document.getElementById('rw_target_branch').classList.toggle('hidden', type !== 'branch');
            document.getElementById('rw_target_team').classList.toggle('hidden', type !== 'team');
            document.getElementById('rw_target_all').classList.toggle('hidden', type !== 'all');
            document.getElementById('rw_members_section').classList.toggle('hidden', type !== 'individual');

            // Sync target_id hidden field
            const hiddenTargetId = document.getElementById('rw_target_id_hidden');
            if (type === 'branch') {
                hiddenTargetId.value = document.getElementById('rw_branch_select').value;
                document.getElementById('rw_branch_select').onchange = () => { hiddenTargetId.value = document.getElementById('rw_branch_select').value; };
            } else if (type === 'team') {
                hiddenTargetId.value = document.getElementById('rw_team_select').value;
                document.getElementById('rw_team_select').onchange = () => { hiddenTargetId.value = document.getElementById('rw_team_select').value; };
            } else {
                hiddenTargetId.value = '';
            }

            // employee_id not required for non-individual
            const empSelect = document.getElementById('rw_main_employee');
            if (empSelect) empSelect.required = (type === 'individual');
        }

        // Init on page load (handles validation redirect back)
        document.addEventListener('DOMContentLoaded', function() {
            rwUpdateTargetUI();
        });

        function _buildRwEmpOptions() {
            const sel = document.getElementById('rw_main_employee');
            if (!sel) return '';
            return Array.from(sel.options).slice(1).map(o =>
                `<option value="${o.value}" data-branch="${o.dataset.branch||''}" data-team="${o.dataset.team||''}">${o.textContent.trim()}</option>`
            ).join('');
        }

        function rwOnBranchFilter() {
            const branchId = document.getElementById('rw_filter_branch').value;
            const teamSel = document.getElementById('rw_filter_team');
            Array.from(teamSel.options).forEach(opt => {
                if (!opt.value) return;
                const match = !branchId || String(opt.dataset.branch) === String(branchId);
                opt.hidden = !match;
                opt.disabled = !match;
            });
            const cur = teamSel.options[teamSel.selectedIndex];
            if (cur && cur.value && cur.hidden) teamSel.value = '';
            rwFilterEmployees();
        }

        function rwFilterEmployees() {
            const branchId = document.getElementById('rw_filter_branch').value;
            const teamId = document.getElementById('rw_filter_team').value;
            const search = (document.getElementById('rw_emp_search').value || '').toLowerCase().trim();
            const sel = document.getElementById('rw_main_employee');
            Array.from(sel.options).forEach(opt => {
                if (!opt.value) return;
                const show = (!branchId || String(opt.dataset.branch) === String(branchId)) &&
                    (!teamId || String(opt.dataset.team) === String(teamId)) &&
                    (!search || opt.textContent.toLowerCase().includes(search));
                opt.hidden = !show;
                opt.disabled = !show;
            });
            const cur = sel.options[sel.selectedIndex];
            if (cur && cur.value && cur.hidden) sel.value = '';
        }

        function rwFilterMemberSelect(input) {
            const search = (input.value || '').toLowerCase().trim();
            const row = input.closest('.reward-member-row');
            const sel = row ? row.querySelector('select') : null;
            if (!sel) return;
            Array.from(sel.options).forEach(opt => {
                if (!opt.value) return;
                const match = !search || opt.textContent.toLowerCase().includes(search);
                opt.hidden = !match;
                opt.disabled = !match;
            });
            const cur = sel.options[sel.selectedIndex];
            if (cur && cur.value && cur.hidden) sel.value = '';
        }

        function addRewardMemberRow() {
            const idx = rewardMemberIndex++;
            const container = document.getElementById('rewardMembersContainer');
            const row = document.createElement('div');
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

        @if ($errors->any() && old('_modal'))
            document.addEventListener('DOMContentLoaded', function() {
                openModal('{{ old('_modal') }}');
            });
        @endif
    </script>
@endpush
