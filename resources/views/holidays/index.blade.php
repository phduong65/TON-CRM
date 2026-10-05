@extends('layouts.admin')

@section('title', 'Ngày nghỉ lễ')
@section('page-title', 'Ngày nghỉ lễ')
@section('breadcrumb', 'Chấm công / Ngày nghỉ lễ')

@section('page-subtitle')
    Danh sách ngày nghỉ lễ có lương + thưởng (nếu có), dùng để tính công trong Bảng chấm công
@endsection

@section('page-actions')
    @can('create-holidays')
    <button onclick="openModal('createHolidayModal')" class="btn-primary">
        <i class="bi bi-plus-lg"></i>
        <span>Thêm ngày nghỉ lễ</span>
    </button>
    @endcan
@endsection

@section('content')
    {{-- Filter bar --}}
    <div class="card mb-4">
        <div class="px-4 py-3">
            @php
                $hFilterActive = request()->anyFilled(['search', 'year', 'status']);
            @endphp
            <form action="{{ route('holidays.index') }}" method="GET">
                <div class="flex flex-wrap gap-2 items-end">
                    <div class="relative flex-1 basis-full sm:basis-0 min-w-[180px]">
                        <i class="bi bi-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                        <input type="text" name="search" value="{{ request('search') }}"
                               class="form-input pl-7 h-9 text-sm w-full" placeholder="Tên ngày lễ...">
                    </div>
                    <div class="flex flex-wrap gap-2 items-end flex-1 sm:flex-none">
                        <div class="w-[calc(50%-0.25rem)] sm:w-28">
                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Năm</label>
                            <select name="year" class="form-input h-9 text-sm w-full">
                                <option value="">Tất cả</option>
                                @for($y = now()->year + 1; $y >= now()->year - 3; $y--)
                                    <option value="{{ $y }}" @selected(request('year') == $y)>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="w-[calc(50%-0.25rem)] sm:w-32">
                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Trạng thái</label>
                            <select name="status" class="form-input h-9 text-sm w-full">
                                <option value="">Tất cả</option>
                                <option value="1" @selected(request('status') === '1')>Hoạt động</option>
                                <option value="0" @selected(request('status') === '0')>Ngừng HĐ</option>
                            </select>
                        </div>
                        <button type="submit" class="btn-secondary h-9 px-4 text-sm gap-1.5 shrink-0">
                            <i class="bi bi-funnel text-xs"></i> Lọc
                        </button>
                        @if($hFilterActive)
                        <a href="{{ route('holidays.index') }}" class="btn-secondary h-9 px-3 text-sm inline-flex items-center gap-1 shrink-0">
                            <i class="bi bi-x text-sm"></i>
                        </a>
                        @endif
                        <span class="text-xs text-slate-400 dark:text-slate-500 ml-auto shrink-0 pb-2">{{ $holidays->total() }} kết quả</span>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-container border-0 rounded-none">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th class="table-th">Ngày</th>
                            <th class="table-th">Tên ngày lễ</th>
                            <th class="table-th text-center">Có lương</th>
                            <th class="table-th text-right">Thưởng</th>
                            <th class="table-th text-center">Trạng thái</th>
                            @canany(['edit-holidays', 'delete-holidays'])
                            <th class="table-th text-center">Thao tác</th>
                            @endcanany
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($holidays as $h)
                        <tr class="table-tr-hover">
                            <td class="table-td font-medium">{{ $h->date->format('d/m/Y') }}</td>
                            <td class="table-td text-sm">
                                {{ $h->name }}
                                <div class="mt-0.5">
                                    @if($h->applies_to_all)
                                        <span class="text-[10px] text-slate-400"><i class="bi bi-people mr-0.5"></i>Toàn công ty</span>
                                    @else
                                        <span class="text-[10px] text-pcrm-600 dark:text-pcrm-400"><i class="bi bi-diagram-3 mr-0.5"></i>{{ $h->teams->pluck('name')->join(', ') ?: '—' }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="table-td text-center">
                                @if($h->is_paid)
                                    <span class="badge-success">Có lương</span>
                                @else
                                    <span class="badge-neutral">Không lương</span>
                                @endif
                            </td>
                            <td class="table-td text-right font-semibold">
                                @if($h->bonus_amount > 0)
                                    <span class="text-amber-600 dark:text-amber-400">{{ number_format($h->bonus_amount, 0, ',', '.') }}₫</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="table-td text-center">
                                @if($h->is_active)
                                    <span class="badge-success">Hoạt động</span>
                                @else
                                    <span class="badge-neutral">Ngừng</span>
                                @endif
                            </td>
                            @canany(['edit-holidays', 'delete-holidays'])
                            <td class="table-td text-center">
                                <div class="flex items-center justify-center gap-1">
                                    @can('edit-holidays')
                                    <button onclick='openEditHolidayModal({{ json_encode([
                                        "id"             => $h->id,
                                        "date"           => $h->date->format("Y-m-d"),
                                        "name"           => $h->name,
                                        "is_paid"        => (bool) $h->is_paid,
                                        "bonus_amount"   => (float) $h->bonus_amount,
                                        "is_active"      => (bool) $h->is_active,
                                        "applies_to_all" => (bool) $h->applies_to_all,
                                        "team_ids"       => $h->teams->pluck("id")->values(),
                                    ]) }})'
                                            class="btn-ghost btn-sm text-amber-600 dark:text-amber-400" title="Sửa">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    @endcan
                                    @can('delete-holidays')
                                    <button onclick="openDeleteHolidayModal({{ $h->id }}, '{{ addslashes($h->name) }}')"
                                            class="btn-ghost btn-sm text-red-600 dark:text-red-400" title="Vô hiệu hóa">
                                        <i class="bi bi-slash-circle"></i>
                                    </button>
                                    @endcan
                                </div>
                            </td>
                            @endcanany
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="table-td text-center py-8 text-slate-400">
                                <i class="bi bi-calendar-event text-3xl mb-2 block opacity-40"></i>
                                <p>Chưa có ngày nghỉ lễ nào. Hãy thêm ngày nghỉ lễ đầu tiên!</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($holidays->hasPages())
        <div class="card-footer">
            {{ $holidays->links() }}
        </div>
        @endif
    </div>

