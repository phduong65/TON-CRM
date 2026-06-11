@extends('layouts.admin')

@section('title', 'Import Đi Trễ / Về Sớm')
@section('page-title', 'Import Chấm Công')
@section('breadcrumb', 'Kỷ luật / Import Đi Trễ Về Sớm')

@section('content')
<div class="page-header">
    <div>
        <p class="page-subtitle">Tải file chấm công để tự động tạo phiếu phạt theo ngưỡng đã cấu hình</p>
    </div>
    <a href="{{ route('penalties.index') }}" class="btn-ghost">
        <i class="bi bi-arrow-left"></i>
        <span>Danh sách phiếu phạt</span>
    </a>
</div>

@if ($errors->any())
    <div class="mb-4 p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
        <div class="flex gap-2 items-start">
            <i class="bi bi-exclamation-triangle-fill text-red-500 mt-0.5 flex-shrink-0"></i>
            <ul class="text-sm text-red-700 dark:text-red-400 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

{{-- ── SECTION 1: Cấu hình ngưỡng phạt ──────────────────────────────────── --}}
<div class="card mb-6">
    <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
        <h2 class="font-semibold text-slate-700 dark:text-slate-200 flex items-center gap-2">
            <i class="bi bi-sliders text-indigo-500"></i>
            Cấu hình ngưỡng phạt
            <span class="text-xs font-normal text-slate-400">(thiết lập một lần, dùng mọi lần import)</span>
        </h2>
        <button onclick="openModal('createRuleModal')" class="btn-primary btn-sm">
            <i class="bi bi-plus-lg"></i> Thêm ngưỡng
        </button>
    </div>

    @if(!$hasRules)
    <div class="px-6 py-10 text-center">
        <i class="bi bi-exclamation-circle text-3xl text-amber-400"></i>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Chưa có ngưỡng phạt nào. Thêm ngưỡng trước khi import.</p>
        <button onclick="openModal('createRuleModal')" class="btn-primary mt-3">
            <i class="bi bi-plus-lg"></i> Thêm ngưỡng đầu tiên
        </button>
    </div>
    @else
    <div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-slate-100 dark:divide-slate-700">

        {{-- Đi trễ --}}
        <div>
            <div class="px-4 py-2.5 bg-amber-50 dark:bg-amber-900/10 border-b border-slate-100 dark:border-slate-700">
                <span class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-400 flex items-center gap-1.5">
                    <i class="bi bi-clock-history"></i> Đi trễ
                </span>
            </div>
            @if($lateRules->isEmpty())
                <p class="px-4 py-4 text-xs text-slate-400 italic">Chưa có ngưỡng đi trễ.</p>
            @else
            <table class="w-full text-sm">
                @foreach($lateRules as $rule)
                <tr class="border-b border-slate-50 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/40 {{ !$rule->is_active ? 'opacity-50' : '' }}">
                    <td class="px-4 py-2.5">
                        <div class="font-medium text-slate-700 dark:text-slate-200">
                            @if($rule->label)
                                {{ $rule->label }}
                            @else
                                {{ $rule->getRangeLabel() }}
                            @endif
                        </div>
                        <div class="text-xs text-slate-400">{{ $rule->getRangeLabel() }}</div>
                    </td>
                    <td class="px-3 py-2.5">
                        <span class="text-xs text-slate-600 dark:text-slate-300">{{ $rule->violation->name ?? '—' }}</span>
                        @if($rule->violation?->points_deducted)
                            <span class="ml-1 text-xs text-red-500 font-medium">−{{ $rule->violation->points_deducted }}đ</span>
                        @endif
                    </td>
                    <td class="px-3 py-2.5 text-right whitespace-nowrap">
                        @if(!$rule->is_active)
                            <span class="badge-slate text-xs mr-1">Tắt</span>
                        @endif
                        <button onclick="openEditRuleModal({{ $rule->id }}, {{ $rule->toJson() }})"
                                class="btn-ghost btn-xs"><i class="bi bi-pencil"></i></button>
                        <form action="{{ route('attendance-import.rules.toggle', $rule) }}" method="POST" class="inline">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn-ghost btn-xs" title="{{ $rule->is_active ? 'Tắt' : 'Bật' }}">
                                <i class="bi bi-{{ $rule->is_active ? 'toggle-on text-green-500' : 'toggle-off text-slate-400' }}"></i>
                            </button>
                        </form>
                        <button onclick="confirmDeleteRule({{ $rule->id }}, '{{ route('attendance-import.rules.destroy', $rule) }}')"
                                class="btn-ghost btn-xs text-red-400"><i class="bi bi-trash3"></i></button>
                    </td>
                </tr>
                @endforeach
            </table>
            @endif
        </div>

        {{-- Về sớm --}}
        <div>
            <div class="px-4 py-2.5 bg-orange-50 dark:bg-orange-900/10 border-b border-slate-100 dark:border-slate-700">
                <span class="text-xs font-semibold uppercase tracking-wide text-orange-700 dark:text-orange-400 flex items-center gap-1.5">
                    <i class="bi bi-box-arrow-right"></i> Về sớm
                </span>
            </div>
            @if($earlyRules->isEmpty())
                <p class="px-4 py-4 text-xs text-slate-400 italic">Chưa có ngưỡng về sớm.</p>
            @else
            <table class="w-full text-sm">
                @foreach($earlyRules as $rule)
                <tr class="border-b border-slate-50 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/40 {{ !$rule->is_active ? 'opacity-50' : '' }}">
                    <td class="px-4 py-2.5">
                        <div class="font-medium text-slate-700 dark:text-slate-200">
                            @if($rule->label)
                                {{ $rule->label }}
                            @else
                                {{ $rule->getRangeLabel() }}
                            @endif
                        </div>
                        <div class="text-xs text-slate-400">{{ $rule->getRangeLabel() }}</div>
                    </td>
                    <td class="px-3 py-2.5">
                        <span class="text-xs text-slate-600 dark:text-slate-300">{{ $rule->violation->name ?? '—' }}</span>
                        @if($rule->violation?->points_deducted)
                            <span class="ml-1 text-xs text-red-500 font-medium">−{{ $rule->violation->points_deducted }}đ</span>
                        @endif
                    </td>
                    <td class="px-3 py-2.5 text-right whitespace-nowrap">
                        @if(!$rule->is_active)
                            <span class="badge-slate text-xs mr-1">Tắt</span>
                        @endif
                        <button onclick="openEditRuleModal({{ $rule->id }}, {{ $rule->toJson() }})"
                                class="btn-ghost btn-xs"><i class="bi bi-pencil"></i></button>
                        <form action="{{ route('attendance-import.rules.toggle', $rule) }}" method="POST" class="inline">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn-ghost btn-xs">
                                <i class="bi bi-{{ $rule->is_active ? 'toggle-on text-green-500' : 'toggle-off text-slate-400' }}"></i>
                            </button>
                        </form>
                        <button onclick="confirmDeleteRule({{ $rule->id }}, '{{ route('attendance-import.rules.destroy', $rule) }}')"
                                class="btn-ghost btn-xs text-red-400"><i class="bi bi-trash3"></i></button>
                    </td>
                </tr>
                @endforeach
            </table>
            @endif
        </div>

    </div>
    @endif

    {{-- Legend --}}
    <div class="px-6 py-3 border-t border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/30">
        <p class="text-xs text-slate-400">
            <i class="bi bi-info-circle mr-1"></i>
            Khi import, mỗi dòng sẽ tự động khớp với ngưỡng có <strong>min_phút ≤ thực tế ≤ max_phút</strong>.
            Nếu nhiều ngưỡng khớp, ngưỡng có min lớn hơn sẽ được ưu tiên (đặc thù hơn).
        </p>
    </div>
