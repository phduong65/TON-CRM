@extends('layouts.admin')

@section('title', 'Xem trước Import Chấm Công')
@section('page-title', 'Xem trước Import')
@section('breadcrumb', 'Kỷ luật / Import Đi Trễ Về Sớm / Xem trước')

@section('content')
<div class="page-header">
    <div>
        <p class="page-subtitle">Kiểm tra kết quả khớp ngưỡng và chọn dòng cần tạo phiếu phạt</p>
    </div>
    <a href="{{ route('attendance-import.index') }}" class="btn-ghost">
        <i class="bi bi-arrow-left"></i> Quay lại
    </a>
</div>

@if ($errors->any())
    <div class="mb-4 p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 flex gap-2 items-start">
        <i class="bi bi-exclamation-triangle-fill text-red-500 mt-0.5 flex-shrink-0"></i>
        <ul class="text-sm text-red-700 dark:text-red-400 space-y-1">
            @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
        </ul>
    </div>
@endif

{{-- Stats bar --}}
@php
    $totalRows    = count($results);
    $matchedRows  = collect($results)->filter(fn($r) => $r['employee'] !== null)->count();
    $noMatchRows  = $totalRows - $matchedRows;
    $lateRows     = collect($results)->filter(fn($r) => $r['matched_late_rule'] !== null)->count();
    $earlyRows    = collect($results)->filter(fn($r) => $r['matched_early_rule'] !== null)->count();
    $noRuleRows   = collect($results)->filter(fn($r) => !$r['matched_late_rule'] && !$r['matched_early_rule'])->count();
    $totalPenalties = collect($results)->sum(fn($r) =>
        ($r['employee'] && $r['matched_late_rule']  ? 1 : 0) +
        ($r['employee'] && $r['matched_early_rule'] ? 1 : 0)
    );
@endphp

<div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-5">
    <div class="card p-4 text-center">
        <p class="text-2xl font-bold text-slate-700 dark:text-slate-200">{{ $totalRows }}</p>
        <p class="text-xs text-slate-400 mt-1">Tổng dòng</p>
    </div>
    <div class="card p-4 text-center">
        <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $matchedRows }}</p>
        <p class="text-xs text-slate-400 mt-1">Khớp nhân viên</p>
    </div>
    <div class="card p-4 text-center">
        <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $lateRows }}</p>
        <p class="text-xs text-slate-400 mt-1">Dòng đi trễ</p>
    </div>
    <div class="card p-4 text-center">
        <p class="text-2xl font-bold text-orange-600 dark:text-orange-400">{{ $earlyRows }}</p>
        <p class="text-xs text-slate-400 mt-1">Dòng về sớm</p>
    </div>
    <div class="card p-4 text-center col-span-2 sm:col-span-1">
        <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ $totalPenalties }}</p>
        <p class="text-xs text-slate-400 mt-1">Phiếu sẽ tạo</p>
    </div>
</div>

@if(count($results) === 0)
    <div class="card p-12 text-center">
        <i class="bi bi-inbox text-4xl text-slate-300 dark:text-slate-600"></i>
        <p class="mt-3 text-slate-500 dark:text-slate-400">Không có dòng nào khớp với ngưỡng phạt đang hoạt động.</p>
        <div class="flex gap-3 justify-center mt-4">
            <a href="{{ route('attendance-import.index') }}" class="btn-ghost">Điều chỉnh ngưỡng</a>
        </div>
    </div>