@endsection

@push('modals')
    @include('holidays.partials.create-modal')
    @include('holidays.partials.edit-modal')
    @include('holidays.partials.delete-modal')
@endpush

@push('scripts')
<script>
function updateTeamChipUI(cb) {
    const chip = cb.closest('.team-chip');
    if (!chip) return;
    const indicator = chip.querySelector('.team-checkbox-indicator');
    const checkIcon = indicator ? indicator.querySelector('.bi-check') : null;

    if (cb.checked) {
        chip.classList.add('border-pcrm-500', 'bg-pcrm-50/80', 'text-pcrm-900', 'dark:bg-pcrm-900/30', 'dark:border-pcrm-500', 'dark:text-pcrm-200');
        chip.classList.remove('border-slate-200', 'dark:border-slate-700', 'bg-white', 'dark:bg-slate-800/80', 'text-slate-700', 'dark:text-slate-300');
        if (indicator) {
            indicator.classList.add('border-pcrm-600', 'bg-pcrm-600', 'text-white');
            indicator.classList.remove('border-slate-300', 'dark:border-slate-600', 'bg-white', 'dark:bg-slate-700');
        }
        if (checkIcon) checkIcon.classList.remove('hidden');
    } else {
        chip.classList.remove('border-pcrm-500', 'bg-pcrm-50/80', 'text-pcrm-900', 'dark:bg-pcrm-900/30', 'dark:border-pcrm-500', 'dark:text-pcrm-200');
        chip.classList.add('border-slate-200', 'dark:border-slate-700', 'bg-white', 'dark:bg-slate-800/80', 'text-slate-700', 'dark:text-slate-300');
        if (indicator) {
            indicator.classList.remove('border-pcrm-600', 'bg-pcrm-600', 'text-white');
            indicator.classList.add('border-slate-300', 'dark:border-slate-600', 'bg-white', 'dark:bg-slate-700');
        }
        if (checkIcon) checkIcon.classList.add('hidden');
    }
}

function updateHolidayCounts(prefix) {
    const wrap = document.getElementById(prefix + 'HolidayTeamsWrap');
    if (!wrap) return;

    const teamCbs = wrap.querySelectorAll('input.holiday-team-cb');
    let totalSelected = 0;
    teamCbs.forEach(function (cb) {
        if (cb.checked) totalSelected++;
    });

    const counterEl = document.getElementById(prefix + 'SelectedCount');
    if (counterEl) counterEl.textContent = totalSelected;

    const branchCards = wrap.querySelectorAll('[data-branch-card]');
    branchCards.forEach(function (card) {
        const bId = card.dataset.branchCard;
        const bTeams = card.querySelectorAll('input.holiday-team-cb');
        const bChecked = card.querySelectorAll('input.holiday-team-cb:checked').length;

        const badge = document.getElementById(prefix + 'BranchBadge_' + bId);
        if (badge) {
            badge.textContent = bChecked + '/' + bTeams.length;
            if (bChecked > 0) {
                badge.classList.add('bg-pcrm-100', 'text-pcrm-700', 'dark:bg-pcrm-900/40', 'dark:text-pcrm-300');
                badge.classList.remove('bg-slate-100', 'text-slate-600', 'dark:bg-slate-700', 'dark:text-slate-300');
            } else {
                badge.classList.remove('bg-pcrm-100', 'text-pcrm-700', 'dark:bg-pcrm-900/40', 'dark:text-pcrm-300');
                badge.classList.add('bg-slate-100', 'text-slate-600', 'dark:bg-slate-700', 'dark:text-slate-300');
            }
        }

        const masterCb = document.getElementById(prefix + 'BranchMaster_' + bId);
        if (masterCb) {
            masterCb.checked = (bTeams.length > 0 && bChecked === bTeams.length);
            masterCb.indeterminate = (bChecked > 0 && bChecked < bTeams.length);
        }
    });
}