</div>

{{-- ── SECTION 2: Upload file ─────────────────────────────────────────────── --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2">
        <div class="card">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700">
                <h2 class="font-semibold text-slate-700 dark:text-slate-200 flex items-center gap-2">
                    <i class="bi bi-cloud-upload text-indigo-500"></i>
                    Tải lên file chấm công
                </h2>
            </div>

            <form action="{{ route('attendance-import.preview') }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-5">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        File Excel / CSV <span class="text-red-500">*</span>
                    </label>
                    <div id="dropZone"
                         class="relative border-2 border-dashed border-slate-300 dark:border-slate-600 rounded-xl p-8 text-center
                                hover:border-indigo-400 dark:hover:border-indigo-500 transition-colors cursor-pointer
                                bg-slate-50 dark:bg-slate-800/50"
                         onclick="document.getElementById('fileInput').click()">
                        <i class="bi bi-file-earmark-spreadsheet text-4xl text-slate-300 dark:text-slate-600"></i>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                            Kéo thả file vào đây hoặc <span class="text-indigo-600 dark:text-indigo-400 font-medium">chọn file</span>
                        </p>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">.xlsx · .xls · .csv — Tối đa 5 MB</p>
                        <div id="fileInfo" class="hidden mt-3 text-sm font-medium text-green-600 dark:text-green-400"></div>
                    </div>
                    <input type="file" id="fileInput" name="file" accept=".xlsx,.xls,.csv" class="hidden"
                           onchange="showFileName(this)">
                </div>

                @if(!$hasRules)
                <div class="p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-sm text-amber-700 dark:text-amber-400 flex gap-2">
                    <i class="bi bi-exclamation-triangle flex-shrink-0 mt-0.5"></i>
                    Chưa có ngưỡng phạt nào. Hãy thêm ngưỡng ở phần trên trước khi import.
                </div>
                @endif

                <div class="flex justify-end">
                    <button type="submit" id="previewBtn" class="btn-primary"
                            {{ !$hasRules ? 'disabled' : '' }}>
                        <i class="bi bi-eye"></i>
                        <span>Xem trước dữ liệu</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Instructions --}}
    <div class="space-y-4">
        <div class="card p-5">
            <h3 class="font-semibold text-slate-700 dark:text-slate-200 mb-3 flex items-center gap-2">
                <i class="bi bi-list-ol text-indigo-500"></i> Quy trình
            </h3>
            <ol class="text-sm text-slate-500 dark:text-slate-400 space-y-2 list-decimal list-inside">
                <li>Cấu hình các <strong>ngưỡng phạt</strong> (một lần)</li>
                <li>Tải file Excel/CSV chấm công</li>
                <li>Xem trước — hệ thống tự khớp ngưỡng</li>
                <li>Chọn dòng và xác nhận tạo phiếu</li>
            </ol>
        </div>

        <div class="card p-5">
            <h3 class="font-semibold text-slate-700 dark:text-slate-200 mb-3 flex items-center gap-2">
                <i class="bi bi-table text-green-500"></i> Cột bắt buộc trong file
            </h3>
            <div class="text-xs space-y-1.5">
                @foreach([
                    ['Mã nhân viên', 'text-red-500', true],
                    ['Tên', 'text-slate-400', false],
                    ['Ngày', 'text-slate-400', false],
                    ['Ca làm', 'text-slate-400', false],
                    ['Số phút đi muộn', 'text-amber-500', false],
                    ['Số phút về sớm', 'text-orange-500', false],
                ] as [$col, $color, $req])
                <div class="flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full {{ $color === 'text-slate-400' ? 'bg-slate-300 dark:bg-slate-600' : 'bg-current ' . $color }} flex-shrink-0"></span>
                    <span class="{{ $color }}">{{ $col }}</span>
                    @if($req)<span class="text-red-500">*</span>@endif
                </div>
                @endforeach
            </div>
        </div>

        <div class="card p-5">
            <h3 class="font-semibold text-slate-700 dark:text-slate-200 mb-2 flex items-center gap-2">
                <i class="bi bi-lightbulb text-yellow-500"></i> Ví dụ cấu hình ngưỡng
            </h3>
            <div class="text-xs text-slate-500 dark:text-slate-400 space-y-1">
                <div class="flex justify-between"><span>1–14 phút</span><span class="text-red-400">−2 điểm</span></div>
                <div class="flex justify-between"><span>15–29 phút</span><span class="text-red-400">−5 điểm</span></div>
                <div class="flex justify-between"><span>30 phút trở lên</span><span class="text-red-400">−10 điểm</span></div>
            </div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════════
     MODALS
