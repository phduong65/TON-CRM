@props([
    'id' => 'exportRangeModal',
    'title' => 'Xuất Excel',
    'exportUrl',
    'refDate' => null,
    'hidden' => [],
])

@php
    $refDateValue = $refDate instanceof \Carbon\Carbon ? $refDate->toDateString() : ($refDate ?: now()->toDateString());
@endphp

<div id="{{ $id }}" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('{{ $id }}')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-file-earmark-excel text-emerald-600"></i> {{ $title }}
            </h3>
            <button onclick="closeModal('{{ $id }}')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form action="{{ $exportUrl }}" method="GET" class="px-4 sm:px-6 py-4 sm:py-5 space-y-4">
            @foreach($hidden as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <input type="hidden" name="ref_date" value="{{ $refDateValue }}">

            <div class="space-y-2">
                <label class="flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-600 text-sm cursor-pointer has-[:checked]:bg-pcrm-50 has-[:checked]:border-pcrm-400 dark:has-[:checked]:bg-pcrm-900/20">
                    <input type="radio" name="range_type" value="week" checked
                           class="text-pcrm-600" onchange="exportRangeToggleCustom('{{ $id }}')">
                    <span class="flex-1">Theo tuần</span>
                    <span class="text-xs text-slate-400">Tuần đang xem</span>
                </label>
                <label class="flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-600 text-sm cursor-pointer has-[:checked]:bg-pcrm-50 has-[:checked]:border-pcrm-400 dark:has-[:checked]:bg-pcrm-900/20">
                    <input type="radio" name="range_type" value="month"
                           class="text-pcrm-600" onchange="exportRangeToggleCustom('{{ $id }}')">
                    <span class="flex-1">Theo tháng</span>
                    <span class="text-xs text-slate-400">Tháng đang xem</span>
                </label>
                <label class="flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-600 text-sm cursor-pointer has-[:checked]:bg-pcrm-50 has-[:checked]:border-pcrm-400 dark:has-[:checked]:bg-pcrm-900/20">
                    <input type="radio" name="range_type" value="custom"
                           class="text-pcrm-600" onchange="exportRangeToggleCustom('{{ $id }}')">
                    <span class="flex-1">Tùy chọn khoảng ngày</span>
                </label>
            </div>

            <div id="{{ $id }}_customRange" class="hidden grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Từ ngày</label>
                    <input type="date" name="date_from" class="form-input">
                </div>
                <div>
                    <label class="form-label">Đến ngày</label>
                    <input type="date" name="date_to" class="form-input">
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('{{ $id }}')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-primary"><i class="bi bi-download"></i> Xuất Excel</button>
            </div>
        </form>
    </div>
</div>

@once
    @push('scripts')
    <script>
    function exportRangeToggleCustom(modalId) {
        const modal = document.getElementById(modalId);
        const custom = document.getElementById(modalId + '_customRange');
        const isCustom = modal.querySelector('input[name=range_type]:checked').value === 'custom';
        custom.classList.toggle('hidden', !isCustom);
    }
    </script>
    @endpush
@endonce
