@extends('layouts.admin')

@section('title', 'Vai trò & Quyền hạn')
@section('page-title', 'Vai trò & Quyền hạn')
@section('breadcrumb', 'Quản trị')

@section('content')
<div class="space-y-5">

    <div class="page-header">
        <div>
            <p class="page-subtitle">Quản lý các nhóm quyền và chức năng được phép</p>
        </div>
        <button onclick="openModal('createRoleModal')" class="btn-primary">
            <i class="bi bi-shield-plus text-sm"></i>
            <span>Thêm vai trò</span>
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @php
            $roleColors = [
                'admin'       => ['bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800', 'text-red-600 dark:text-red-400', 'bg-red-100 dark:bg-red-900/40', 'Quản trị viên'],
                'manager'     => ['bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800', 'text-blue-600 dark:text-blue-400', 'bg-blue-100 dark:bg-blue-900/40', 'Quản lý'],
                'team_leader' => ['bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800', 'text-yellow-600 dark:text-yellow-400', 'bg-yellow-100 dark:bg-yellow-900/40', 'Trưởng nhóm'],
                'staff'       => ['bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700', 'text-slate-500 dark:text-slate-400', 'bg-slate-100 dark:bg-slate-700', 'Nhân viên'],
            ];
        @endphp

        @forelse($roles as $role)
            @php
                $meta = $roleColors[$role->name] ?? ['bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700', 'text-slate-500 dark:text-slate-400', 'bg-slate-100 dark:bg-slate-700', $role->name];
                $rolePermsJson = $role->permissions->pluck('name')->toJson();
            @endphp
            <div class="card border-2 {{ $meta[0] }}">
                <div class="card-body">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl {{ $meta[2] }} flex items-center justify-center shrink-0">
                                <i class="bi bi-shield-check {{ $meta[1] }} text-lg"></i>
                            </div>
                            <div>
                                <p class="font-bold text-slate-900 dark:text-white">{{ $meta[3] }}</p>
                                <p class="text-xs text-slate-400 font-mono">{{ $role->name }}</p>
                            </div>
                        </div>
                        @if($role->name !== 'admin')
                        <div class="flex items-center gap-1">
                            <button onclick='openEditRoleModal({{ json_encode(["id"=>$role->id,"name"=>$role->name,"permissions"=>$role->permissions->pluck("name")]) }})'
                                    class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-white dark:hover:bg-slate-700 transition-colors"
                                    title="Sửa">
                                <i class="bi bi-pencil text-xs"></i>
                            </button>
                            <button onclick="confirmDeleteRole({{ $role->id }}, '{{ $meta[3] }}', {{ $role->users_count }})"
                                    class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                                    title="Xóa">
                                <i class="bi bi-trash text-xs"></i>
                            </button>
                        </div>
                        @else
                        <span class="text-[10px] px-2 py-0.5 rounded-full bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400 font-semibold">Bảo vệ</span>
                        @endif
                    </div>

                    <div class="flex items-center gap-4 text-xs text-slate-500 dark:text-slate-400 mt-2 mb-3">
                        <span class="flex items-center gap-1">
                            <i class="bi bi-key"></i>
                            <strong class="text-slate-700 dark:text-slate-300">{{ $role->permissions_count }}</strong> quyền
                        </span>
                        <span class="flex items-center gap-1">
                            <i class="bi bi-people"></i>
                            <strong class="text-slate-700 dark:text-slate-300">{{ $role->users_count }}</strong> người dùng
                        </span>
                    </div>

                    <div class="flex flex-wrap gap-1 mt-2">
                        @foreach($role->permissions->take(6) as $perm)
                            <span class="px-1.5 py-0.5 rounded text-[10px] bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-500 dark:text-slate-400 font-mono">
                                {{ $perm->name }}
                            </span>
                        @endforeach
                        @if($role->permissions_count > 6)
                            <span class="px-1.5 py-0.5 rounded text-[10px] text-slate-400 dark:text-slate-500 font-medium">
                                +{{ $role->permissions_count - 6 }} khác
                            </span>
                        @endif
                    </div>

                    @if($role->name !== 'admin')
                    <div class="mt-3 pt-3 border-t border-slate-200 dark:border-slate-700">
                        <button onclick='openEditRoleModal({{ json_encode(["id"=>$role->id,"name"=>$role->name,"permissions"=>$role->permissions->pluck("name")]) }})'
                                class="text-xs text-pcrm-600 dark:text-pcrm-400 hover:underline font-medium">
                            Chỉnh sửa quyền hạn →
                        </button>
                    </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-3 card">
                <div class="card-body text-center text-slate-400 py-12">
                    <i class="bi bi-shield text-3xl block mb-2 opacity-40"></i>
                    <p>Chưa có vai trò nào.</p>
                </div>
            </div>
        @endforelse
    </div>