════════════════════════════════════════════════════════════════════════════ --}}

{{-- Create Rule Modal --}}
<div id="createRuleModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50 dark:bg-black/70" onclick="closeModal('createRuleModal')"></div>
    <div class="relative bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md z-10">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-700">
            <h3 class="font-semibold text-slate-700 dark:text-slate-200">Thêm ngưỡng phạt</h3>
            <button onclick="closeModal('createRuleModal')" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <form action="{{ route('attendance-import.rules.store') }}" method="POST" class="p-6 space-y-4">
            @csrf
            <input type="hidden" name="_modal" value="createRuleModal">

            <div>
                <label class="form-label">Loại vi phạm <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-2 gap-2 mt-1">
                    <label class="flex items-center gap-2 px-3 py-2 border rounded-lg cursor-pointer
                                  has-[:checked]:border-amber-400 has-[:checked]:bg-amber-50 dark:has-[:checked]:bg-amber-900/20
                                  border-slate-200 dark:border-slate-700 text-sm">
                        <input type="radio" name="type" value="late" class="accent-amber-500" checked>
                        <i class="bi bi-clock-history text-amber-500"></i> Đi trễ
                    </label>
                    <label class="flex items-center gap-2 px-3 py-2 border rounded-lg cursor-pointer
                                  has-[:checked]:border-orange-400 has-[:checked]:bg-orange-50 dark:has-[:checked]:bg-orange-900/20
                                  border-slate-200 dark:border-slate-700 text-sm">
                        <input type="radio" name="type" value="early" class="accent-orange-500">
                        <i class="bi bi-box-arrow-right text-orange-500"></i> Về sớm
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Từ (phút, bao gồm) <span class="text-red-500">*</span></label>
                    <input type="number" name="min_minutes" min="1" value="{{ old('min_minutes', 1) }}"
                           class="form-input" placeholder="VD: 1">
                    <p class="mt-1 text-xs text-slate-400">Tối thiểu bao nhiêu phút</p>
                </div>
                <div>
                    <label class="form-label">Đến (phút, bao gồm)</label>
                    <input type="number" name="max_minutes" min="1" value="{{ old('max_minutes') }}"
                           class="form-input" placeholder="Để trống = ∞">
                    <p class="mt-1 text-xs text-slate-400">Để trống nếu không giới hạn</p>
                </div>
            </div>

            <div>
                <label class="form-label">Vi phạm áp dụng <span class="text-red-500">*</span></label>
                <select name="violation_id" class="form-input">
                    <option value="">Chọn vi phạm...</option>
                    @foreach($violations as $v)
                        <option value="{{ $v->id }}" {{ old('violation_id') == $v->id ? 'selected' : '' }}>
                            {{ $v->name }}
                            @if($v->points_deducted) — −{{ $v->points_deducted }} điểm @endif
                            @if($v->money_deducted > 0) +{{ number_format($v->money_deducted) }}đ @endif
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Nhãn hiển thị (tuỳ chọn)</label>
                <input type="text" name="label" value="{{ old('label') }}"
                       class="form-input" placeholder="VD: Đi trễ dưới 15 phút">
                <p class="mt-1 text-xs text-slate-400">Tên gợi nhớ, không bắt buộc</p>
            </div>

            <div class="flex justify-end gap-3 pt-2">
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
    <div class="absolute inset-0 bg-black/50 dark:bg-black/70" onclick="closeModal('editRuleModal')"></div>
    <div class="relative bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md z-10">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-700">
            <h3 class="font-semibold text-slate-700 dark:text-slate-200">Chỉnh sửa ngưỡng phạt</h3>
            <button onclick="closeModal('editRuleModal')" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <form id="editRuleForm" action="" method="POST" class="p-6 space-y-4">
            @csrf @method('PUT')

            <div>
                <label class="form-label">Loại vi phạm <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-2 gap-2 mt-1">
                    <label id="editTypeLate" class="flex items-center gap-2 px-3 py-2 border rounded-lg cursor-pointer
                                  border-slate-200 dark:border-slate-700 text-sm">
                        <input type="radio" name="type" value="late" id="editTypeLateRadio" class="accent-amber-500">
                        <i class="bi bi-clock-history text-amber-500"></i> Đi trễ
                    </label>
                    <label id="editTypeEarly" class="flex items-center gap-2 px-3 py-2 border rounded-lg cursor-pointer
                                  border-slate-200 dark:border-slate-700 text-sm">
                        <input type="radio" name="type" value="early" id="editTypeEarlyRadio" class="accent-orange-500">
                        <i class="bi bi-box-arrow-right text-orange-500"></i> Về sớm
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Từ (phút, bao gồm) <span class="text-red-500">*</span></label>
                    <input type="number" id="editMinMinutes" name="min_minutes" min="1" class="form-input">
                </div>
                <div>
                    <label class="form-label">Đến (phút, bao gồm)</label>
                    <input type="number" id="editMaxMinutes" name="max_minutes" min="1" class="form-input"
                           placeholder="Để trống = ∞">
                </div>
            </div>

            <div>
                <label class="form-label">Vi phạm áp dụng <span class="text-red-500">*</span></label>
                <select id="editViolationId" name="violation_id" class="form-input">
                    <option value="">Chọn vi phạm...</option>
                    @foreach($violations as $v)
                        <option value="{{ $v->id }}">
                            {{ $v->name }}
                            @if($v->points_deducted) — −{{ $v->points_deducted }} điểm @endif
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Nhãn hiển thị</label>
                <input type="text" id="editLabel" name="label" class="form-input" placeholder="VD: Đi trễ dưới 15 phút">
            </div>

            <div class="flex justify-end gap-3 pt-2">
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
    <div class="absolute inset-0 bg-black/50 dark:bg-black/70" onclick="closeModal('deleteRuleModal')"></div>
    <div class="relative bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-sm z-10 p-6">
        <div class="text-center mb-4">
            <div class="w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center mx-auto mb-3">
                <i class="bi bi-trash3 text-red-500 text-xl"></i>
            </div>
            <h3 class="font-semibold text-slate-700 dark:text-slate-200">Xoá ngưỡng phạt?</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Hành động này không thể hoàn tác.</p>
        </div>
        <form id="deleteRuleForm" action="" method="POST">
            @csrf @method('DELETE')
            <div class="flex gap-3">
                <button type="button" onclick="closeModal('deleteRuleModal')" class="btn-ghost flex-1">Huỷ</button>
                <button type="submit" class="btn-danger flex-1">Xoá</button>
            </div>
        </form>
    </div>
