@extends('layouts.admin')

@section('title', isset($role) ? 'Sửa vai trò' : 'Thêm vai trò')

@section('content')
@php
    $isEdit = isset($role);
    $rolePermissions = $rolePermissions ?? [];
@endphp

<div class="max-w-3xl space-y-5">

    {{-- Header --}}
    <div class="flex items-center gap-3">
        <a href="{{ route('roles.index') }}" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
            <i class="bi bi-arrow-left text-sm"></i>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">
                {{ $isEdit ? 'Sửa vai trò: ' . $role->name : 'Thêm vai trò mới' }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Chọn các quyền hạn mà vai trò này được phép sử dụng</p>
        </div>
    </div>

    <form action="{{ $isEdit ? route('roles.update', $role) : route('roles.store') }}" method="POST" class="space-y-5">
        @csrf
        @if($isEdit) @method('PUT') @endif

        {{-- Role name --}}
        <div class="card">
            <div class="card-header">
                <h2 class="font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                    <i class="bi bi-shield text-pcrm-600"></i> Thông tin vai trò
                </h2>
            </div>
            <div class="card-body">
                <label for="name" class="form-label">Tên vai trò <span class="text-red-500">*</span></label>
                <input type="text" id="name" name="name" class="form-input max-w-sm"
                       value="{{ old('name', $role->name ?? '') }}"
                       placeholder="VD: supervisor"
                       {{ ($isEdit && $role->name === 'admin') ? 'disabled' : '' }}
                       required>
                <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">Chỉ dùng chữ thường và dấu gạch dưới, VD: <span class="font-mono">team_leader</span></p>
                @error('name') <p class="form-error">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Permissions --}}
        <div class="card">
            <div class="card-header">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                            <i class="bi bi-key text-pcrm-600"></i> Quyền hạn
                        </h2>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Chọn các chức năng mà vai trò này có thể thực hiện</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="selectAll()" class="text-xs text-pcrm-600 dark:text-pcrm-400 hover:underline">Chọn tất cả</button>
                        <span class="text-slate-300 dark:text-slate-600">|</span>
                        <button type="button" onclick="deselectAll()" class="text-xs text-slate-500 dark:text-slate-400 hover:underline">Bỏ chọn</button>
                    </div>
                </div>
            </div>
            <div class="card-body space-y-6">
                @foreach($permissionGroups as $groupName => $perms)
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ $groupName }}</p>
                        <button type="button" onclick="toggleGroup(this)"
                                data-group="{{ Str::slug($groupName) }}"
                                class="text-[11px] text-slate-400 hover:text-pcrm-600 dark:hover:text-pcrm-400">
                            Chọn nhóm
                        </button>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 perm-group" data-group="{{ Str::slug($groupName) }}">
                        @foreach($perms as $permKey => $permLabel)
                            @php $checked = in_array($permKey, old('permissions', $rolePermissions)); @endphp
                            <label class="flex items-start gap-2 p-2.5 rounded-lg border border-slate-200 dark:border-slate-700 cursor-pointer
                                          hover:border-pcrm-300 dark:hover:border-pcrm-600 hover:bg-pcrm-50/50 dark:hover:bg-pcrm-900/10 transition-colors
                                          {{ $checked ? 'bg-pcrm-50 dark:bg-pcrm-900/20 border-pcrm-200 dark:border-pcrm-700' : '' }}">
                                <input type="checkbox" name="permissions[]" value="{{ $permKey }}"
                                       {{ $checked ? 'checked' : '' }}
                                       class="perm-checkbox rounded border-slate-300 dark:border-slate-600 text-pcrm-600 focus:ring-pcrm-500 mt-0.5 shrink-0">
                                <div class="min-w-0">
                                    <p class="text-xs font-medium text-slate-700 dark:text-slate-300 leading-tight">{{ $permLabel }}</p>
                                    <p class="text-[10px] text-slate-400 dark:text-slate-500 font-mono leading-tight">{{ $permKey }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Counter + Actions --}}
        <div class="flex items-center justify-between">
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Đã chọn: <span id="selected-count" class="font-semibold text-pcrm-600 dark:text-pcrm-400">{{ count($rolePermissions) }}</span> quyền
            </p>
            <div class="flex items-center gap-3">
                <a href="{{ route('roles.index') }}" class="btn-secondary">Hủy</a>
                <button type="submit" class="btn-primary" {{ ($isEdit && $role->name === 'admin') ? 'disabled' : '' }}>
                    <i class="bi bi-floppy text-sm"></i>
                    <span>{{ $isEdit ? 'Cập nhật' : 'Tạo vai trò' }}</span>
                </button>
            </div>
        </div>

    </form>
</div>
@endsection

@push('scripts')
<script>
function updateCount() {
    const count = document.querySelectorAll('.perm-checkbox:checked').length;
    document.getElementById('selected-count').textContent = count;
}

function selectAll() {
    document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = true);
    updateCount();
}

function deselectAll() {
    document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = false);
    updateCount();
}

function toggleGroup(btn) {
    const groupName = btn.dataset.group;
    const checkboxes = document.querySelectorAll('.perm-group[data-group="' + groupName + '"] .perm-checkbox');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    checkboxes.forEach(cb => cb.checked = !allChecked);
    btn.textContent = allChecked ? 'Chọn nhóm' : 'Bỏ nhóm';
    updateCount();
}

document.querySelectorAll('.perm-checkbox').forEach(cb => {
    cb.addEventListener('change', updateCount);
});
</script>
@endpush
