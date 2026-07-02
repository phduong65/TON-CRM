@extends('layouts.admin')

@section('title', 'Ngày nghỉ lễ')
@section('page-title', 'Ngày nghỉ lễ')
@section('breadcrumb', 'Chấm công / Ngày nghỉ lễ')

@section('content')
    <div class="page-header">
        <div>
            <p class="page-subtitle">Danh sách ngày nghỉ lễ có lương + thưởng (nếu có), dùng để tính công trong Bảng chấm công</p>
        </div>
        @can('create-holidays')
        <button onclick="openModal('createHolidayModal')" class="btn-primary">
            <i class="bi bi-plus-lg"></i>
            <span>Thêm ngày nghỉ lễ</span>
        </button>
        @endcan
    </div>

    {{-- Filter bar --}}
    <div class="card mb-4">
        <div class="px-4 py-3">
            @php
                $hExtraKeys    = ['year', 'status'];
                $hFilterActive = request()->anyFilled(array_merge(['search'], $hExtraKeys));
                $hExtraCount   = collect($hExtraKeys)->filter(fn($k) => request($k))->count();
            @endphp
            <form action="{{ route('holidays.index') }}" method="GET">
                <div class="flex gap-2 items-center">
                    <div class="relative flex-1 min-w-0">
                        <i class="bi bi-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                        <input type="text" name="search" value="{{ request('search') }}"
                               class="form-input pl-7 h-9 text-sm w-full" placeholder="Tên ngày lễ...">
                    </div>
                    <button type="button" onclick="toggleEl('filterPanelHolidays')"
                            class="sm:hidden relative h-9 w-9 flex items-center justify-center rounded-lg border shrink-0 transition-colors
                                   {{ $hExtraCount > 0 ? 'border-pcrm-400 bg-pcrm-50 text-pcrm-700 dark:border-pcrm-600 dark:bg-pcrm-900/30 dark:text-pcrm-400' : 'border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-500 dark:text-slate-400' }}">
                        <i class="bi bi-funnel text-sm"></i>
                        @if($hExtraCount > 0)
                            <span class="absolute -top-1.5 -right-1.5 w-4 h-4 flex items-center justify-center rounded-full bg-pcrm-600 text-white text-[9px] font-bold">{{ $hExtraCount }}</span>
                        @endif
                    </button>
                    <button type="submit" class="hidden sm:inline-flex btn-primary h-9 px-4 text-sm gap-1.5 shrink-0">
                        <i class="bi bi-funnel text-xs"></i> Lọc
                    </button>
                    @if($hFilterActive)
                    <a href="{{ route('holidays.index') }}" class="hidden sm:inline-flex btn-secondary h-9 px-3 text-sm items-center gap-1 shrink-0">
                        <i class="bi bi-x text-sm"></i>
                    </a>
                    @endif
                    <span class="hidden sm:block text-xs text-slate-400 dark:text-slate-500 ml-auto shrink-0">{{ $holidays->total() }} kết quả</span>
                </div>
                <div id="filterPanelHolidays" class="filter-panel {{ $hExtraCount > 0 ? 'is-active' : '' }}">
                    <div class="grid grid-cols-2 gap-2 sm:contents">
                        <div>
                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Năm</label>
                            <select name="year" class="form-input h-9 text-sm w-full">
                                <option value="">Tất cả</option>
                                @for($y = now()->year + 1; $y >= now()->year - 3; $y--)
                                    <option value="{{ $y }}" @selected(request('year') == $y)>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Trạng thái</label>
                            <select name="status" class="form-input h-9 text-sm w-full">
                                <option value="">Tất cả</option>
                                <option value="1" @selected(request('status') === '1')>Hoạt động</option>
                                <option value="0" @selected(request('status') === '0')>Ngừng HĐ</option>
                            </select>
                        </div>
                    </div>
                    <div class="filter-mobile-actions">
                        <button type="submit" class="btn-primary h-9 px-4 text-sm flex-1 gap-1">
                            <i class="bi bi-funnel text-xs"></i> Áp dụng
                        </button>
                        @if($hFilterActive)
                        <a href="{{ route('holidays.index') }}" class="btn-secondary h-9 px-3 inline-flex items-center gap-1 text-sm shrink-0">
                            <i class="bi bi-x text-sm"></i> Xóa
                        </a>
                        @endif
                        <span class="ml-auto text-xs text-slate-400 dark:text-slate-500 shrink-0">{{ $holidays->total() }}</span>
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
                            <td class="table-td text-sm">{{ $h->name }}</td>
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
                                        "id"           => $h->id,
                                        "date"         => $h->date->format("Y-m-d"),
                                        "name"         => $h->name,
                                        "is_paid"      => (bool) $h->is_paid,
                                        "bonus_amount" => (float) $h->bonus_amount,
                                        "is_active"    => (bool) $h->is_active,
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
function openEditHolidayModal(data) {
    document.getElementById('editHolidayId').value          = data.id;
    document.getElementById('editHolidayDate').value         = data.date         ?? '';
    document.getElementById('editHolidayName').value         = data.name         ?? '';
    document.getElementById('editHolidayBonus').value        = data.bonus_amount ?? '';
    document.getElementById('editHolidayPaid').checked       = !!data.is_paid;
    document.getElementById('editHolidayActive').checked     = !!data.is_active;
    document.getElementById('editHolidayForm').action        = '/holidays/' + data.id;
    openModal('editHolidayModal');
}

function openDeleteHolidayModal(id, name) {
    document.getElementById('deleteHolidayName').textContent = name;
    document.getElementById('deleteHolidayForm').action = '/holidays/' + id;
    openModal('deleteHolidayModal');
}

@if($errors->any() && old('_modal'))
document.addEventListener('DOMContentLoaded', function() {
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
});
@endif
</script>
@endpush