</div>

{{-- Auto-reopen modal on validation error --}}
@if($errors->any() && old('_modal'))
<script>
document.addEventListener('DOMContentLoaded', () => openModal('{{ old('_modal') }}'));
</script>
@endif

<script>
function showFileName(input) {
    const info = document.getElementById('fileInfo');
    const btn  = document.getElementById('previewBtn');
    if (input.files.length > 0) {
        info.textContent = '✓ ' + input.files[0].name;
        info.classList.remove('hidden');
        btn.disabled = false;
    } else {
        info.classList.add('hidden');
        btn.disabled = true;
    }
}

// Drag & drop
const dropZone = document.getElementById('dropZone');
dropZone.addEventListener('dragover', e => {
    e.preventDefault();
    dropZone.classList.add('border-indigo-400', 'dark:border-indigo-500', 'bg-indigo-50');
});
dropZone.addEventListener('dragleave', () => {
    dropZone.classList.remove('border-indigo-400', 'dark:border-indigo-500', 'bg-indigo-50');
});
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('border-indigo-400', 'bg-indigo-50');
    const fileInput = document.getElementById('fileInput');
    if (e.dataTransfer.files.length > 0) {
        const dt = new DataTransfer();
        dt.items.add(e.dataTransfer.files[0]);
        fileInput.files = dt.files;
        showFileName(fileInput);
    }
});

function openEditRuleModal(id, rule) {
    const form = document.getElementById('editRuleForm');
    form.action = '/attendance-import/rules/' + id;
    document.getElementById('editMinMinutes').value = rule.min_minutes;
    document.getElementById('editMaxMinutes').value = rule.max_minutes ?? '';
    document.getElementById('editViolationId').value = rule.violation_id;
    document.getElementById('editLabel').value = rule.label ?? '';
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