@else
<form action="{{ route('attendance-import.confirm') }}" method="POST" id="confirmForm">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">

    <div class="card">
        {{-- Toolbar --}}
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-3">
                <label class="flex items-center gap-2 cursor-pointer text-sm text-slate-600 dark:text-slate-300">
                    <input type="checkbox" id="selectAll" class="rounded border-slate-300" onchange="toggleSelectAll(this)" checked>
                    Chọn tất cả
                </label>
                <span id="selectedCount" class="text-xs text-slate-400">({{ $totalPenalties }} đã chọn)</span>
            </div>
            <div class="flex gap-2 flex-wrap">
                <button type="button" onclick="filterRows('all', this)"
                        class="filter-btn active-filter text-xs px-3 py-1.5 rounded-lg border border-indigo-300 bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-400">
                    Tất cả ({{ $totalRows }})
                </button>
                @if($lateRows)
                <button type="button" onclick="filterRows('late', this)"
                        class="filter-btn text-xs px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-600 text-slate-500">
                    Đi trễ ({{ $lateRows }})
                </button>
                @endif
                @if($earlyRows)
                <button type="button" onclick="filterRows('early', this)"
                        class="filter-btn text-xs px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-600 text-slate-500">
                    Về sớm ({{ $earlyRows }})
                </button>
                @endif
                @if($noMatchRows)
                <button type="button" onclick="filterRows('nomatch', this)"
                        class="filter-btn text-xs px-3 py-1.5 rounded-lg border border-red-200 text-red-500">
                    Không khớp NV ({{ $noMatchRows }})
                </button>
                @endif
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-800 text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide border-b border-slate-100 dark:border-slate-700">
                        <th class="px-3 py-3 text-center w-10"></th>
                        <th class="px-3 py-3 text-left">Nhân viên</th>
                        <th class="px-3 py-3 text-left">Ngày / Ca</th>
                        <th class="px-3 py-3 text-center">Phút</th>
                        <th class="px-3 py-3 text-left">Ngưỡng khớp</th>
                        <th class="px-3 py-3 text-left">Vi phạm áp dụng</th>
                        <th class="px-3 py-3 text-center">Điểm</th>
                        <th class="px-3 py-3 text-center">Trạng thái</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 dark:divide-slate-800">
                    @foreach ($results as $i => $row)
                    @php
                        $hasEmployee = $row['employee'] !== null;
                        $rowTypes = [];
                        if ($row['matched_late_rule'])  $rowTypes[] = 'late';
                        if ($row['matched_early_rule']) $rowTypes[] = 'early';
                        if (!$hasEmployee) $rowTypes[] = 'nomatch';
                    @endphp

                    {{-- Late row --}}
                    @if($row['matched_late_rule'])
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 row-item {{ !$hasEmployee ? 'opacity-50' : '' }}"
                        data-types="all late {{ !$hasEmployee ? 'nomatch' : '' }}">
                        <td class="px-3 py-2.5 text-center">
                            @if($hasEmployee)
                                <input type="checkbox" name="selected_rows[]" value="{{ $i }}_late"
                                       class="row-check rounded border-slate-300" checked onchange="updateCount()">
                            @else
                                <span class="text-slate-300 dark:text-slate-700" title="Không tìm thấy NV">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5">
                            <div class="font-medium text-slate-700 dark:text-slate-200">
                                {{ $hasEmployee ? $row['employee']->name : $row['employee_name'] }}
                            </div>
                            <div class="text-xs font-mono text-slate-400">{{ $row['employee_code'] }}</div>
                        </td>
                        <td class="px-3 py-2.5 text-xs text-slate-500 dark:text-slate-400">
                            {{ $row['date'] }}<br>
                            <span class="text-slate-400">{{ $row['shift'] }}</span>
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold
                                         bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400">
                                <i class="bi bi-clock-history text-[10px]"></i>
                                {{ $row['late_minutes'] }}'
                            </span>
                        </td>
                        <td class="px-3 py-2.5">
                            <span class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $row['matched_late_rule']->label ?: $row['matched_late_rule']->getRangeLabel() }}
                            </span>
                        </td>
                        <td class="px-3 py-2.5">
                            <span class="text-xs text-slate-700 dark:text-slate-200">
                                {{ $row['matched_late_rule']->violation->name ?? '—' }}
                            </span>
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            @if($row['matched_late_rule']->violation?->points_deducted)
                                <span class="text-xs font-semibold text-red-500">
                                    −{{ $row['matched_late_rule']->violation->points_deducted }}
                                </span>
                            @else
                                <span class="text-xs text-slate-300">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            @if($hasEmployee)
                                <span class="badge-yellow text-xs">Sẵn sàng</span>
                            @else
                                <span class="badge-red text-xs">Không tìm thấy NV</span>
                            @endif
                        </td>
                    </tr>
                    @endif

                    {{-- Early row --}}
                    @if($row['matched_early_rule'])
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 row-item {{ !$hasEmployee ? 'opacity-50' : '' }}"
                        data-types="all early {{ !$hasEmployee ? 'nomatch' : '' }}">
                        <td class="px-3 py-2.5 text-center">
                            @if($hasEmployee)
                                <input type="checkbox" name="selected_rows[]" value="{{ $i }}_early"
                                       class="row-check rounded border-slate-300" checked onchange="updateCount()">
                            @else
                                <span class="text-slate-300 dark:text-slate-700">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5">
                            <div class="font-medium text-slate-700 dark:text-slate-200">
                                {{ $hasEmployee ? $row['employee']->name : $row['employee_name'] }}
                            </div>
                            <div class="text-xs font-mono text-slate-400">{{ $row['employee_code'] }}</div>
                        </td>
                        <td class="px-3 py-2.5 text-xs text-slate-500 dark:text-slate-400">
                            {{ $row['date'] }}<br>
                            <span class="text-slate-400">{{ $row['shift'] }}</span>
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold
                                         bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-400">
                                <i class="bi bi-box-arrow-right text-[10px]"></i>
                                {{ $row['early_minutes'] }}'
                            </span>
                        </td>
                        <td class="px-3 py-2.5">
                            <span class="text-xs text-slate-500 dark:text-slate-400">
                                {{ $row['matched_early_rule']->label ?: $row['matched_early_rule']->getRangeLabel() }}
                            </span>
                        </td>
                        <td class="px-3 py-2.5">
                            <span class="text-xs text-slate-700 dark:text-slate-200">
                                {{ $row['matched_early_rule']->violation->name ?? '—' }}
                            </span>
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            @if($row['matched_early_rule']->violation?->points_deducted)
                                <span class="text-xs font-semibold text-red-500">
                                    −{{ $row['matched_early_rule']->violation->points_deducted }}
                                </span>
                            @else
                                <span class="text-xs text-slate-300">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            @if($hasEmployee)
                                <span class="badge-yellow text-xs">Sẵn sàng</span>
                            @else
                                <span class="badge-red text-xs">Không tìm thấy NV</span>
                            @endif
                        </td>
                    </tr>
                    @endif

                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Footer --}}
        <div class="px-5 py-4 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between gap-4 flex-wrap bg-slate-50 dark:bg-slate-800/40">
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Đã chọn <strong id="confirmCount" class="text-slate-700 dark:text-slate-200">{{ $totalPenalties }}</strong> phiếu phạt
                — trạng thái <span class="text-yellow-600 font-medium">Chờ duyệt</span>
            </p>
            <div class="flex gap-3">
                <a href="{{ route('attendance-import.index') }}" class="btn-ghost">
                    <i class="bi bi-x-lg"></i> Huỷ
                </a>
                <button type="submit" id="submitBtn" class="btn-primary">
                    <i class="bi bi-check2-circle"></i>
                    <span>Tạo phiếu phạt</span>
                </button>
            </div>
        </div>
    </div>
