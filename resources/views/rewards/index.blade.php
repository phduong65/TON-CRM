@extends('layouts.admin')

@section('title', 'Thưởng điểm')
@section('page-title', 'Thưởng điểm')
@section('page-subtitle', 'Phiếu thưởng điểm cho cá nhân, đội nhóm hoặc chi nhánh — theo trạng thái duyệt')
@section('breadcrumb', 'Thưởng phạt / Thưởng điểm')

@section('page-actions')
    @can('create-rewards')
        <button onclick="openModal('createRewardModal')" class="btn-primary">
            <i class="bi bi-plus-lg"></i>
            <span>Tạo phiếu thưởng</span>
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
        $activeRewardType = request('reward_type_id') ? $rewardTypes->firstWhere('id', (int) request('reward_type_id')) : null;
        $activeFilters = array_filter([
            'search'         => request('search') ? 'Tìm: "' . request('search') . '"' : null,
            'reward_type_id' => $activeRewardType ? 'Loại: ' . $activeRewardType->name : null,
            'date_from'      => request('date_from') ? 'Từ ' . \Illuminate\Support\Carbon::parse(request('date_from'))->format('d/m/Y') : null,
            'date_to'        => request('date_to') ? 'Đến ' . \Illuminate\Support\Carbon::parse(request('date_to'))->format('d/m/Y') : null,
        ]);
    @endphp

    <div class="card">
        <nav class="status-tabs" aria-label="Lọc theo trạng thái">
            <a href="{{ route('rewards.index', $tabQuery(null)) }}"
               class="status-tab {{ !$currentStatus ? 'is-active' : '' }}" @if (!$currentStatus) aria-current="page" @endif>
                Tất cả <span class="status-tab-count">{{ number_format($allCount) }}</span>
            </a>
            @foreach ($statusMeta as $key => [$label])
                <a href="{{ route('rewards.index', $tabQuery($key)) }}"
                   class="status-tab {{ $currentStatus === $key ? 'is-active' : '' }} {{ $key === 'pending' && ($statusCounts[$key] ?? 0) > 0 ? 'has-attention' : '' }}"
                   @if ($currentStatus === $key) aria-current="page" @endif>
                    {{ $label }} <span class="status-tab-count">{{ number_format($statusCounts[$key] ?? 0) }}</span>
                </a>
            @endforeach
        </nav>

        <x-table-toolbar :paginator="$rewards" label="phiếu">
            <x-slot:info>
                @foreach ($activeFilters as $key => $text)
                    <a href="{{ route('rewards.index', request()->except([$key, 'page'])) }}" class="filter-chip" title="Bỏ bộ lọc này">
                        {{ $text }} <i class="bi bi-x" aria-hidden="true"></i><span class="sr-only">Bỏ bộ lọc</span>
                    </a>
                @endforeach
            </x-slot:info>

            <form action="{{ route('rewards.index') }}" method="GET" class="relative" role="search">
                @foreach (request()->only(['status', 'reward_type_id', 'date_from', 'date_to']) as $k => $v)
                    @if ($v !== null && $v !== '') <input type="hidden" name="{{ $k }}" value="{{ $v }}"> @endif
                @endforeach
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none" aria-hidden="true"></i>
                <input type="search" name="search" value="{{ request('search') }}" class="form-input h-9 w-56 pl-8 text-sm"
                       placeholder="Tên, mã NV hoặc mã phiếu…" aria-label="Tìm phiếu thưởng">
            </form>
            <button type="button" onclick="toggleFilterDrawer(true)" class="btn-secondary relative">
                <i class="bi bi-funnel"></i>
                <span>Bộ lọc</span>
                @if (request()->anyFilled(['reward_type_id', 'date_from', 'date_to']))
                    <span class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-pcrm-600 rounded-full" aria-hidden="true"></span>
                @endif
            </button>
        </x-table-toolbar>

        <div class="card-body p-0">
            <div class="table-container border-0 rounded-none">
                <table class="table-base">
                    <caption class="sr-only">Danh sách phiếu thưởng{{ $currentStatus ? ' — ' . ($statusMeta[$currentStatus][0] ?? $currentStatus) : '' }}</caption>
                    <thead>
                        <tr>
                            <th class="table-th lg:max-xl:hidden" scope="col">Mã phiếu</th>
                            <th class="table-th min-w-[170px] xl:min-w-[190px]" scope="col" data-mcard-title>Người nhận</th>
                            <th class="table-th" scope="col">Loại thưởng</th>
                            <th class="table-th text-right" scope="col">Điểm</th>
                            <th class="table-th lg:max-xl:hidden" scope="col">Thời gian</th>
                            <th class="table-th" scope="col">Trạng thái</th>
                            <th class="table-th text-right" scope="col">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rewards as $reward)
                            @php
                                [$badgeLbl, $badgeCls, $badgeIcon] = $statusMeta[$reward->status] ?? [$reward->status, 'badge-neutral', 'bi-info-circle'];
                                $targetType = $reward->target_type ?? 'individual';
                                [$targetName, $targetMeta, $targetIcon] = match ($targetType) {
                                    'all'    => ['Tất cả nhân viên', 'Thưởng toàn công ty', 'bi-buildings'],
                                    'branch' => [$targetBranchNames[$reward->target_id] ?? 'Chi nhánh', 'Thưởng cả chi nhánh', 'bi-building'],
                                    'team'   => [$targetTeamNames[$reward->target_id] ?? 'Đội nhóm', 'Thưởng cả đội', 'bi-people'],
                                    default  => [
                                        $reward->employee?->name ?? 'N/A',
                                        trim(($reward->employee?->team?->name ?? 'Chưa gán đội') . ($reward->employee?->branch ? ' · ' . $reward->employee->branch->name : '')),
                                        null,
                                    ],
                                };
                                $people = $targetType === 'individual' ? 1 + $reward->members_count : $reward->members_count;
                            @endphp
                            <tr class="table-tr-hover cursor-pointer" onclick="openRewardDetail({{ $reward->id }})">
                                <td class="table-td whitespace-nowrap lg:max-xl:hidden">
                                    <a href="{{ route('rewards.show', $reward) }}" onclick="event.stopPropagation()"
                                       class="font-mono text-xs font-medium text-pcrm-600 dark:text-pcrm-400 hover:underline">{{ $reward->code }}</a>
                                </td>
                                <td class="table-td">
                                    <div class="flex items-center gap-1.5 font-medium text-slate-900 dark:text-white">
                                        @if ($targetIcon) <i class="bi {{ $targetIcon }} text-[#168A63]" aria-hidden="true"></i>
                                        @elseif ($targetType === 'individual' && $reward->employee) <x-employee-avatar :employee="$reward->employee" size="w-6 h-6" text="text-[10px]" /> @endif
                                        {{ $targetName }}
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ $targetMeta }}</div>
                                    @if ($people > 1)
                                        <span class="mt-1 inline-flex items-center gap-1 text-[11px] font-medium text-slate-500 dark:text-slate-400">
                                            <i class="bi bi-people" aria-hidden="true"></i> {{ number_format($people) }} người nhận
                                        </span>
                                    @endif
                                </td>
                                <td class="table-td text-sm">
                                    <div class="max-w-xs lg:max-2xl:max-w-[11rem] truncate text-slate-800 dark:text-slate-200" title="{{ $reward->rewardType?->name }}">{{ $reward->rewardType?->name ?? 'N/A' }}</div>
                                    @if ($reward->description)
                                        <div class="max-w-xs lg:max-2xl:max-w-[11rem] truncate text-xs text-slate-400 dark:text-slate-500" title="{{ $reward->description }}">{{ $reward->description }}</div>
                                    @elseif ($reward->rewardType?->category)
                                        <div class="text-xs text-slate-400 dark:text-slate-500">{{ $reward->rewardType->category->name }}</div>
                                    @endif
                                </td>
                                <td class="table-td text-right whitespace-nowrap">
                                    <span class="points-chip points-chip-plus">+{{ number_format($reward->total_points_awarded) }}</span>
                                    @if ($targetType !== 'individual')
                                        <span class="mt-1 block text-[11px] text-slate-400">mỗi người</span>
                                    @endif
                                </td>
                                <td class="table-td whitespace-nowrap text-xs text-slate-500 dark:text-slate-400 lg:max-xl:hidden">
                                    <span class="block text-slate-700 dark:text-slate-300">{{ $reward->created_at->format('d/m/Y') }}</span>
                                    @if ($reward->status === 'pending')
                                        <span class="text-[#C98219] dark:text-amber-400" title="Thời gian chờ duyệt">chờ {{ $reward->created_at->diffForHumans(null, true) }}</span>
                                    @else
                                        {{ $reward->created_at->format('H:i') }}
                                    @endif
                                </td>
                                <td class="table-td whitespace-nowrap">
                                    <span class="{{ $badgeCls }}"><i class="bi {{ $badgeIcon }}" aria-hidden="true"></i> {{ $badgeLbl }}</span>
                                </td>
                                <td class="table-td text-right whitespace-nowrap">
                                    <div class="inline-flex items-center justify-end gap-0.5" onclick="event.stopPropagation()">
                                        <a href="{{ route('rewards.show', $reward) }}" class="row-action" title="Xem chi tiết" aria-label="Xem chi tiết phiếu {{ $reward->code }}">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @if ($reward->status === 'pending')
                                            @can('delete-rewards')
                                                <button type="button" title="Xoá" aria-label="Xoá phiếu {{ $reward->code }}"
                                                    onclick="openDeleteRewardModal({{ $reward->id }}, {{ Illuminate\Support\Js::from($reward->code) }})"
                                                    class="row-action row-action-danger">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="table-td py-16 text-center text-slate-400 dark:text-slate-500">
                                    <i class="bi bi-gift mb-3 block text-4xl" aria-hidden="true"></i>
                                    @if ($currentStatus || $activeFilters)
                                        <p class="text-sm font-medium text-slate-600 dark:text-slate-300">Không có phiếu thưởng nào khớp bộ lọc</p>
                                        <a href="{{ route('rewards.index') }}" class="mt-3 inline-flex items-center gap-1.5 text-sm text-pcrm-600 dark:text-pcrm-400 hover:underline">
                                            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Xoá bộ lọc
                                        </a>
                                    @else
                                        <p class="text-sm font-medium text-slate-600 dark:text-slate-300">Chưa có phiếu thưởng nào</p>
                                        @can('create-rewards')
                                            <button onclick="openModal('createRewardModal')"
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
        @if ($rewards->hasPages())
            <div class="card-footer">
                {{ $rewards->links() }}
            </div>
        @endif
    </div>