</div>

@endsection

@push('modals')
    @include('roles.partials.create-modal')
    @include('roles.partials.edit-modal')

    {{-- Delete modal --}}
    <div id="deleteRoleModal" class="hidden fixed inset-0 bg-black/50 z-50 items-center justify-center p-4"
         onclick="if(event.target===this)closeModal('deleteRoleModal')">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-sm p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center shrink-0">
                    <i class="bi bi-exclamation-triangle text-red-600 dark:text-red-400"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-slate-900 dark:text-white">Xóa vai trò</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Hành động này không thể hoàn tác.</p>
                </div>
            </div>
            <p class="text-sm text-slate-700 dark:text-slate-300 mb-5">
                Bạn có chắc muốn xóa vai trò <strong id="deleteRoleName"></strong>?
            </p>
            <div class="flex gap-3">
                <button onclick="closeModal('deleteRoleModal')" class="btn-secondary flex-1">Hủy</button>
                <form id="deleteRoleForm" method="POST" class="flex-1">
                    @csrf @method('DELETE')
                    <button type="submit" class="w-full px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-medium transition-colors">
                        Xóa vai trò
                    </button>
                </form>
            </div>
        </div>
    </div>
@endpush

@push('scripts')
<script>
function openEditRoleModal(data) {
    document.getElementById('editRoleId').value   = data.id;
    document.getElementById('editRoleName').value = data.name ?? '';
    document.getElementById('editRoleForm').action = '/roles/' + data.id;

    const activePerms = data.permissions || [];
    document.querySelectorAll('.edit-role-perm').forEach(function(cb) {
        cb.checked = activePerms.includes(cb.dataset.perm);
    });
    updateEditRolePermCount();
    openModal('editRoleModal');
}

function confirmDeleteRole(roleId, roleName, userCount) {
    if (userCount > 0) {
        alert('Không thể xóa vai trò "' + roleName + '" vì đang có ' + userCount + ' người dùng sử dụng.');
        return;
    }
    document.getElementById('deleteRoleName').textContent = roleName;
    document.getElementById('deleteRoleForm').action = '/roles/' + roleId;
    openModal('deleteRoleModal');
}

function createRoleSelectAll() {
    document.querySelectorAll('.create-role-perm').forEach(cb => cb.checked = true);
    updateCreateRolePermCount();
}
function createRoleDeselectAll() {
    document.querySelectorAll('.create-role-perm').forEach(cb => cb.checked = false);
    updateCreateRolePermCount();
}
function editRoleSelectAll() {
    document.querySelectorAll('.edit-role-perm').forEach(cb => cb.checked = true);
    updateEditRolePermCount();
}
function editRoleDeselectAll() {
    document.querySelectorAll('.edit-role-perm').forEach(cb => cb.checked = false);
    updateEditRolePermCount();
}
function updateCreateRolePermCount() {
    const el = document.getElementById('createRolePermCount');
    if (el) el.textContent = document.querySelectorAll('.create-role-perm:checked').length;
}
function updateEditRolePermCount() {
    const el = document.getElementById('editRolePermCount');
    if (el) el.textContent = document.querySelectorAll('.edit-role-perm:checked').length;
}

document.querySelectorAll('.create-role-perm').forEach(cb => cb.addEventListener('change', updateCreateRolePermCount));
document.querySelectorAll('.edit-role-perm').forEach(cb => cb.addEventListener('change', updateEditRolePermCount));
updateCreateRolePermCount();

@if($errors->any() && old('_modal'))
document.addEventListener('DOMContentLoaded', function() {
    openModal('{{ old("_modal") }}');
});
@endif
</script>
@endpush
