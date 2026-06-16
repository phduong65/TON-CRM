@extends('layouts.admin')

@section('title', 'Import Đi Trễ / Về Sớm')
@section('page-title', 'Import Chấm Công')
@section('breadcrumb', 'Kỷ luật / Import Đi Trễ Về Sớm')

@section('content')

<style>
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(14px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes connFill {
    from { width: 0; opacity: 0; }
    to   { width: 100%; opacity: 1; }
}
.ai1 { animation: fadeUp .4s ease-out both; }
.ai2 { animation: fadeUp .4s .09s ease-out both; }
.ai3 { animation: fadeUp .4s .17s ease-out both; }
.ai4 { animation: fadeUp .4s .25s ease-out both; }
.conn-fill { animation: connFill .6s .4s ease-out both; }

.rule-row { transition: background-color .12s, box-shadow .12s; }
.rule-row-late:hover  { box-shadow: inset 3px 0 0 #f59e0b; }
.rule-row-early:hover { box-shadow: inset 3px 0 0 #f97316; }

#dropZone.drag-over {
    border-color: #6366f1 !important;
    background-color: rgb(238 242 255 / .5) !important;
}
.dark #dropZone.drag-over {
    border-color: #818cf8 !important;
    background-color: rgb(79 70 229 / .1) !important;
}
</style>

{{-- Header --}}
<div class="page-header ai1">
    <div>
        <p class="page-subtitle text-sm">Tải file chấm công để tự động tạo phiếu phạt theo ngưỡng đã cấu hình</p>
    </div>
    <a href="{{ route('penalties.index') }}" class="btn-danger">
        <i class="bi bi-arrow-left"></i>
        <span>Danh sách phiếu phạt</span>
    </a>
</div>


@if ($errors->any())
    <div class="ai2 mb-4 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
        <div class="flex gap-2.5 items-start">
            <i class="bi bi-exclamation-triangle-fill text-red-500 mt-0.5 flex-shrink-0"></i>
            <ul class="text-sm text-red-700 dark:text-red-400 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

{{-- ── SECTION 1: Ngưỡng phạt ──────────────────────────────────────────── --}}
<div class="card mb-5 ai3">
    <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                <i class="bi bi-sliders text-indigo-500 text-sm"></i>
            </div>
            <div>
                <h2 class="font-semibold mb-2 text-slate-700 dark:text-slate-200 leading-none">Ngưỡng phạt tự động</h2>
                @if($hasRules)
                    <p class="text-sm text-slate-400 mt-0.5 leading-none">{{ $lateRules->count() + $earlyRules->count() }} ngưỡng đang hoạt động</p>
                @endif
            </div>
        </div>
        <button onclick="openModal('createRuleModal')" class="btn-primary btn-md">
            <i class="bi bi-plus-lg"></i> Thêm ngưỡng
        </button>
    </div>

    @if(!$hasRules)
    <div class="px-6 py-14 text-center">
        <div class="w-16 h-16 rounded-2xl bg-amber-50 dark:bg-amber-900/20 flex items-center justify-center mx-auto mb-4">
            <i class="bi bi-sliders text-3xl text-amber-400"></i>
        </div>
        <p class="font-semibold text-slate-600 dark:text-slate-300">Chưa có ngưỡng phạt</p>
        <p class="text-sm text-slate-400 dark:text-slate-500 mt-1.5 max-w-xs mx-auto leading-relaxed">
            Thêm ít nhất một ngưỡng để hệ thống tự động phân loại vi phạm khi import.
        </p>
        <button onclick="openModal('createRuleModal')" class="btn-primary mt-5">
            <i class="bi bi-plus-lg"></i> Thêm ngưỡng đầu tiên
        </button>
    </div>
    @else
    <div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-slate-100 dark:divide-slate-700/60">

        {{-- Đi trễ --}}
        <div class="min-w-0">
            <div class="px-5 py-2.5 flex items-center gap-2 border-b border-slate-100 dark:border-slate-700/60 bg-amber-50/40 dark:bg-amber-900/5">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-400 flex-shrink-0"></span>
                <span class="text-xs font-semibold text-amber-700 dark:text-amber-400 uppercase tracking-widest">Đi trễ</span>
                <span class="ml-auto text-xs text-amber-500/60 font-medium tabular-nums">{{ $lateRules->count() }}</span>
            </div>
            @if($lateRules->isEmpty())
                <div class="px-5 py-8 flex flex-col items-center gap-2">
                    <i class="bi bi-clock text-slate-300 dark:text-slate-700 text-2xl"></i>
                    <p class="text-xs text-slate-400 italic">Chưa có ngưỡng đi trễ</p>
                </div>
            @else
            <div class="divide-y divide-slate-50 dark:divide-slate-800/50">
                @foreach($lateRules as $rule)
                <div class="rule-row rule-row-late flex items-center gap-3 px-5 py-3
                            hover:bg-amber-50/30 dark:hover:bg-amber-900/5 group
                            {{ !$rule->is_active ? 'opacity-50' : '' }}">
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-slate-700 dark:text-slate-200 truncate">
                            @if($rule->label) {{ $rule->label }} @else {{ $rule->getRangeLabel() }} @endif
                        </div>
                        @if($rule->label)
                            <div class="text-xs text-slate-400 mt-0.5 truncate">{{ $rule->getRangeLabel() }}</div>
                        @endif
                    </div>
                    <div class="flex-shrink-0 text-right flex gap-2">
                        <div class="text-xs text-slate-500 dark:text-slate-400 max-w-[100px] truncate">{{ $rule->violation->name ?? '—' }}</div>
                        @if($rule->violation?->points_deducted)
                            <span class="text-xs font-bold text-red-500">-{{ $rule->violation->points_deducted }} đ</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-0.5 flex-shrink-0">
                        <button onclick="openEditRuleModal({{ $rule->id }}, {{ $rule->toJson() }})"
                                class="btn-ghost btn-xs " title="Chỉnh sửa">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form action="{{ route('attendance-import.rules.toggle', $rule) }}" method="POST" class="inline">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn-ghost btn-xs" title="{{ $rule->is_active ? 'Tắt' : 'Bật' }}">
                                <i class="bi bi-{{ $rule->is_active ? 'toggle-on text-green-500' : 'toggle-off text-slate-400' }} text-lg leading-none"></i>
                            </button>
                        </form>
                        <button onclick="confirmDeleteRule({{ $rule->id }}, '{{ route('attendance-import.rules.destroy', $rule) }}')"
                                class="btn-ghost btn-xs text-red-400 " title="Xoá">
                            <i class="bi bi-trash3"></i>
                        </button>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Về sớm --}}
        <div class="min-w-0">
            <div class="px-5 py-2.5 flex items-center gap-2 border-b border-slate-100 dark:border-slate-700/60 bg-orange-50/40 dark:bg-orange-900/5">
                <span class="w-1.5 h-1.5 rounded-full bg-orange-400 flex-shrink-0"></span>
                <span class="text-xs font-semibold text-orange-700 dark:text-orange-400 uppercase tracking-widest">Về sớm</span>
                <span class="ml-auto text-xs text-orange-500/60 font-medium tabular-nums">{{ $earlyRules->count() }}</span>
            </div>
            @if($earlyRules->isEmpty())
                <div class="px-5 py-8 flex flex-col items-center gap-2">
                    <i class="bi bi-box-arrow-right text-slate-300 dark:text-slate-700 text-2xl"></i>
                    <p class="text-xs text-slate-400 italic">Chưa có ngưỡng về sớm</p>
                </div>
            @else
            <div class="divide-y divide-slate-50 dark:divide-slate-800/50">
                @foreach($earlyRules as $rule)
                <div class="rule-row rule-row-early flex items-center gap-3 px-5 py-3
                            hover:bg-orange-50/30 dark:hover:bg-orange-900/5 group
                            {{ !$rule->is_active ? 'opacity-50' : '' }}">
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-slate-700 dark:text-slate-200 truncate">
                            @if($rule->label) {{ $rule->label }} @else {{ $rule->getRangeLabel() }} @endif
                        </div>
                        @if($rule->label)
                            <div class="text-xs text-slate-400 mt-0.5 truncate">{{ $rule->getRangeLabel() }}</div>
                        @endif
                    </div>
                    <div class="flex-shrink-0 text-right">
                        <div class="text-xs text-slate-500 dark:text-slate-400 max-w-[100px] truncate">{{ $rule->violation->name ?? '—' }}</div>
                        @if($rule->violation?->points_deducted)
                            <span class="text-xs font-bold text-red-500">-{{ $rule->violation->points_deducted }} đ</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-0.5 flex-shrink-0">
                        <button onclick="openEditRuleModal({{ $rule->id }}, {{ $rule->toJson() }})"
                                class="btn-ghost btn-xs " title="Chỉnh sửa">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form action="{{ route('attendance-import.rules.toggle', $rule) }}" method="POST" class="inline">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn-ghost btn-xs" title="{{ $rule->is_active ? 'Tắt' : 'Bật' }}">
                                <i class="bi bi-{{ $rule->is_active ? 'toggle-on text-green-500' : 'toggle-off text-slate-400' }} text-lg leading-none"></i>
                            </button>
                        </form>
                        <button onclick="confirmDeleteRule({{ $rule->id }}, '{{ route('attendance-import.rules.destroy', $rule) }}')"
                                class="btn-ghost btn-xs text-red-400 " title="Xoá">
                            <i class="bi bi-trash3"></i>
                        </button>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>

    </div>
    @endif

    <div class="px-5 py-2.5 border-t border-slate-100 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/20 flex items-start gap-2">
        <i class="bi bi-info-circle text-slate-400 text-xs mt-0.5 flex-shrink-0"></i>
        <p class="text-xs text-slate-400 leading-relaxed">
            Mỗi dòng khớp ngưỡng có <strong>min ≤ thực tế ≤ max</strong> phút.
            Khi nhiều ngưỡng khớp, ngưỡng đặc thù hơn (min lớn hơn) được ưu tiên.
        </p>
    </div>
