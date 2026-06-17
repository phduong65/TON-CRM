@extends('layouts.admin')

@section('title', 'Danh sách vi phạm')
@section('page-title', 'Vi phạm')
@section('breadcrumb', 'Kỷ luật / Vi phạm')

@section('content')
    <div class="page-header">
        <div>
            <p class="page-subtitle">Danh sách lỗi vi phạm theo từng quy chế</p>
        </div>
        @can('create-violations')
        <button onclick="openModal('createViolationModal')" class="btn-primary">
            <i class="bi bi-plus-lg"></i>
            <span>Thêm vi phạm</span>
        </button>
        @endcan
    </div>

    <div class="card">
        {{-- Filter bar --}}
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700">
            <form action="{{ route('violations.index') }}" method="GET" class="flex flex-wrap gap-2 items-end">
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Tìm kiếm</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                           class="form-input h-9 text-sm w-44" placeholder="Tên vi phạm...">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Quy chế</label>
                    <select name="regulation_id" class="form-input h-9 text-sm">
                        <option value="">Tất cả quy chế</option>
                        @foreach($regulations as $reg)
                            <option value="{{ $reg->id }}" @selected(request('regulation_id') == $reg->id)>
                                {{ $reg->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Hình thức phạt</label>
                    <select name="penalty_type" class="form-input h-9 text-sm">
                        <option value="">Tất cả</option>
                        <option value="points" @selected(request('penalty_type') === 'points')>Trừ điểm</option>
                        <option value="money"  @selected(request('penalty_type') === 'money')>Phạt tiền</option>
                        <option value="both"   @selected(request('penalty_type') === 'both')>Cả hai</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Mức độ</label>
                    <select name="severity" class="form-input h-9 text-sm">
                        <option value="">Tất cả mức độ</option>
                        <option value="low"      @selected(request('severity') === 'low')>Nhẹ</option>
                        <option value="medium"   @selected(request('severity') === 'medium')>Trung bình</option>
                        <option value="high"     @selected(request('severity') === 'high')>Nặng</option>
                        <option value="critical" @selected(request('severity') === 'critical')>Nghiêm trọng</option>
                        <option value="extreme"  @selected(request('severity') === 'extreme')>Đặc biệt NT</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Trạng thái</label>
                    <select name="status" class="form-input h-9 text-sm">
                        <option value="">Tất cả</option>
                        <option value="1" @selected(request('status') === '1')>Hoạt động</option>
                        <option value="0" @selected(request('status') === '0')>Ngừng hoạt động</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="btn-primary h-9 px-4 text-sm">
                        <i class="bi bi-funnel text-xs"></i> Lọc
                    </button>
                    @if(request()->anyFilled(['search', 'regulation_id', 'penalty_type', 'severity', 'status']))
                    <a href="{{ route('violations.index') }}" class="btn-secondary h-9 px-4 text-sm inline-flex items-center gap-1">
                        <i class="bi bi-x-circle text-xs"></i> Xóa lọc
                    </a>
                    @endif
                </div>
                <div class="ml-auto flex items-end">
                    <p class="text-xs text-slate-400 dark:text-slate-500">{{ $violations->total() }} kết quả</p>
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-container border-0 rounded-none">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th class="table-th">Tên vi phạm</th>
                            <th class="table-th">Quy chế</th>
                            <th class="table-th">Mức độ</th>
                            <th class="table-th">Hình thức phạt</th>
                            <th class="table-th text-right">
                                @php
                                    $currentSort = request('sort', 'points_asc');
                                    $nextSort    = $currentSort === 'points_asc' ? 'points_desc' : 'points_asc';
                                    $sortIcon    = $currentSort === 'points_asc' ? 'bi-sort-numeric-down' : 'bi-sort-numeric-up';
                                @endphp
                                <a href="{{ request()->fullUrlWithQuery(['sort' => $nextSort, 'page' => 1]) }}"
                                   class="inline-flex items-center gap-1 hover:text-pcrm-600 dark:hover:text-pcrm-400 transition-colors"
                                   title="{{ $currentSort === 'points_asc' ? 'Đang: ít → nhiều. Click để đổi' : 'Đang: nhiều → ít. Click để đổi' }}">
                                    Trừ điểm
                                    <i class="bi {{ $sortIcon }} text-pcrm-500"></i>
                                </a>
                            </th>
                            <th class="table-th text-right">Phạt tiền</th>
                            <th class="table-th text-center">Trạng thái</th>
                            @canany(['update-violations', 'delete-violations'])
                            <th class="table-th text-center">Thao tác</th>
                            @endcanany
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($violations as $v)
                        <tr class="table-tr-hover">
                            <td class="table-td font-medium">{{ $v->name }}</td>
                            <td class="table-td text-sm">{{ $v->regulation->name ?? '—' }}</td>
                            <td class="table-td">
                                @php
                                    $sev = $v->severity ?? 'medium';
                                    $sc = ['low' => 'badge-info', 'medium' => 'badge-warning', 'high' => 'badge-danger', 'critical' => 'badge-danger', 'extreme' => 'badge-danger'];
                                    $sl = ['low' => 'Nhẹ', 'medium' => 'Trung bình', 'high' => 'Nặng', 'critical' => 'Nghiêm trọng', 'extreme' => 'Đặc biệt NT'];
                                @endphp
                                <span class="{{ $sc[$sev] ?? 'badge-neutral' }}">{{ $sl[$sev] ?? $sev }}</span>
                            </td>
                            <td class="table-td">
                                @php
                                    $pt = $v->penalty_type ?? 'points';
                                    $ptMap = ['points' => ['badge-info', 'Trừ điểm'], 'money' => ['badge-warning', 'Phạt tiền'], 'both' => ['badge-danger', 'Cả hai']];
                                    [$ptCls, $ptLbl] = $ptMap[$pt] ?? ['badge-neutral', $pt];
                                @endphp
                                <span class="{{ $ptCls }}">{{ $ptLbl }}</span>
                            </td>
                            <td class="table-td text-right font-semibold">
                                @if(in_array($v->penalty_type, ['points', 'both']) && $v->points_deducted > 0)
                                    <span class="text-red-600 dark:text-red-400">-{{ $v->points_deducted }} đ</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="table-td text-right font-semibold">
                                @if(in_array($v->penalty_type, ['money', 'both']) && $v->money_deducted > 0)
                                    <span class="text-amber-600 dark:text-amber-400">{{ number_format($v->money_deducted, 0, ',', '.') }}₫</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="table-td text-center">
                                @if($v->is_active)
                                    <span class="badge-success">Hoạt động</span>
                                @else
                                    <span class="badge-neutral">Ngừng</span>
                                @endif
                            </td>
                            @canany(['edit-violations', 'delete-violations'])
                            <td class="table-td text-center">
                                <div class="flex items-center justify-center gap-1">
                                    @can('edit-violations')
                                    <button onclick='openEditViolationModal({{ json_encode([
                                        "id"             => $v->id,
                                        "name"           => $v->name,
                                        "description"    => $v->description,
                                        "severity"       => $v->severity,
                                        "regulation_id"  => $v->regulation_id,
                                        "penalty_type"   => $v->penalty_type,
                                        "points_deducted"=> $v->points_deducted,
                                        "money_deducted" => (float) $v->money_deducted,
                                        "is_active"      => (bool) $v->is_active,
                                    ]) }})'
                                            class="btn-ghost btn-sm text-amber-600 dark:text-amber-400" title="Sửa">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    @endcan
                                    @can('delete-violations')
                                    <button onclick="openDeleteViolationModal({{ $v->id }}, '{{ addslashes($v->name) }}')"
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
                            <td colspan="8" class="table-td text-center py-8 text-slate-400">
                                <i class="bi bi-exclamation-circle text-3xl mb-2 block opacity-40"></i>
                                <p>Chưa có lỗi vi phạm nào. Hãy thêm vi phạm đầu tiên!</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($violations->hasPages())
        <div class="card-footer">
            {{ $violations->links() }}
        </div>
        @endif
    </div>

