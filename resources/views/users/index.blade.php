@extends('layouts.admin')

@section('title', 'Quản lý người dùng')
@section('page-title', 'Quản lý người dùng')
@section('breadcrumb', 'Quản trị')

@section('content')
<div class="space-y-5">

    <div class="page-header">
        <div>
            <p class="page-subtitle">Tài khoản đăng nhập hệ thống và phân quyền</p>
        </div>
        @can('manage-users')
        <button onclick="openModal('createUserModal')" class="btn-primary">
            <i class="bi bi-person-plus text-sm"></i>
            <span>Thêm người dùng</span>
        </button>
        @endcan
    </div>

    {{-- Filter bar --}}
    <div class="card">
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700">
            @php $userFilterActive = request()->anyFilled(['search', 'role', 'status']); @endphp
            <form action="{{ route('users.index') }}" method="GET"
                  class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-end">
                <div class="relative min-w-0 sm:flex-1 sm:max-w-xs">
                    <i class="bi bi-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                           class="form-input pl-7 h-9 text-sm w-full" placeholder="Tên, email...">
                </div>
                <div class="grid grid-cols-2 gap-2 sm:contents">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Vai trò</label>
                        <select name="role" class="form-input h-9 text-sm w-full">
                            <option value="">Tất cả</option>
                            @foreach($roles as $role)
                                @php $rLabels = ['admin'=>'Quản trị viên','director'=>'Giám đốc','manager'=>'Quản lý','team_leader'=>'Trưởng nhóm','staff'=>'Nhân viên']; @endphp
                                <option value="{{ $role->name }}" @selected(request('role') === $role->name)>
                                    {{ $rLabels[$role->name] ?? $role->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Trạng thái</label>
                        <select name="status" class="form-input h-9 text-sm w-full">
                            <option value="">Tất cả</option>
                            <option value="active"   @selected(request('status') === 'active')>Hoạt động</option>
                            <option value="inactive" @selected(request('status') === 'inactive')>Tạm khóa</option>
                        </select>
                    </div>
                </div>
                <div class="flex gap-2 items-center">
                    <button type="submit" class="btn-primary h-9 px-4 text-sm flex-1 sm:flex-none gap-1.5">
                        <i class="bi bi-funnel text-xs"></i> Lọc
                    </button>
                    @if($userFilterActive)
                    <a href="{{ route('users.index') }}" class="btn-secondary h-9 px-3 inline-flex items-center gap-1 text-sm shrink-0">
                        <i class="bi bi-x text-sm"></i>
                    </a>
                    @endif
                    <span class="text-xs text-slate-400 dark:text-slate-500 ml-auto shrink-0">{{ $users->total() }} kết quả</span>
                </div>
            </form>
        </div>
        <div class="table-container border-0 rounded-none">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60">
                    <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">#</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Người dùng</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Vai trò</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Trạng thái TK</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Quyền riêng</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300">Đăng ký</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-600 dark:text-slate-300">Thao tác</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse($users as $user)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                    <td class="px-4 py-3 text-slate-400 dark:text-slate-500 text-xs">{{ $loop->iteration }}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full {{ $user->status === 'inactive' ? 'bg-slate-200 dark:bg-slate-700' : 'bg-pcrm-100 dark:bg-pcrm-900/50' }} flex items-center justify-center {{ $user->status === 'inactive' ? 'text-slate-400' : 'text-pcrm-700 dark:text-pcrm-400' }} font-bold text-xs shrink-0">
                                {{ strtoupper(substr($user->name, 0, 2)) }}
                            </div>
                            <div class="min-w-0">
                                <p class="font-medium {{ $user->status === 'inactive' ? 'text-slate-400 dark:text-slate-500 line-through' : 'text-slate-900 dark:text-white' }} truncate">
                                    {{ $user->name }}
                                    @if($user->id === auth()->id())
                                        <span class="ml-1 text-[10px] text-pcrm-600 dark:text-pcrm-400 font-semibold no-underline">(bạn)</span>
                                    @endif
                                </p>
                                <p class="text-xs text-slate-400 dark:text-slate-500 truncate">{{ $user->email }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        @foreach($user->roles as $role)
                            @php
                                $roleColors = [
                                    'admin'       => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                    'manager'     => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                                    'team_leader' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
                                    'staff'       => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400',
                                ];
                                $roleLabels = [
                                    'admin'       => 'Quản trị viên',
                                    'manager'     => 'Quản lý',
                                    'team_leader' => 'Trưởng nhóm',
                                    'staff'       => 'Nhân viên',
                                ];
                                $color = $roleColors[$role->name] ?? 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400';
                                $label = $roleLabels[$role->name] ?? $role->name;
                            @endphp
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $color }}">
                                <i class="bi bi-shield-check text-[9px]"></i>
                                {{ $label }}
                            </span>
                        @endforeach
                        @if($user->roles->isEmpty())
                            <span class="text-xs text-slate-400 italic">Chưa phân vai trò</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if($user->status === 'inactive')
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                <i class="bi bi-lock text-[9px]"></i> Tạm khóa
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                <i class="bi bi-check-circle text-[9px]"></i> Hoạt động
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @php $directCount = $user->getDirectPermissions()->count(); @endphp
                        @if($directCount > 0)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">
                                <i class="bi bi-key text-[9px]"></i>
                                {{ $directCount }} quyền riêng
                            </span>
                        @else
                            <span class="text-xs text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-400 dark:text-slate-500 whitespace-nowrap">
                        {{ $user->created_at->format('d/m/Y') }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-1">
                            @can('manage-users')
                            <button onclick='openEditUserModal({{ json_encode(["id"=>$user->id,"name"=>$user->name,"email"=>$user->email,"role"=>$user->roles->first()?->name ?? ""]) }})'
                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                                <i class="bi bi-pencil text-xs"></i> Sửa
                            </button>
                            @if($user->id !== auth()->id())
                            <form action="{{ route('users.toggleStatus', $user) }}" method="POST" class="inline">
                                @csrf
                                @if($user->status === 'inactive')
                                <button type="submit"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium text-green-600 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/20 transition-colors"
                                        title="Kích hoạt tài khoản">
                                    <i class="bi bi-unlock text-xs"></i> Kích hoạt
                                </button>
                                @else
                                <button type="submit"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium text-orange-600 dark:text-orange-400 hover:bg-orange-50 dark:hover:bg-orange-900/20 transition-colors"
                                        title="Tạm khóa tài khoản">
                                    <i class="bi bi-lock text-xs"></i> Khóa
                                </button>
                                @endif
                            </form>
                            @endif
                            @endcan
                            @if($user->id !== auth()->id())
                            <button onclick="openDeleteUserModal({{ $user->id }}, '{{ addslashes($user->name) }}')"
                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                                <i class="bi bi-trash text-xs"></i> Xóa
                            </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-12 text-center text-slate-400 dark:text-slate-500">
                        <i class="bi bi-people text-3xl block mb-2 opacity-40"></i>
                        <p>Chưa có người dùng nào.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
        @if($users->hasPages())
        <div class="px-4 py-3 border-t border-slate-100 dark:border-slate-700">
            {{ $users->links() }}
        </div>
        @endif
    </div>

</div>

@endsection

@push('modals')
    @include('users.partials.create-modal')
    @include('users.partials.edit-modal')

    {{-- Delete modal --}}
    <div id="deleteUserModal" class="hidden fixed inset-0 bg-black/50 z-50 items-center justify-center p-4"
         onclick="if(event.target===this)closeModal('deleteUserModal')">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-sm p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center shrink-0">
                    <i class="bi bi-exclamation-triangle text-red-600 dark:text-red-400"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-slate-900 dark:text-white">Xóa người dùng</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Hành động này không thể hoàn tác.</p>
                </div>
            </div>
            <p class="text-sm text-slate-700 dark:text-slate-300 mb-5">
                Bạn có chắc muốn xóa người dùng <strong id="deleteUserName"></strong>?
            </p>
            <div class="flex gap-3">
                <button onclick="closeModal('deleteUserModal')" class="btn-secondary flex-1">Hủy</button>
                <form id="deleteUserForm" method="POST" class="flex-1">
                    @csrf @method('DELETE')
                    <button type="submit" class="w-full px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-medium transition-colors">
                        Xóa
                    </button>
                </form>
            </div>
        </div>
    </div>
@endpush

@push('scripts')
<script>
// Role card highlight for create modal
document.querySelectorAll('.create-role-card').forEach(function(card) {
    card.addEventListener('click', function() {
        const radio = this.querySelector('input[type=radio]');
        radio.checked = true;
        document.querySelectorAll('.create-role-card').forEach(function(c) {
            c.classList.remove(...c.dataset.colors.split(' '));
            c.classList.add('border-slate-200', 'dark:border-slate-700');
        });
        this.classList.remove('border-slate-200', 'dark:border-slate-700');
        this.classList.add(...this.dataset.colors.split(' '));
    });
});

// Role card highlight for edit modal
document.querySelectorAll('.edit-role-card').forEach(function(card) {
    card.addEventListener('click', function() {
        const radio = this.querySelector('input[type=radio]');
        radio.checked = true;
        document.querySelectorAll('.edit-role-card').forEach(function(c) {
            c.classList.remove(...c.dataset.colors.split(' '));
            c.classList.add('border-slate-200', 'dark:border-slate-700');
        });
        this.classList.remove('border-slate-200', 'dark:border-slate-700');
        this.classList.add(...this.dataset.colors.split(' '));
    });
});

function openEditUserModal(data) {
    document.getElementById('editUserId').value    = data.id;
    document.getElementById('editUserName').value  = data.name  ?? '';
    document.getElementById('editUserEmail').value = data.email ?? '';
    document.getElementById('editUserForm').action = '/users/' + data.id;

    // Reset all role cards
    document.querySelectorAll('.edit-role-card').forEach(function(card) {
        card.classList.remove(...card.dataset.colors.split(' '));
        card.classList.add('border-slate-200', 'dark:border-slate-700');
        const radio = card.querySelector('input[type=radio]');
        radio.checked = false;
        if (radio.value === data.role) {
            radio.checked = true;
            card.classList.remove('border-slate-200', 'dark:border-slate-700');
            card.classList.add(...card.dataset.colors.split(' '));
        }
    });

    openModal('editUserModal');
}

function openDeleteUserModal(id, name) {
    document.getElementById('deleteUserName').textContent = name;
    document.getElementById('deleteUserForm').action = '/users/' + id;
    openModal('deleteUserModal');
}

@if($errors->any() && old('_modal'))
document.addEventListener('DOMContentLoaded', function() {
    @if(old('_modal') === 'editUserModal')
    openEditUserModal({
        id: '{{ old("_edit_id") }}',
        name: '{{ old("name") }}',
        email: '{{ old("email") }}',
        role: '{{ old("role") }}'
    });
    @else
    openModal('{{ old("_modal") }}');
    @endif
});
@endif
</script>
@endpush
