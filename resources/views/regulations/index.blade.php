@extends('layouts.admin')

@section('title', 'Quy chế xử phạt')
@section('page-title', 'Quy chế')
@section('breadcrumb', 'Kỷ luật / Quy chế')

@section('content')
    <div class="page-header">
        <div>
            <p class="page-subtitle">Quản lý các quy chế xử phạt — điểm, tiền hoặc cả hai</p>
        </div>
        @can('create-regulations')
        <button onclick="openModal('createRegulationModal')" class="btn-primary">
            <i class="bi bi-plus-lg"></i>
            <span>Thêm quy chế</span>
        </button>
        @endcan
    </div>

    <div class="card">
        {{-- Filter bar --}}
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700">
            <form action="{{ route('regulations.index') }}" method="GET" class="flex flex-wrap gap-2 items-end">
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Tìm kiếm</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                           class="form-input h-9 text-sm w-52" placeholder="Tên, mã quy chế...">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Loại phạt</label>
                    <select name="type" class="form-input h-9 text-sm">
                        <option value="">Tất cả loại</option>
                        <option value="points" @selected(request('type') === 'points')>Điểm</option>
                        <option value="money"  @selected(request('type') === 'money')>Tiền</option>
                        <option value="both"   @selected(request('type') === 'both')>Cả hai</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Trạng thái</label>
                    <select name="status" class="form-input h-9 text-sm">
                        <option value="">Tất cả</option>
                        <option value="1" @selected(request('status') === '1')>Hiệu lực</option>
                        <option value="0" @selected(request('status') === '0')>Tạm ngưng</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="btn-primary h-9 px-4 text-sm">
                        <i class="bi bi-funnel text-xs"></i> Lọc
                    </button>
                    @if(request()->anyFilled(['search', 'type', 'status']))
                    <a href="{{ route('regulations.index') }}" class="btn-secondary h-9 px-4 text-sm inline-flex items-center gap-1">
                        <i class="bi bi-x-circle text-xs"></i> Xóa lọc
                    </a>
                    @endif
                </div>
                <div class="ml-auto flex items-end">
                    <p class="text-xs text-slate-400 dark:text-slate-500">{{ $regulations->total() }} kết quả</p>
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-container border-0 rounded-none">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th class="table-th">Mã</th>
                            <th class="table-th">Tên quy chế</th>
                            <th class="table-th">Loại phạt</th>
                            <th class="table-th text-right">Điểm mặc định</th>
                            <th class="table-th text-right">Tiền mặc định</th>
                            <th class="table-th">Hiệu lực từ</th>
                            <th class="table-th text-center">Trạng thái</th>
                            @canany(['create-regulations', 'update-regulations'])
                            <th class="table-th text-center">Thao tác</th>
                            @endcanany
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($regulations as $reg)
                        <tr class="table-tr-hover">
                            <td class="table-td font-mono text-xs">{{ $reg->code }}</td>
                            <td class="table-td font-medium">{{ $reg->name }}</td>
                            <td class="table-td">
                                @php
                                    $tm = ['points' => ['badge-info', 'Điểm'], 'money' => ['badge-warning', 'Tiền'], 'both' => ['badge-danger', 'Cả hai']];
                                    [$cls, $lbl] = $tm[$reg->type] ?? ['badge-neutral', $reg->type];
                                @endphp
                                <span class="{{ $cls }}">{{ $lbl }}</span>
                            </td>
                            <td class="table-td text-right font-semibold">
                                {{ $reg->default_points ? number_format($reg->default_points) : '—' }}
                            </td>
                            <td class="table-td text-right font-semibold">
                                @if($reg->default_money > 0)
                                    {{ number_format($reg->default_money, 0, ',', '.') }}₫
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="table-td text-sm">{{ $reg->effective_date ? $reg->effective_date->format('d/m/Y') : '—' }}</td>
                            <td class="table-td text-center">
                                @if($reg->is_active)
                                    <span class="badge badge-success">Hoạt động</span>
                                @else
                                    <span class="badge badge-neutral">Tạm ngưng</span>
                                @endif
                            </td>
                            @canany(['create-regulations', 'update-regulations'])
                            <td class="table-td text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <button onclick='openEditRegulationModal({{ json_encode(["id"=>$reg->id,"code"=>$reg->code,"name"=>$reg->name,"description"=>$reg->description,"type"=>$reg->type,"default_points"=>$reg->default_points,"default_money"=>$reg->default_money,"effective_date"=>optional($reg->effective_date)->format("Y-m-d"),"is_active"=>$reg->is_active]) }})'
                                            class="btn-ghost btn-sm text-amber-600 dark:text-amber-400" title="Sửa">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button onclick="openDeleteRegulationModal({{ $reg->id }}, '{{ addslashes($reg->name) }}')"
                                            class="btn-ghost btn-sm text-red-600 dark:text-red-400" title="Vô hiệu hóa">
                                        <i class="bi bi-slash-circle"></i>
                                    </button>
                                </div>
                            </td>
                            @endcanany
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="table-td text-center py-8 text-slate-400">
                                <i class="bi bi-journal-x text-3xl mb-2 block opacity-40"></i>
                                <p>Chưa có quy chế nào. Hãy thêm quy chế đầu tiên!</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($regulations->hasPages())
        <div class="card-footer">
            {{ $regulations->links() }}
        </div>
        @endif
    </div>

    @include('regulations.partials.create-modal')
    @include('regulations.partials.edit-modal')
    @include('regulations.partials.delete-modal')