</div>

{{-- ── SECTION 2: Upload + Sidebar ─────────────────────────────────────── --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 ai4">

    {{-- Upload form --}}
    <div class="lg:col-span-2">
        <div class="card h-full flex flex-col">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center flex-shrink-0">
                    <i class="bi bi-cloud-upload text-indigo-500 text-sm"></i>
                </div>
                <h2 class="font-semibold text-slate-700 dark:text-slate-200">Tải lên file chấm công</h2>
            </div>

            <form action="{{ route('attendance-import.preview') }}" method="POST"
                  enctype="multipart/form-data" class="p-6 space-y-5 flex-1 flex flex-col">
                @csrf

                @if(!$hasRules)
                <div class="p-3.5 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 flex gap-2.5 items-start">
                    <i class="bi bi-exclamation-triangle text-amber-500 flex-shrink-0 mt-0.5"></i>
                    <p class="text-sm text-amber-700 dark:text-amber-400">Chưa có ngưỡng phạt. Hãy thêm ngưỡng ở phần trên trước khi import.</p>
                </div>
                @endif

                {{-- Drop zone --}}
                <div class="flex-1">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        File Excel / CSV <span class="text-red-500">*</span>
                    </label>
                    <div id="dropZone"
                         class="relative rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-700
                                cursor-pointer transition-all duration-200 min-h-[210px] overflow-hidden
                                hover:border-indigo-400 dark:hover:border-indigo-500
                                hover:bg-indigo-50/30 dark:hover:bg-indigo-900/5"
                         onclick="document.getElementById('fileInput').click()">

                        {{-- Empty state --}}
                        <div id="dzEmpty" class="flex flex-col items-center justify-center gap-4 py-12 px-6 min-h-[210px]">
                            <div class="relative">
                                <div class="w-16 h-16 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-center">
                                    <i class="bi bi-file-earmark-spreadsheet text-2xl text-indigo-400 dark:text-indigo-500"></i>
                                </div>
                                <div class="absolute -bottom-1.5 -right-1.5 w-5 h-5 rounded-full bg-indigo-500 flex items-center justify-center ring-2 ring-white dark:ring-slate-900">
                                    <i class="bi bi-arrow-up text-white" style="font-size:9px;line-height:1"></i>
                                </div>
                            </div>
                            <div class="text-center">
                                <p class="text-sm font-medium text-slate-700 dark:text-slate-300">
                                    Kéo thả file vào đây
                                </p>
                                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">
                                    hoặc <span class="text-indigo-600 dark:text-indigo-400 font-semibold">nhấn để chọn</span>
                                </p>
                            </div>
                            <div class="flex items-center gap-2 flex-wrap justify-center">
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 text-xs font-mono">.xlsx</span>
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 text-xs font-mono">.xls</span>
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 text-xs font-mono">.csv</span>
                                <span class="text-slate-300 dark:text-slate-600 select-none">·</span>
                                <span class="text-xs text-slate-400">tối đa 5 MB</span>
                            </div>
                        </div>

                        {{-- File selected state --}}
                        <div id="dzSelected" class="hidden flex-col items-center justify-center gap-4 py-12 px-6 min-h-[210px]">
                            <div class="relative">
                                <div class="w-16 h-16 rounded-2xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 flex items-center justify-center">
                                    <i class="bi bi-file-earmark-check text-2xl text-emerald-500"></i>
                                </div>
                                <div class="absolute -bottom-1.5 -right-1.5 w-5 h-5 rounded-full bg-emerald-500 flex items-center justify-center ring-2 ring-white dark:ring-slate-900">
                                    <i class="bi bi-check text-white" style="font-size:9px;line-height:1"></i>
                                </div>
                            </div>
                            <div class="text-center">
                                <p id="fileNameDisplay" class="text-sm font-semibold text-slate-800 dark:text-slate-100 max-w-[220px] truncate"></p>
                                <p id="fileSizeDisplay" class="text-xs text-slate-400 dark:text-slate-500 mt-0.5"></p>
                                <p class="text-xs text-indigo-500 dark:text-indigo-400 mt-2">Nhấn để thay đổi file</p>
                            </div>
                        </div>

                    </div>
                    <input type="file" id="fileInput" name="file" accept=".xlsx,.xls,.csv" class="hidden"
                           onchange="showFileName(this)">
                </div>

                <div class="flex justify-end">
                    <button type="submit" id="previewBtn" class="btn-primary" {{ !$hasRules ? 'disabled' : '' }}>
                        <i class="bi bi-eye"></i>
                        Xem trước dữ liệu
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Guide sidebar --}}
    <div>
        <div class="card overflow-hidden">

            {{-- Process timeline --}}
            <div class="px-5 pt-5 pb-4 border-b border-slate-100 dark:border-slate-700">
                <p class="text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-4">Quy trình</p>
                <div class="relative">
                    {{-- Connecting vertical line --}}
                    <div class="absolute left-[13px] top-6 bottom-2 w-px bg-slate-100 dark:bg-slate-800"></div>
                    <ol class="space-y-4 relative">
                        @foreach([
                            ['Cấu hình ngưỡng phạt',  'Thêm ít nhất một ngưỡng',       $hasRules],
                            ['Tải file Excel / CSV',   'Xuất từ phần mềm chấm công',     false],
                            ['Xem trước & chọn dòng', 'Hệ thống tự khớp ngưỡng',        false],
                            ['Xác nhận tạo phiếu',    'Phiếu chờ duyệt theo workflow',  false],
                        ] as $si => [$stitle, $sdesc, $sdone])
                        <li class="flex items-start gap-3">
                            <div class="relative z-10 w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0
                                        {{ $sdone
                                            ? 'bg-indigo-500 text-white shadow-sm shadow-indigo-200 dark:shadow-indigo-900/40'
                                            : 'bg-white dark:bg-slate-800 border-2 border-slate-200 dark:border-slate-700 text-slate-400' }}">
                                @if($sdone) <i class="bi bi-check-lg text-xs"></i>
                                @else {{ $si + 1 }} @endif
                            </div>
                            <div class="pt-0.5">
                                <p class="text-sm font-medium leading-none
                                          {{ $sdone ? 'text-slate-700 dark:text-slate-200' : 'text-slate-500 dark:text-slate-400' }}">
                                    {{ $stitle }}
                                </p>
                                <p class="text-xs text-slate-400 dark:text-slate-500 mt-1 leading-none">{{ $sdesc }}</p>
                            </div>
                        </li>
                        @endforeach
                    </ol>
                </div>
            </div>

            {{-- Required columns --}}
            <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700">
                <p class="text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-3">Cột trong file</p>
                <div class="space-y-2">
                    @foreach([
                        ['Mã nhân viên',  true,  'text-rose-600   bg-rose-50   dark:bg-rose-900/20   border-rose-200   dark:border-rose-800'],
                        ['Tên',           false, 'text-slate-500  bg-slate-100 dark:bg-slate-700     border-slate-200  dark:border-slate-600'],
                        ['Ngày',          false, 'text-slate-500  bg-slate-100 dark:bg-slate-700     border-slate-200  dark:border-slate-600'],
                        ['Ca làm',        false, 'text-slate-500  bg-slate-100 dark:bg-slate-700     border-slate-200  dark:border-slate-600'],
                        ['Phút đi muộn',  false, 'text-amber-700  bg-amber-50  dark:bg-amber-900/20  border-amber-200  dark:border-amber-800'],
                        ['Phút về sớm',   false, 'text-orange-700 bg-orange-50 dark:bg-orange-900/20 border-orange-200 dark:border-orange-800'],
                    ] as [$col, $req, $cstyle])
                    <div class="flex items-center justify-between gap-2">
                        <code class="text-xs px-2 py-0.5 rounded border font-mono {{ $cstyle }}">{{ $col }}</code>
                        <span class="text-xs flex-shrink-0 {{ $req ? 'text-red-500 font-medium' : 'text-slate-400' }}">
                            {{ $req ? 'Bắt buộc' : 'Tùy chọn' }}
                        </span>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Example thresholds
            <div class="px-5 py-4 bg-slate-50/50 dark:bg-slate-800/20">
                <p class="text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-3">Ví dụ ngưỡng</p>
                <div class="space-y-2">
                    @foreach([
                        ['1 - 14 phút',   '2 điểm'],
                        ['15 - 29 phút',  '5 điểm'],
                        ['30+ phút',      '10 điểm'],
                    ] as [$range, $pts])
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ $range }}</span>
                        <span class="text-xs font-semibold text-red-500 px-2 py-0.5 bg-red-50 dark:bg-red-900/20 rounded-full">-{{ $pts }}</span>
                    </div>
                    @endforeach
                </div>
            </div> --}}

        </div>
    </div>

