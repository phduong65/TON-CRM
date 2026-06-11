@extends('layouts.admin')

@section('title', 'Chi nhánh')

@section('content')
    <div class="page-header">
        <div>
            <p class="page-subtitle">Danh sách tất cả chi nhánh trong hệ thống</p>
        </div>
        @can('create-branches')
        <button onclick="openModal('createBranchModal')" class="btn-primary">
            <i class="bi bi-plus-lg"></i>
            <span>Thêm chi nhánh</span>
        </button>
        @endcan
    </div>

    <div class="card">
        {{-- Filter bar --}}
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700">
            <form action="{{ route('branches.index') }}" method="GET" class="flex flex-wrap gap-2 items-end">
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Tìm kiếm</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                           class="form-input h-9 text-sm w-52" placeholder="Tên, mã chi nhánh...">
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
                    @if(request()->anyFilled(['search', 'status']))
                    <a href="{{ route('branches.index') }}" class="btn-secondary h-9 px-4 text-sm inline-flex items-center gap-1">
                        <i class="bi bi-x-circle text-xs"></i> Xóa lọc
                    </a>
                    @endif
                </div>
                <div class="ml-auto flex items-end">
                    <p class="text-xs text-slate-400 dark:text-slate-500">{{ $branches->total() }} kết quả</p>
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-container border-0 rounded-none">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th class="table-th">Mã CN</th>
                            <th class="table-th">Tên chi nhánh</th>
                            <th class="table-th">Địa chỉ</th>
                            <th class="table-th text-center">Đội nhóm</th>
                            <th class="table-th text-center">Nhân viên</th>
                            <th class="table-th text-center">Trạng thái</th>
                            <th class="table-th text-center">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($branches as $b)
                        <tr class="table-tr-hover">
                            <td class="table-td font-mono text-xs">{{ $b->code ?? '—' }}</td>
                            <td class="table-td font-medium">{{ $b->name }}</td>
                            <td class="table-td text-slate-500 text-sm">{{ $b->address ?? '—' }}</td>
                            <td class="table-td text-center">{{ $b->teams_count ?? 0 }}</td>
                            <td class="table-td text-center">{{ $b->employees_count ?? 0 }}</td>
                            <td class="table-td text-center">
                                @if($b->is_active)
                                    <span class="badge badge-success">Hoạt động</span>
                                @else
                                    <span class="badge badge-neutral">Ngừng</span>
                                @endif
                            </td>
                            <td class="table-td text-center">
                                <div class="flex items-center justify-center gap-1">
                                    @can('edit-branches')
                                    <button onclick='openEditBranchModal({{ json_encode(["id"=>$b->id,"code"=>$b->code,"name"=>$b->name,"phone"=>$b->phone,"address"=>$b->address,"is_active"=>$b->is_active]) }})'
                                            class="btn-ghost btn-sm text-amber-600 dark:text-amber-400" title="Sửa">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    @endcan
                                    @can('delete-branches')
                                    <button onclick="openDeleteBranchModal({{ $b->id }}, '{{ addslashes($b->name) }}')"
                                            class="btn-ghost btn-sm text-red-600 dark:text-red-400" title="Vô hiệu hóa">
                                        <i class="bi bi-slash-circle"></i>
                                    </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="table-td text-center py-8 text-slate-400">
                                <i class="bi bi-buildings text-3xl mb-2 block opacity-40"></i>
                                <p>Chưa có chi nhánh nào</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($branches->hasPages())
        <div class="card-footer">
            {{ $branches->links() }}
        </div>
        @endif
    </div>

@endsection

@push('modals')
    @include('branches.partials.create-modal')
    @include('branches.partials.edit-modal')
    @include('branches.partials.delete-modal')
@endpush

@push('scripts')
<script>
function openEditBranchModal(data) {
    document.getElementById('editBranchCode').value    = data.code    ?? '';
    document.getElementById('editBranchPhone').value   = data.phone   ?? '';
    document.getElementById('editBranchName').value    = data.name    ?? '';
    document.getElementById('editBranchAddress').value = data.address ?? '';
    document.getElementById('editBranchActive').checked = !!data.is_active;
    document.getElementById('editBranchForm').action   = '/branches/' + data.id;
    openModal('editBranchModal');
}
function openDeleteBranchModal(id, name) {
    document.getElementById('deleteBranchName').textContent = name;
    document.getElementById('deleteBranchForm').action = '/branches/' + id;
    openModal('deleteBranchModal');
}

@if($errors->any() && old('_modal'))
document.addEventListener('DOMContentLoaded', function() {
    @if(old('_modal') === 'editBranchModal')
    openEditBranchModal({
        id: '{{ old("_edit_id") }}',
        code: '{{ old("code") }}',
        phone: '{{ old("phone") }}',
        name: '{{ old("name") }}',
        address: '{{ old("address") }}',
        is_active: {{ old("is_active") ? "true" : "false" }}
    });
    @else
    openModal('{{ old("_modal") }}');
    @endif
});
@endif
</script>
@endpush
