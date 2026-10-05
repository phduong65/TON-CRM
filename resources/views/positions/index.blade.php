@extends('layouts.admin')

@section('title', 'Chức danh')
@section('page-title', 'Chức danh')
@section('breadcrumb', 'Nhân sự / Chức danh')

@section('page-subtitle')
    Danh sách chức danh dùng chung khi thêm/sửa nhân viên — chọn từ dropdown thay vì gõ tay để tránh sai lệch chính tả
@endsection

@section('page-actions')
    @can('create-positions')
    <button onclick="openModal('createPositionModal')" class="btn-primary">
        <i class="bi bi-plus-lg"></i>
        <span>Thêm chức danh</span>
    </button>
    @endcan
@endsection

@section('content')
    {{-- Filter bar --}}
    <div class="card mb-4">
        <div class="px-4 py-3">
            @php
                $pFilterActive = request()->anyFilled(['search', 'status']);
            @endphp
            <form action="{{ route('positions.index') }}" method="GET">
                <div class="flex flex-wrap gap-2 items-end">
                    <div class="relative flex-1 basis-full sm:basis-0 min-w-[180px]">
                        <i class="bi bi-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                        <input type="text" name="search" value="{{ request('search') }}"
                               class="form-input pl-7 h-9 text-sm w-full" placeholder="Tên chức danh...">
                    </div>
                    <div class="flex flex-wrap gap-2 items-end flex-1 sm:flex-none">
                        <div class="w-[calc(50%-0.25rem)] sm:w-32">
                            <select name="status" class="form-input h-9 text-sm w-full">
                                <option value="">Tất cả</option>
                                <option value="1" @selected(request('status') === '1')>Hoạt động</option>
                                <option value="0" @selected(request('status') === '0')>Ngừng HĐ</option>
                            </select>
                        </div>
                        <button type="submit" class="btn-secondary h-9 px-4 text-sm gap-1.5 shrink-0">
                            <i class="bi bi-funnel text-xs"></i> Lọc
                        </button>
                        @if($pFilterActive)
                        <a href="{{ route('positions.index') }}" class="btn-secondary h-9 px-3 text-sm inline-flex items-center gap-1 shrink-0">
                            <i class="bi bi-x text-sm"></i>
                        </a>
                        @endif
                        <span class="text-xs text-slate-400 dark:text-slate-500 ml-auto shrink-0 pb-2">{{ $positions->total() }} kết quả</span>
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
                            <th class="table-th">Tên chức danh</th>
                            <th class="table-th text-center">Số nhân viên</th>
                            <th class="table-th text-center">Trạng thái</th>
                            @canany(['edit-positions', 'delete-positions'])
                            <th class="table-th text-center">Thao tác</th>
                            @endcanany
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($positions as $p)
                        <tr class="table-tr-hover">
                            <td class="table-td text-sm font-medium">{{ $p->name }}</td>
                            <td class="table-td text-center text-sm text-slate-500">{{ $p->employees_count }}</td>
                            <td class="table-td text-center">
                                @if($p->is_active)
                                    <span class="badge-success">Hoạt động</span>
                                @else
                                    <span class="badge-neutral">Ngừng</span>
                                @endif
                            </td>
                            @canany(['edit-positions', 'delete-positions'])
                            <td class="table-td text-center">
                                <div class="flex items-center justify-center gap-1">
                                    @can('edit-positions')
                                    <button onclick='openEditPositionModal({{ json_encode([
                                        "id"        => $p->id,
                                        "name"      => $p->name,
                                        "is_active" => (bool) $p->is_active,
                                    ]) }})'
                                            class="btn-ghost btn-sm text-amber-600 dark:text-amber-400" title="Sửa">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    @endcan
                                    @can('delete-positions')
                                    <button onclick="openDeletePositionModal({{ $p->id }}, '{{ addslashes($p->name) }}', {{ $p->employees_count }})"
                                            class="btn-ghost btn-sm text-red-600 dark:text-red-400" title="Xóa">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                    @endcan
                                </div>
                            </td>
                            @endcanany
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="table-td text-center py-8 text-slate-400">
                                <i class="bi bi-person-badge text-3xl mb-2 block opacity-40"></i>
                                <p>Chưa có chức danh nào. Hãy thêm chức danh đầu tiên!</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($positions->hasPages())
        <div class="card-footer">
            {{ $positions->links() }}
        </div>
        @endif
    </div>

@endsection

@push('modals')
    @include('positions.partials.create-modal')
    @include('positions.partials.edit-modal')
    @include('positions.partials.delete-modal')
@endpush

@push('scripts')
<script>
function openEditPositionModal(data) {
    document.getElementById('editPositionId').value       = data.id;
    document.getElementById('editPositionName').value     = data.name      ?? '';
    document.getElementById('editPositionActive').checked = !!data.is_active;
    document.getElementById('editPositionForm').action    = '/positions/' + data.id;
    openModal('editPositionModal');
}

function openDeletePositionModal(id, name, employeesCount) {
    document.getElementById('deletePositionName').textContent = name;
    document.getElementById('deletePositionForm').action = '/positions/' + id;
    document.getElementById('deletePositionWarning').classList.toggle('hidden', employeesCount === 0);
    openModal('deletePositionModal');
}

@if($errors->any() && old('_modal'))
document.addEventListener('DOMContentLoaded', function() {
    @if(old('_modal') === 'editPositionModal')
    openEditPositionModal({
        id:        '{{ old("_edit_id") }}',
        name:      '{{ old("name") }}',
        is_active: {{ old('is_active') ? 'true' : 'false' }},
    });
    @else
    openModal('{{ old("_modal") }}');
    @endif
});
@endif
</script>
@endpush