</div>

{{-- ══════════════════════════════════════════════════════════════════════
     MODALS
═══════════════════════════════════════════════════════════════════════ --}}

{{-- Create Rule Modal --}}
<div id="createRuleModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50 dark:bg-black/70 backdrop-blur-sm" onclick="closeModal('createRuleModal')"></div>
    <div class="relative bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md z-10">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-700">
            <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center">
                    <i class="bi bi-plus-lg text-indigo-500 text-sm"></i>
                </div>
                <h3 class="font-semibold text-slate-700 dark:text-slate-200">Thêm ngưỡng phạt</h3>
            </div>
            <button onclick="closeModal('createRuleModal')"
                    class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400
                           hover:text-slate-600 dark:hover:text-slate-200
                           hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form action="{{ route('attendance-import.rules.store') }}" method="POST" class="p-6 space-y-4">
            @csrf
            <input type="hidden" name="_modal" value="createRuleModal">

            <div>
                <label class="form-label">Loại vi phạm <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-2 gap-2 mt-1">
                    <label class="flex items-center gap-2 px-3 py-2.5 border rounded-xl cursor-pointer transition-colors
                                  has-[:checked]:border-amber-400 has-[:checked]:bg-amber-50 dark:has-[:checked]:bg-amber-900/20
                                  border-slate-200 dark:border-slate-700 text-sm">
                        <input type="radio" name="type" value="late" class="accent-amber-500" checked>
                        <i class="bi bi-clock-history text-amber-500"></i>
                        <span>Đi trễ</span>
                    </label>
                    <label class="flex items-center gap-2 px-3 py-2.5 border rounded-xl cursor-pointer transition-colors
                                  has-[:checked]:border-orange-400 has-[:checked]:bg-orange-50 dark:has-[:checked]:bg-orange-900/20
                                  border-slate-200 dark:border-slate-700 text-sm">
                        <input type="radio" name="type" value="early" class="accent-orange-500">
                        <i class="bi bi-box-arrow-right text-orange-500"></i>
                        <span>Về sớm</span>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Từ <span class="text-slate-400 font-normal">(phút, bao gồm)</span> <span class="text-red-500">*</span></label>
                    <input type="number" name="min_minutes" min="1" value="{{ old('min_minutes', 1) }}"
                           class="form-input" placeholder="VD: 1">
                    <p class="mt-1 text-xs text-slate-400">Tối thiểu bao nhiêu phút</p>
                </div>
                <div>
                    <label class="form-label">Đến <span class="text-slate-400 font-normal">(phút, bao gồm)</span></label>
                    <input type="number" name="max_minutes" min="1" value="{{ old('max_minutes') }}"
                           class="form-input" placeholder="Trống = không giới hạn">
                </div>
            </div>

            <div>
                <label class="form-label">Vi phạm áp dụng <span class="text-red-500">*</span></label>
                <select name="violation_id" class="form-input">
                    <option value="">Chọn vi phạm...</option>
                    @foreach($violations as $v)
                        <option value="{{ $v->id }}" {{ old('violation_id') == $v->id ? 'selected' : '' }}>
                            {{ $v->name }}
                            @if($v->points_deducted) (-{{ $v->points_deducted }} điểm) @endif
                            @if($v->money_deducted > 0) +{{ number_format($v->money_deducted) }}đ @endif
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Nhãn hiển thị <span class="text-slate-400 font-normal">(tuỳ chọn)</span></label>
                <input type="text" name="label" value="{{ old('label') }}"
                       class="form-input" placeholder="VD: Đi trễ dưới 15 phút">
            </div>

            <div class="flex justify-end gap-3 pt-2 border-t border-slate-100 dark:border-slate-700">
                <button type="button" onclick="closeModal('createRuleModal')" class="btn-ghost">Huỷ</button>
                <button type="submit" class="btn-primary">
                    <i class="bi bi-plus-lg"></i> Thêm ngưỡng
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Rule Modal --}}
<div id="editRuleModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50 dark:bg-black/70 backdrop-blur-sm" onclick="closeModal('editRuleModal')"></div>
    <div class="relative bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md z-10">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-700">
            <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center">
                    <i class="bi bi-pencil text-indigo-500 text-sm"></i>
                </div>
                <h3 class="font-semibold text-slate-700 dark:text-slate-200">Chỉnh sửa ngưỡng</h3>
            </div>
            <button onclick="closeModal('editRuleModal')"
                    class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400
                           hover:text-slate-600 dark:hover:text-slate-200
                           hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form id="editRuleForm" action="" method="POST" class="p-6 space-y-4">
            @csrf @method('PUT')

            <div>
                <label class="form-label">Loại vi phạm <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-2 gap-2 mt-1">
                    <label id="editTypeLate" class="flex items-center gap-2 px-3 py-2.5 border rounded-xl cursor-pointer transition-colors
                                  has-[:checked]:border-amber-400 has-[:checked]:bg-amber-50 dark:has-[:checked]:bg-amber-900/20
                                  border-slate-200 dark:border-slate-700 text-sm">
                        <input type="radio" name="type" value="late" id="editTypeLateRadio" class="accent-amber-500">
                        <i class="bi bi-clock-history text-amber-500"></i>
                        <span>Đi trễ</span>
                    </label>
                    <label id="editTypeEarly" class="flex items-center gap-2 px-3 py-2.5 border rounded-xl cursor-pointer transition-colors
                                  has-[:checked]:border-orange-400 has-[:checked]:bg-orange-50 dark:has-[:checked]:bg-orange-900/20
                                  border-slate-200 dark:border-slate-700 text-sm">
                        <input type="radio" name="type" value="early" id="editTypeEarlyRadio" class="accent-orange-500">
                        <i class="bi bi-box-arrow-right text-orange-500"></i>
                        <span>Về sớm</span>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Từ <span class="text-slate-400 font-normal">(phút)</span> <span class="text-red-500">*</span></label>
                    <input type="number" id="editMinMinutes" name="min_minutes" min="1" class="form-input">
                </div>
                <div>
                    <label class="form-label">Đến <span class="text-slate-400 font-normal">(phút)</span></label>
                    <input type="number" id="editMaxMinutes" name="max_minutes" min="1" class="form-input"
                           placeholder="Trống = không giới hạn">
                </div>
            </div>

            <div>
                <label class="form-label">Vi phạm áp dụng <span class="text-red-500">*</span></label>
                <select id="editViolationId" name="violation_id" class="form-input">
                    <option value="">Chọn vi phạm...</option>
                    @foreach($violations as $v)
                        <option value="{{ $v->id }}">
                            {{ $v->name }}
                            @if($v->points_deducted) (-{{ $v->points_deducted }} điểm) @endif
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Nhãn hiển thị <span class="text-slate-400 font-normal">(tuỳ chọn)</span></label>
                <input type="text" id="editLabel" name="label" class="form-input" placeholder="VD: Đi trễ dưới 15 phút">
            </div>

            <div class="flex justify-end gap-3 pt-2 border-t border-slate-100 dark:border-slate-700">
                <button type="button" onclick="closeModal('editRuleModal')" class="btn-ghost">Huỷ</button>
                <button type="submit" class="btn-primary">
                    <i class="bi bi-check-lg"></i> Lưu thay đổi
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Delete confirm modal --}}
<div id="deleteRuleModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50 dark:bg-black/70 backdrop-blur-sm" onclick="closeModal('deleteRuleModal')"></div>
    <div class="relative bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-sm z-10 p-6 text-center">
        <div class="w-14 h-14 rounded-2xl bg-red-50 dark:bg-red-900/20 flex items-center justify-center mx-auto mb-4">
            <i class="bi bi-trash3 text-red-500 text-2xl"></i>
        </div>
        <h3 class="font-semibold text-slate-700 dark:text-slate-200">Xoá ngưỡng phạt?</h3>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1.5 mb-5">Hành động này không thể hoàn tác.</p>
        <form id="deleteRuleForm" action="" method="POST">
            @csrf @method('DELETE')
            <div class="flex gap-3">
                <button type="button" onclick="closeModal('deleteRuleModal')" class="btn-ghost flex-1">Huỷ</button>
                <button type="submit" class="btn-danger flex-1">Xoá</button>
            </div>
        </form>
    </div>
