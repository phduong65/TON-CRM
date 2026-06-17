@extends('layouts.admin')

@section('title', 'Quy chế xử phạt')
@section('page-title', 'Quy chế')
@section('breadcrumb', 'Kỷ luật / Quy chế')

@section('content')
    <div class="page-header">
        <div>
            <p class="page-subtitle">Danh mục nhóm quy chế — phân loại các lỗi vi phạm</p>
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
            @php $regFilterActive = request()->anyFilled(['search', 'status']); @endphp
            <form action="{{ route('regulations.index') }}" method="GET"
                  class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-end">
                <div class="relative min-w-0 sm:flex-1 sm:max-w-xs">
                    <i class="bi bi-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                           class="form-input pl-7 h-9 text-sm w-full" placeholder="Tên quy chế...">
                </div>
                <div class="grid grid-cols-2 gap-2 sm:contents">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Trạng thái</label>
                        <select name="status" class="form-input h-9 text-sm w-full">
                            <option value="">Tất cả</option>
                            <option value="1" @selected(request('status') === '1')>Hiệu lực</option>
                            <option value="0" @selected(request('status') === '0')>Tạm ngưng</option>
                        </select>
                    </div>
                    <div class="flex gap-2 items-end sm:hidden">
                        <button type="submit" class="btn-primary h-9 px-4 text-sm flex-1 gap-1">
                            <i class="bi bi-funnel text-xs"></i> Lọc
                        </button>
                        @if($regFilterActive)
                        <a href="{{ route('regulations.index') }}" class="btn-secondary h-9 w-9 inline-flex items-center justify-center shrink-0">
                            <i class="bi bi-x text-sm"></i>
                        </a>
                        @endif
                    </div>
                </div>
                <div class="hidden sm:flex items-end gap-2">
                    <button type="submit" class="btn-primary h-9 px-4 text-sm gap-1.5">
                        <i class="bi bi-funnel text-xs"></i> Lọc
                    </button>
                    @if($regFilterActive)
                    <a href="{{ route('regulations.index') }}" class="btn-secondary h-9 px-3 inline-flex items-center gap-1 text-sm">
                        <i class="bi bi-x text-sm"></i>
                    </a>
                    @endif
                    <span class="text-xs text-slate-400 dark:text-slate-500 ml-2">{{ $regulations->total() }} kết quả</span>
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-container border-0 rounded-none">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th class="table-th">Tên quy chế</th>
                            <th class="table-th">Mô tả</th>
                            <th class="table-th text-center">Số lỗi vi phạm</th>
                            <th class="table-th">Hiệu lực từ</th>
                            <th class="table-th text-center">Trạng thái</th>
                            @canany(['update-regulations', 'delete-regulations'])
                            <th class="table-th text-center">Thao tác</th>
                            @endcanany
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($regulations as $reg)
                        <tr class="table-tr-hover">
                            <td class="table-td font-medium">{{ $reg->name }}</td>
                            <td class="table-td text-sm text-slate-500 dark:text-slate-400 max-w-xs truncate">
                                {{ $reg->description ?? '—' }}
                            </td>
                            <td class="table-td text-center">
                                <a href="{{ route('violations.index', ['regulation_id' => $reg->id]) }}"
                                   class="inline-flex items-center gap-1 text-pcrm-600 dark:text-pcrm-400 hover:underline font-semibold">
                                    {{ $reg->violations_count }}
                                    <i class="bi bi-arrow-right text-xs"></i>
                                </a>
                            </td>
                            <td class="table-td text-sm">{{ $reg->effective_date ? $reg->effective_date->format('d/m/Y') : '—' }}</td>
                            <td class="table-td text-center">
                                @if($reg->is_active)
                                    <span class="badge badge-success">Hoạt động</span>
                                @else
                                    <span class="badge badge-neutral">Tạm ngưng</span>
                                @endif
                            </td>
                            @canany(['edit-regulations', 'delete-regulations'])
                            <td class="table-td text-center">
                                <div class="flex items-center justify-center gap-1">
                                    @can('edit-regulations')
                                    <button onclick='openEditRegulationModal({{ json_encode([
                                        "id"             => $reg->id,
                                        "name"           => $reg->name,
                                        "description"    => $reg->description,
                                        "effective_date" => optional($reg->effective_date)->format("Y-m-d"),
                                        "is_active"      => (bool) $reg->is_active,
                                    ]) }})'
                                            class="btn-ghost btn-sm text-amber-600 dark:text-amber-400" title="Sửa">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    @endcan
                                    @can('delete-regulations')
                                    <button onclick="openDeleteRegulationModal({{ $reg->id }}, '{{ addslashes($reg->name) }}')"
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

@endsection

@push('modals')
    @include('regulations.partials.create-modal')
    @include('regulations.partials.edit-modal')
    @include('regulations.partials.delete-modal')
@endpush

@push('scripts')
<script>
function openEditRegulationModal(data) {
    document.getElementById('editRegId').value         = data.id;
    document.getElementById('editRegName').value       = data.name           ?? '';
    document.getElementById('editRegDesc').value       = data.description    ?? '';
    document.getElementById('editRegEffective').value  = data.effective_date ?? '';
    document.getElementById('editRegActive').checked   = !!data.is_active;
    document.getElementById('editRegulationForm').action = '/regulations/' + data.id;
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
        id:             '{{ old("_edit_id") }}',
        name:           '{{ old("name") }}',
        description:    '{{ old("description") }}',
        effective_date: '{{ old("effective_date") }}',
        is_active:      {{ old('is_active') ? 'true' : 'false' }},
    });
    @else
    openModal('{{ old("_modal") }}');
    @endif
});
@endif
</script>
@endpush