@endsection

@push('modals')
    @include('rewards.partials.detail-modal')
    @include('rewards.partials.create-modal')
    @include('rewards.partials.delete-modal')

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
        <form action="{{ route('rewards.index') }}" method="GET" class="flex-1 flex flex-col overflow-y-auto">
            <div class="p-5 space-y-4 flex-1">
                <div>
                    <label class="block text-xs font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wider mb-2">Tìm kiếm</label>
                    <div class="relative w-full">
                        <i class="bi bi-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                        <input type="text" name="search" value="{{ request('search') }}" class="form-input pl-7 text-sm w-full" placeholder="Tên NV, mã phiếu...">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wider mb-2">Loại thưởng</label>
                    <select name="reward_type_id" class="form-input text-sm w-full">
                        <option value="">Tất cả</option>
                        @foreach ($rewardTypes as $rt)
                            <option value="{{ $rt->id }}" @selected(request('reward_type_id') == $rt->id)>{{ $rt->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wider mb-2">Trạng thái</label>
                    <select name="status" class="form-input text-sm w-full">
                        <option value="">Tất cả</option>
                        <option value="pending"  @selected(request('status') === 'pending')>Chờ duyệt</option>
                        <option value="approved" @selected(request('status') === 'approved')>Đã duyệt</option>
                        <option value="rejected" @selected(request('status') === 'rejected')>Từ chối</option>
                        <option value="revoked"  @selected(request('status') === 'revoked')>Đã thu hồi</option>
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
            window.location.href = "{{ route('rewards.index') }}";
        }
    </script>
@endpush