</div>

@if($errors->any() && old('_modal'))
<script>
document.addEventListener('DOMContentLoaded', () => openModal('{{ old('_modal') }}'));
</script>
@endif

<script>
function showFileName(input) {
    const empty    = document.getElementById('dzEmpty');
    const selected = document.getElementById('dzSelected');
    const nameEl   = document.getElementById('fileNameDisplay');
    const sizeEl   = document.getElementById('fileSizeDisplay');
    const btn      = document.getElementById('previewBtn');

    if (input.files.length > 0) {
        const file = input.files[0];
        nameEl.textContent = file.name;
        const s = file.size;
        sizeEl.textContent = s < 1024 ? s + ' B'
                           : s < 1048576 ? Math.round(s / 1024) + ' KB'
                           : (s / 1048576).toFixed(1) + ' MB';
        empty.classList.add('hidden');
        selected.classList.remove('hidden');
        selected.classList.add('flex');
        btn.disabled = false;
    } else {
        empty.classList.remove('hidden');
        selected.classList.add('hidden');
        selected.classList.remove('flex');
        btn.disabled = true;
    }
}

const dropZone = document.getElementById('dropZone');
dropZone.addEventListener('dragover', e => {
    e.preventDefault();
    dropZone.classList.add('drag-over');
});
['dragleave', 'dragend'].forEach(evt =>
    dropZone.addEventListener(evt, () => dropZone.classList.remove('drag-over'))
);
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('drag-over');
    const fileInput = document.getElementById('fileInput');
    if (e.dataTransfer.files.length > 0) {
        const dt = new DataTransfer();
        dt.items.add(e.dataTransfer.files[0]);
        fileInput.files = dt.files;
        showFileName(fileInput);
    }
});

function openEditRuleModal(id, rule) {
    document.getElementById('editRuleForm').action = '/attendance-import/rules/' + id;
    document.getElementById('editMinMinutes').value  = rule.min_minutes;
    document.getElementById('editMaxMinutes').value  = rule.max_minutes ?? '';
    document.getElementById('editViolationId').value = rule.violation_id;
    document.getElementById('editLabel').value       = rule.label ?? '';
    document.getElementById('editTypeLateRadio').checked  = rule.type === 'late';
    document.getElementById('editTypeEarlyRadio').checked = rule.type === 'early';
    openModal('editRuleModal');
}

function confirmDeleteRule(id, url) {
    document.getElementById('deleteRuleForm').action = url;
    openModal('deleteRuleModal');
}
</script>
@endsection