@endsection

@push('scripts')
<script>
function toggleRegMoneyField(type, prefix) {
    const moneyEl  = document.getElementById(prefix === 'create' ? 'createRegMoney' : 'editRegMoney');
    const pointsEl = document.getElementById(prefix === 'create' ? 'createRegPoints' : 'editRegPoints');
    if (!moneyEl) return;
    if (type === 'points') {
        moneyEl.classList.add('hidden');
        pointsEl.classList.remove('hidden');
    } else if (type === 'money') {
        moneyEl.classList.remove('hidden');
        pointsEl.classList.add('hidden');
    } else {
        moneyEl.classList.remove('hidden');
        pointsEl.classList.remove('hidden');
    }
}

function openEditRegulationModal(data) {
    document.getElementById('editRegId').value            = data.id;
    document.getElementById('editRegCode').value          = data.code             ?? '';
    document.getElementById('editRegName').value          = data.name             ?? '';
    document.getElementById('editRegDesc').value          = data.description      ?? '';
    document.getElementById('editRegType').value          = data.type             ?? '';
    document.getElementById('editRegDefaultPoints').value = data.default_points   ?? 0;
    document.getElementById('editRegDefaultMoney').value  = data.default_money    ?? 0;
    document.getElementById('editRegEffective').value     = data.effective_date   ?? '';
    document.getElementById('editRegActive').checked      = !!data.is_active;
    document.getElementById('editRegulationForm').action  = '/regulations/' + data.id;
    toggleRegMoneyField(data.type, 'edit');
    openModal('editRegulationModal');
}
function openDeleteRegulationModal(id, name) {
    document.getElementById('deleteRegulationName').textContent = name;
    document.getElementById('deleteRegulationForm').action = '/regulations/' + id;
    openModal('deleteRegulationModal');
}

@if($errors->any() && old('_modal'))
document.addEventListener('DOMContentLoaded', function() {
    @if(old('_modal') === 'editRegulationModal')
    openEditRegulationModal({
        id: '{{ old("_edit_id") }}',
        code: '{{ old("code") }}',
        name: '{{ old("name") }}',
        description: '{{ old("description") }}',
        type: '{{ old("type") }}',
        default_points: '{{ old("default_points") }}',
        default_money: '{{ old("default_money") }}',
        effective_date: '{{ old("effective_date") }}',
        is_active: {{ old('is_active') ? 'true' : 'false' }}
    });
    @else
    openModal('{{ old("_modal") }}');
    @endif
});
@endif
</script>
@endpush
