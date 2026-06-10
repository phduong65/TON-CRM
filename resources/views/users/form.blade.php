@extends('layouts.admin')

@section('title', isset($user) ? 'Sửa người dùng' : 'Thêm người dùng')

@section('content')
@php
    $isEdit = isset($user);
    $userRole = $userRole ?? '';
    $userDirectPermissions = $userDirectPermissions ?? [];
    $rolePermissions = $rolePermissions ?? [];
@endphp

<div class="max-w-3xl space-y-5">

    {{-- Header --}}
    <div class="flex items-center gap-3">
        <a href="{{ route('users.index') }}" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
            <i class="bi bi-arrow-left text-sm"></i>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-white">
                {{ $isEdit ? 'Sửa người dùng: ' . $user->name : 'Thêm người dùng' }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                {{ $isEdit ? 'Cập nhật thông tin, vai trò và quyền hạn riêng' : 'Tạo tài khoản mới và phân vai trò' }}
            </p>
        </div>
    </div>

    <form action="{{ $isEdit ? route('users.update', $user) : route('users.store') }}" method="POST" class="space-y-5">
        @csrf
        @if($isEdit) @method('PUT') @endif

        {{-- Basic info --}}
        <div class="card">
            <div class="card-header">
                <h2 class="font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                    <i class="bi bi-person text-pcrm-600"></i> Thông tin cơ bản
                </h2>
            </div>
            <div class="card-body space-y-4">
                <div>
                    <label for="name" class="form-label">Họ và tên <span class="text-red-500">*</span></label>
                    <input type="text" id="name" name="name" class="form-input"
                           value="{{ old('name', $user->name ?? '') }}"
                           placeholder="VD: Nguyễn Văn A" required>
                    @error('name') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="email" class="form-label">Email <span class="text-red-500">*</span></label>
                    <input type="email" id="email" name="email" class="form-input"
                           value="{{ old('email', $user->email ?? '') }}"
                           placeholder="email@congty.vn" required>
                    @error('email') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="password" class="form-label">
                            Mật khẩu {{ $isEdit ? '' : '*' }}
                        </label>
                        @if($isEdit)
                            <p class="text-[11px] text-slate-400 dark:text-slate-500 mb-1">Để trống nếu không đổi mật khẩu</p>
                        @endif
                        <input type="password" id="password" name="password" class="form-input"
                               placeholder="{{ $isEdit ? 'Để trống nếu không đổi' : 'Tối thiểu 8 ký tự' }}"
                               {{ $isEdit ? '' : 'required' }}>
                        @error('password') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="password_confirmation" class="form-label">Xác nhận mật khẩu</label>
                        @if($isEdit) <p class="text-[11px] text-slate-400 mb-1">&nbsp;</p> @endif
                        <input type="password" id="password_confirmation" name="password_confirmation" class="form-input"
                               placeholder="Nhập lại mật khẩu">
                    </div>
                </div>
            </div>
        </div>

        {{-- Role selection --}}
        <div class="card">
            <div class="card-header">
                <h2 class="font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                    <i class="bi bi-shield-check text-pcrm-600"></i> Vai trò
                </h2>
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Vai trò quyết định tập quyền mặc định của người dùng</p>
            </div>
            <div class="card-body">
                @error('role') <p class="form-error mb-3">{{ $message }}</p> @enderror
                <div class="grid grid-cols-2 gap-3" id="role-list">
                    @php
                        $roleLabels = [
                            'admin'       => ['Quản trị viên', 'Toàn quyền hệ thống', 'bi-shield-fill', 'text-red-600 dark:text-red-400', 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800'],
                            'manager'     => ['Quản lý', 'Duyệt phiếu, quản lý nhân sự', 'bi-person-badge-fill', 'text-blue-600 dark:text-blue-400', 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800'],
                            'team_leader' => ['Trưởng nhóm', 'Tạo phiếu phạt, xem nhân viên', 'bi-people-fill', 'text-yellow-600 dark:text-yellow-400', 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800'],
                            'staff'       => ['Nhân viên', 'Xem cơ bản', 'bi-person-fill', 'text-slate-600 dark:text-slate-400', 'bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700'],
                        ];
                    @endphp
                    @foreach($roles as $role)
                        @php
                            $meta = $roleLabels[$role->name] ?? [$role->name, '', 'bi-shield', 'text-slate-500', 'bg-slate-50 dark:bg-slate-800 border-slate-200'];
                            $selected = old('role', $userRole) === $role->name;
                        @endphp
                        <label class="role-card flex items-start gap-3 p-3 rounded-xl border-2 cursor-pointer transition-all
                                      {{ $selected ? $meta[4] : 'border-transparent hover:border-slate-200 dark:hover:border-slate-600' }}"
                               data-role="{{ $role->name }}" data-colors="{{ $meta[4] }}">
                            <input type="radio" name="role" value="{{ $role->name }}" class="sr-only" {{ $selected ? 'checked' : '' }}>
                            <span class="w-8 h-8 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center shrink-0 mt-0.5">
                                <i class="{{ $meta[2] }} {{ $meta[3] }} text-sm"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="font-semibold text-sm text-slate-800 dark:text-slate-200">{{ $meta[0] }}</p>
                                <p class="text-[11px] text-slate-400 dark:text-slate-500 leading-snug">{{ $meta[1] }}</p>
                                <p class="text-[10px] text-slate-400 mt-0.5">{{ $role->permissions->count() }} quyền</p>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Additional direct permissions --}}
        <div class="card">
            <div class="card-header">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                            <i class="bi bi-key text-pcrm-600"></i> Quyền hạn riêng
                        </h2>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">
                            Quyền thêm vào ngoài vai trò — ô màu xanh là quyền đã có qua vai trò
                        </p>
                    </div>
                    <button type="button" onclick="togglePermissions()"
                            class="text-xs text-pcrm-600 dark:text-pcrm-400 hover:underline shrink-0 ml-4">
                        Ẩn/Hiện
                    </button>
                </div>
            </div>
            <div id="permissions-section" class="card-body space-y-5">
                @foreach($permissionGroups as $groupName => $perms)
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">{{ $groupName }}</p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        @foreach($perms as $permKey => $permLabel)
                            @php
                                $viaRole  = in_array($permKey, $rolePermissions);
                                $checked  = in_array($permKey, old('permissions', $userDirectPermissions));
                            @endphp
                            <label class="flex items-center gap-2 p-2 rounded-lg border cursor-pointer transition-colors
                                          {{ $viaRole
                                              ? 'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800'
                                              : 'border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                                <input type="checkbox" name="permissions[]" value="{{ $permKey }}"
                                       {{ $checked || $viaRole ? 'checked' : '' }}
                                       {{ $viaRole ? 'disabled' : '' }}
                                       class="rounded border-slate-300 dark:border-slate-600 text-pcrm-600 focus:ring-pcrm-500">
                                <div class="min-w-0">
                                    <p class="text-xs font-medium text-slate-700 dark:text-slate-300 leading-tight">{{ $permLabel }}</p>
                                    <p class="text-[10px] text-slate-400 dark:text-slate-500 leading-tight truncate">{{ $permKey }}</p>
                                    @if($viaRole)
                                        <p class="text-[10px] text-emerald-600 dark:text-emerald-400 leading-tight">qua vai trò</p>
                                    @endif
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('users.index') }}" class="btn-secondary">Hủy</a>
            <button type="submit" class="btn-primary">
                <i class="bi bi-floppy text-sm"></i>
                <span>{{ $isEdit ? 'Cập nhật' : 'Tạo người dùng' }}</span>
            </button>
        </div>

    </form>
</div>
@endsection

@push('scripts')
<script>
// Role card selection highlight
document.querySelectorAll('.role-card').forEach(function(card) {
    card.addEventListener('click', function() {
        const radio = this.querySelector('input[type=radio]');
        radio.checked = true;

        document.querySelectorAll('.role-card').forEach(function(c) {
            c.classList.remove(...c.dataset.colors.split(' '));
            c.classList.add('border-transparent');
        });

        this.classList.remove('border-transparent');
        this.classList.add(...this.dataset.colors.split(' '));
    });
});

function togglePermissions() {
    const section = document.getElementById('permissions-section');
    section.classList.toggle('hidden');
}
</script>
@endpush