@endsection

@push('modals')
    @include('violations.partials.create-modal')
    @include('violations.partials.edit-modal')
    @include('violations.partials.delete-modal')
@endpush

@push('scripts')
<script>
var VIOLATION_SEVERITY_POINTS = { low: 1, medium: 3, high: 5, critical: 10, extreme: 20 };

function setViolationSeverity(severity, prefix) {
    var pts = VIOLATION_SEVERITY_POINTS[severity] || 0;

    // Update hidden input
    var hiddenInput = document.getElementById(prefix + 'ViolationSeverityInput');
    if (hiddenInput) hiddenInput.value = severity;

    // Update points field
    var ptsInput = document.getElementById(prefix + 'ViolationPointsVal');
    if (ptsInput) ptsInput.value = pts;

    // Update button active states
    document.querySelectorAll('.viol-sev-btn-' + prefix).forEach(function (btn) {
        var isActive = btn.dataset.severity === severity;
        btn.classList.toggle('ring-2',        isActive);
        btn.classList.toggle('ring-offset-1', isActive);
        btn.classList.toggle('ring-pcrm-500', isActive);
        btn.classList.toggle('dark:ring-offset-slate-800', isActive);
        btn.classList.toggle('scale-105',     isActive);
        btn.style.fontWeight = isActive ? '700' : '';
    });
}