function onHolidayTeamChange(checkbox, prefix) {
    updateTeamChipUI(checkbox);
    updateHolidayCounts(prefix);
}

function toggleHolidayBranch(masterCb, prefix) {
    const branchId = masterCb.dataset.branch;
    const wrap = document.getElementById(prefix + 'HolidayTeamsWrap');
    if (!wrap) return;

    wrap.querySelectorAll('input.holiday-team-cb[data-branch="' + branchId + '"]').forEach(function (cb) {
        cb.checked = masterCb.checked;
        updateTeamChipUI(cb);
    });
    updateHolidayCounts(prefix);
}

function selectHolidayPreset(prefix, type) {
    const wrap = document.getElementById(prefix + 'HolidayTeamsWrap');
    if (!wrap) return;

    wrap.querySelectorAll('input.holiday-team-cb').forEach(function (cb) {
        if (type === 'office') {
            cb.checked = (cb.dataset.office === '1');
        } else if (type === 'all') {
            cb.checked = true;
        } else if (type === 'none') {
            cb.checked = false;
        }
        updateTeamChipUI(cb);
    });
    updateHolidayCounts(prefix);
}

function openEditHolidayModal(data) {
    document.getElementById('editHolidayId').value          = data.id;
    document.getElementById('editHolidayDate').value         = data.date         ?? '';
    document.getElementById('editHolidayName').value         = data.name         ?? '';
    document.getElementById('editHolidayBonus').value        = data.bonus_amount ?? '';
    document.getElementById('editHolidayPaid').checked       = !!data.is_paid;
    document.getElementById('editHolidayActive').checked     = !!data.is_active;
    document.getElementById('editHolidayForm').action        = '/holidays/' + data.id;

    // Scope selection
    const all = !!data.applies_to_all;
    document.getElementById('editHolidayScopeAll').checked   = all;
    document.getElementById('editHolidayScopeTeams').checked = !all;
    const teamIds = (data.team_ids || []).map(String);
    document.querySelectorAll('.edit-holiday-team').forEach(function (cb) {
        cb.checked = teamIds.includes(String(cb.value));
        updateTeamChipUI(cb);
    });
    toggleHolidayScope('edit');
    updateHolidayCounts('edit');

    openModal('editHolidayModal');
}

// Show/hide departments according to scope choice
function toggleHolidayScope(prefix) {
    const all = document.getElementById(prefix + 'HolidayScopeAll').checked;
    const wrap = document.getElementById(prefix + 'HolidayTeamsWrap');
    if (wrap) {
        wrap.classList.toggle('hidden', all);
        if (!all) {
            updateHolidayCounts(prefix);
        }
    }
}

function openDeleteHolidayModal(id, name) {
    document.getElementById('deleteHolidayName').textContent = name;
    document.getElementById('deleteHolidayForm').action = '/holidays/' + id;
    openModal('deleteHolidayModal');
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize create modal scope and chip UI
    document.querySelectorAll('input.holiday-team-cb[data-prefix=""]').forEach(updateTeamChipUI);
    updateHolidayCounts('');

    @if($errors->any() && old('_modal'))
        @if(old('_modal') === 'editHolidayModal')
        openEditHolidayModal({
            id:           '{{ old("_edit_id") }}',
            date:         '{{ old("date") }}',
            name:         '{{ old("name") }}',
            bonus_amount: '{{ old("bonus_amount") }}',
            is_paid:      {{ old('is_paid') ? 'true' : 'false' }},
            is_active:    {{ old('is_active') ? 'true' : 'false' }},
        });
        @else
        openModal('{{ old("_modal") }}');
        @endif
    @endif
});
</script>
@endpush