</form>
@endif

<script>
function filterRows(type, btn) {
    document.querySelectorAll('.row-item').forEach(row => {
        const types = (row.getAttribute('data-types') || '').split(' ');
        row.style.display = (type === 'all' || types.includes(type)) ? '' : 'none';
    });
    document.querySelectorAll('.filter-btn').forEach(b => {
        b.classList.remove('active-filter', 'border-indigo-300', 'bg-indigo-50', 'dark:bg-indigo-900/20', 'text-indigo-700', 'dark:text-indigo-400');
        b.classList.add('border-slate-200', 'dark:border-slate-600', 'text-slate-500');
    });
    btn.classList.add('active-filter', 'border-indigo-300', 'bg-indigo-50', 'dark:bg-indigo-900/20', 'text-indigo-700', 'dark:text-indigo-400');
    btn.classList.remove('border-slate-200', 'text-slate-500');
}

function toggleSelectAll(cb) {
    document.querySelectorAll('.row-check').forEach(c => {
        if (c.closest('tr').style.display !== 'none') c.checked = cb.checked;
    });
    updateCount();
}

function updateCount() {
    const checked = document.querySelectorAll('.row-check:checked').length;
    document.getElementById('selectedCount').textContent = `(${checked} đã chọn)`;
    document.getElementById('confirmCount').textContent = checked;
    const all = [...document.querySelectorAll('.row-check')];
    const allChecked = all.every(c => c.checked);
    document.getElementById('selectAll').indeterminate = !allChecked && all.some(c => c.checked);
    document.getElementById('selectAll').checked = allChecked;
    document.getElementById('submitBtn').disabled = checked === 0;
}

document.addEventListener('DOMContentLoaded', updateCount);
</script>
@endsection