function toggleViolationPenaltyFields(penaltyType, prefix) {
    var pointsEl = document.getElementById(prefix + 'ViolationPoints');
    var moneyEl  = document.getElementById(prefix + 'ViolationMoney');
    if (!pointsEl || !moneyEl) return;
    pointsEl.classList.toggle('hidden', penaltyType === 'money');
    moneyEl.classList.toggle('hidden', penaltyType === 'points');
}

function openEditViolationModal(data) {
    document.getElementById('editViolationId').value             = data.id;
    document.getElementById('editViolationName').value           = data.name             ?? '';
    document.getElementById('editViolationDesc').value           = data.description      ?? '';
    document.getElementById('editViolationRegulation').value     = data.regulation_id    ?? '';
    document.getElementById('editViolationPenaltyType').value    = data.penalty_type     ?? 'points';
    document.getElementById('editViolationMoneyVal').value       = data.money_deducted   ?? 0;
    document.getElementById('editViolationActive').checked       = !!data.is_active;
    document.getElementById('editViolationForm').action          = '/violations/' + data.id;
    setViolationSeverity(data.severity ?? 'medium', 'edit');
    toggleViolationPenaltyFields(data.penalty_type ?? 'points', 'edit');
    openModal('editViolationModal');
}

function openDeleteViolationModal(id, name) {
    document.getElementById('deleteViolationName').textContent = name;
    document.getElementById('deleteViolationForm').action = '/violations/' + id;
    openModal('deleteViolationModal');
}

@if($errors->any() && old('_modal'))
document.addEventListener('DOMContentLoaded', function() {
    @if(old('_modal') === 'editViolationModal')
    openEditViolationModal({
        id:              '{{ old("_edit_id") }}',
        name:            '{{ old("name") }}',
        description:     '{{ old("description") }}',
        severity:        '{{ old("severity", "medium") }}',
        regulation_id:   '{{ old("regulation_id") }}',
        penalty_type:    '{{ old("penalty_type", "points") }}',
        points_deducted: '{{ old("points_deducted", 0) }}',
        money_deducted:  '{{ old("money_deducted", 0) }}',
        is_active:       {{ old('is_active') ? 'true' : 'false' }},
    });
    @else
    openModal('{{ old("_modal") }}');
    @endif
});
@endif
</script>
@endpush
