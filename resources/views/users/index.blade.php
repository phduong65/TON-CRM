@extends('layouts.admin')

@section('title', 'Quản lý người dùng')
@section('page-title', 'Quản lý người dùng')
@section('breadcrumb', 'Quản trị')
@section('page-subtitle', 'Tài khoản đăng nhập hệ thống và phân quyền')

@section('page-actions')
    @can('manage-users')
    <button onclick="openModal('createUserModal')" class="btn-primary">
        <i class="bi bi-person-plus text-sm"></i>
        <span>Thêm người dùng</span>
    </button>
    @endcan
@endsection

@section('content')
<div class="space-y-5">

    @php
        $currentEmployeeStatus = request('employee_status', 'active');
        $currentRole = request('role');
        $q = fn(array $set = [], array $drop = []) => array_filter(
            array_merge(request()->except(array_merge(['page'], $drop, array_keys($set))), $set),
            fn($v) => $v !== null && $v !== ''
        );
        $isFiltered = request()->filled('search') || request()->filled('role') || (request()->filled('employee_status') && request('employee_status') !== 'active');
    @endphp

    <div class="card overflow-hidden">
        {{-- Status Tabs: Lọc theo trạng thái nhân viên (mặc định là Đang làm) --}}
        <div class="notif-head">
            <nav class="status-tabs" aria-label="Lọc theo trạng thái nhân viên">
                <a href="{{ route('users.index', $q(['employee_status' => 'all'])) }}"
                   class="status-tab {{ $currentEmployeeStatus === 'all' ? 'is-active' : '' }}" @if ($currentEmployeeStatus === 'all') aria-current="page" @endif>
                    Tất cả <span class="status-tab-count">{{ number_format($statusCounts['all']) }}</span>
                </a>
                <a href="{{ route('users.index', $q([], ['employee_status'])) }}"
                   class="status-tab {{ $currentEmployeeStatus === 'active' || $currentEmployeeStatus === '1' ? 'is-active' : '' }}"
                   @if ($currentEmployeeStatus === 'active' || $currentEmployeeStatus === '1') aria-current="page" @endif>
                    Đang làm <span class="status-tab-count">{{ number_format($statusCounts['active']) }}</span>
                </a>
                <a href="{{ route('users.index', $q(['employee_status' => 'resigned'])) }}"
                   class="status-tab {{ $currentEmployeeStatus === 'resigned' || $currentEmployeeStatus === '0' ? 'is-active' : '' }}"
                   @if ($currentEmployeeStatus === 'resigned' || $currentEmployeeStatus === '0') aria-current="page" @endif>
                    Đã nghỉ <span class="status-tab-count">{{ number_format($statusCounts['resigned']) }}</span>
                </a>
                <a href="{{ route('users.index', $q(['employee_status' => 'unlinked'])) }}"
                   class="status-tab {{ $currentEmployeeStatus === 'unlinked' ? 'is-active' : '' }}"
                   @if ($currentEmployeeStatus === 'unlinked') aria-current="page" @endif>
                    Chưa liên kết NV <span class="status-tab-count">{{ number_format($statusCounts['unlinked']) }}</span>
                </a>
            </nav>
        </div>

        {{-- Type Chips: Lọc theo vai trò (như nhóm thông báo) --}}
        <div class="type-chips notif-chips" role="group" aria-label="Lọc theo vai trò">
            <a href="{{ route('users.index', $q([], ['role'])) }}"
               class="type-chip {{ !$currentRole ? 'is-active' : '' }}" @if (!$currentRole) aria-current="true" @endif>
                <i class="bi bi-grid" aria-hidden="true"></i> Mọi vai trò
                <span class="type-chip-count">{{ number_format($totalForRoles) }}</span>
            </a>
            @foreach ($roles as $role)
                @php
                    $roleIcons = [
                        'admin'       => 'bi-shield-check',
                        'director'    => 'bi-briefcase',
                        'manager'     => 'bi-person-badge',
                        'team_leader' => 'bi-people',
                        'staff'       => 'bi-person',
                    ];
                    $rLabels = ['admin'=>'Quản trị viên','director'=>'Giám đốc','manager'=>'Quản lý','team_leader'=>'Trưởng nhóm','staff'=>'Nhân viên'];
                    $rIcon = $roleIcons[$role->name] ?? 'bi-person';
                    $rLabel = $rLabels[$role->name] ?? $role->name;
                    $rCount = $roleCounts[$role->name] ?? 0;
                @endphp
                <a href="{{ route('users.index', $q(['role' => $role->name])) }}"
                   class="type-chip {{ $currentRole === $role->name ? 'is-active' : '' }}"
                   @if ($currentRole === $role->name) aria-current="true" @endif>
                    <i class="bi {{ $rIcon }}" aria-hidden="true"></i> {{ $rLabel }}
                    <span class="type-chip-count">{{ number_format($rCount) }}</span>
                </a>
            @endforeach
        </div>

        {{-- Thanh tìm kiếm tinh gọn --}}
        <div class="px-4 py-2.5 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/40">
            <div class="flex flex-col sm:flex-row gap-2.5 sm:items-center justify-between">
                <form action="{{ route('users.index') }}" method="GET" class="relative flex-1 max-w-md" role="search">
                    @if(request()->filled('employee_status') && request('employee_status') !== 'active')
                        <input type="hidden" name="employee_status" value="{{ request('employee_status') }}">
                    @endif
                    @if($currentRole)
                        <input type="hidden" name="role" value="{{ $currentRole }}">
                    @endif

                    <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none" aria-hidden="true"></i>
                    <input type="search" name="search" value="{{ request('search') }}"
                           class="form-input pl-8.5 pr-8 h-9 text-sm w-full rounded-lg bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 focus:border-pcrm-500"
                           placeholder="Tìm theo tên, email hoặc mã NV…" autocomplete="off"
                           aria-label="Tìm kiếm người dùng">
                    @if(request('search'))
                        <a href="{{ route('users.index', $q([], ['search'])) }}"
                           class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors p-0.5"
                           title="Xóa tìm kiếm">
                            <i class="bi bi-x-circle-fill text-xs"></i>
                        </a>
                    @endif
                </form>

                <div class="flex items-center gap-3 justify-between sm:justify-end">
                    @if($isFiltered)
                    <a href="{{ route('users.index') }}" class="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-pcrm-600 dark:text-slate-400 dark:hover:text-pcrm-400 font-medium transition-colors">
                        <i class="bi bi-arrow-counterclockwise"></i> Đặt lại bộ lọc
                    </a>
                    @endif
                    <span class="text-xs text-slate-400 dark:text-slate-500 font-medium whitespace-nowrap">{{ $users->total() }} người dùng</span>
                </div>
            </div>
        </div>
        <div class="table-container border-0 rounded-none">
        <table class="w-full min-w-[900px] text-sm">
            <thead>
                <tr class="border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60">
                    <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300 whitespace-nowrap">#</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300 whitespace-nowrap" data-mcard-title>Người dùng</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300 whitespace-nowrap">Vai trò</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300 whitespace-nowrap">Trạng thái TK</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300 whitespace-nowrap">TT Nhân viên</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300 whitespace-nowrap">Quyền riêng</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-600 dark:text-slate-300 whitespace-nowrap">Đăng ký</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-600 dark:text-slate-300 whitespace-nowrap">Thao tác</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse($users as $user)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                    <td class="px-4 py-3 text-slate-400 dark:text-slate-500 text-xs">{{ $loop->iteration }}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <x-employee-avatar :user="$user" size="w-8 h-8" :initials="2"
                                :fallback="$user->status === 'inactive' ? 'bg-slate-200 dark:bg-slate-700 text-slate-400' : 'bg-pcrm-100 dark:bg-pcrm-900/50 text-pcrm-700 dark:text-pcrm-400'" />
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
                    <td class="px-4 py-3 whitespace-nowrap">
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
                    <td class="px-4 py-3 whitespace-nowrap">
                        @if($user->status === 'pending')
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                <i class="bi bi-hourglass-split text-[9px]"></i> Chờ duyệt
                            </span>
                        @elseif($user->status === 'inactive')
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                <i class="bi bi-lock text-[9px]"></i> Tạm khóa
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                <i class="bi bi-check-circle text-[9px]"></i> Hoạt động
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        @if($user->employee)
                            <div class="flex items-center gap-1.5">
                                @if($user->employee->is_active)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                                        <i class="bi bi-briefcase text-[9px]"></i> Đang làm
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400">
                                        <i class="bi bi-person-x text-[9px]"></i> Đã nghỉ
                                    </span>
                                @endif
                                @if($user->employee->code)
                                    <span class="text-[11px] font-mono text-slate-400 dark:text-slate-500">({{ $user->employee->code }})</span>
                                @endif
                            </div>
                        @else
                            <span class="text-xs text-slate-400 dark:text-slate-500 italic">Chưa liên kết</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
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
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <div class="flex items-center justify-end gap-1">
                            @can('manage-users')
                            @if($user->status === 'pending')
                                <button onclick='openEditUserModal({{ json_encode(["id"=>$user->id,"name"=>$user->name,"email"=>$user->email,"role"=>$user->roles->first()?->name ?? "","status"=>$user->status,"permissions"=>$user->getDirectPermissions()->pluck("name")]) }})'
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-colors"
                                        title="Chọn vai trò và kích hoạt tài khoản">
                                    <i class="bi bi-check-lg text-xs"></i> Duyệt
                                </button>
                                @if($user->id !== auth()->id())
                                <form action="{{ route('users.reject', $user) }}" method="POST" class="inline"
                                      onsubmit="return confirm('Từ chối đăng ký của {{ addslashes($user->name) }}?');">
                                    @csrf
                                    <button type="submit"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium text-orange-600 dark:text-orange-400 hover:bg-orange-50 dark:hover:bg-orange-900/20 transition-colors"
                                            title="Từ chối đăng ký">
                                        <i class="bi bi-x-lg text-xs"></i><span class="sm:max-2xl:sr-only">Từ chối</span>
                                    </button>
                                </form>
                                @endif
                            @else
                                <button onclick='openEditUserModal({{ json_encode(["id"=>$user->id,"name"=>$user->name,"email"=>$user->email,"role"=>$user->roles->first()?->name ?? "","status"=>$user->status,"permissions"=>$user->getDirectPermissions()->pluck("name")]) }})'
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                                    <i class="bi bi-pencil text-xs"></i><span class="sm:max-2xl:sr-only">Sửa</span>
                                </button>
                                @if($user->id !== auth()->id())
                                <form action="{{ route('users.toggleStatus', $user) }}" method="POST" class="inline">
                                    @csrf
                                    @if($user->status === 'inactive')
                                    <button type="submit"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium text-green-600 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/20 transition-colors"
                                            title="Kích hoạt tài khoản">
                                        <i class="bi bi-unlock text-xs"></i><span class="sm:max-2xl:sr-only">Kích hoạt</span>
                                    </button>
                                    @else
                                    <button type="submit"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium text-orange-600 dark:text-orange-400 hover:bg-orange-50 dark:hover:bg-orange-900/20 transition-colors"
                                            title="Tạm khóa tài khoản">
                                        <i class="bi bi-lock text-xs"></i><span class="sm:max-2xl:sr-only">Khóa</span>
                                    </button>
                                    @endif
                                </form>
                                @endif
                            @endif
                            @endcan
                            @can('impersonate-users')
                            @if($user->id !== auth()->id() && !$user->hasRole('admin') && $user->status === 'active')
                            <form action="{{ route('users.impersonate', $user) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium text-violet-600 dark:text-violet-400 hover:bg-violet-50 dark:hover:bg-violet-900/20 transition-colors"
                                        title="Đăng nhập hộ tài khoản này">
                                    <i class="bi bi-box-arrow-in-right text-xs"></i><span class="sm:max-2xl:sr-only">Đăng nhập hộ</span>
                                </button>
                            </form>
                            @endif
                            @endcan
                            @if($user->id !== auth()->id())
                            <button onclick="openDeleteUserModal({{ $user->id }}, '{{ addslashes($user->name) }}')"
                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                                <i class="bi bi-trash text-xs"></i><span class="sm:max-2xl:sr-only">Xóa</span>
                            </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-4 py-12 text-center text-slate-400 dark:text-slate-500">
                        <i class="bi bi-people text-3xl block mb-2 opacity-40"></i>
                        <p class="font-medium text-slate-600 dark:text-slate-300">
                            {{ $isFiltered ? 'Không có người dùng nào khớp với bộ lọc' : 'Chưa có người dùng nào.' }}
                        </p>
                        @if($isFiltered)
                        <a href="{{ route('users.index') }}" class="mt-2.5 inline-flex items-center gap-1.5 text-xs text-pcrm-600 dark:text-pcrm-400 hover:underline font-medium">
                            <i class="bi bi-arrow-counterclockwise"></i> Xem tất cả người dùng
                        </a>
                        @endif
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
// Vai trò ↔ quyền riêng: quyền đã có qua vai trò được đánh dấu + khoá trong bộ chọn quyền
function syncUserRolePerms(form) {
    const picked = form.querySelector('input[name="role"]:checked');
    const perms = picked ? JSON.parse(picked.dataset.rolePerms || '[]') : [];
    PermPicker.lockViaRole(form.querySelector('[data-perm-picker]'), perms);
}
document.addEventListener('change', function (e) {
    const form = e.target.closest('[data-user-form]');
    if (form && e.target.name === 'role') syncUserRolePerms(form);
});
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-user-form]').forEach(syncUserRolePerms);
});

function openEditUserModal(data) {
    const form = document.getElementById('editUserForm');
    document.getElementById('editUserId').value    = data.id;
    document.getElementById('editUserName').value  = data.name  ?? '';
    document.getElementById('editUserEmail').value = data.email ?? '';
    document.getElementById('editUserModalSub').textContent = data.email ?? '';
    form.action = '/users/' + data.id;
    form.querySelectorAll('input[type="password"]').forEach(function (i) { i.value = ''; });

    // Tài khoản pending (tự đăng ký chờ duyệt) — modal đổi thành "Duyệt tài khoản".
    // UsersController::update() tự chuyển pending -> active khi lưu.
    const isPending = data.status === 'pending';
    document.getElementById('editUserModalTitle').textContent = isPending ? 'Duyệt tài khoản' : 'Sửa người dùng';
    document.getElementById('editUserModalIcon').className = isPending ? 'bi bi-person-check' : 'bi bi-pencil-square';
    document.getElementById('editUserModalIconWrap').className = 'pcrm-dialog-icon ' + (isPending
        ? 'bg-[#e8f5ef] text-[#168A63] dark:bg-[#168A63]/20 dark:text-[#34d399]'
        : 'bg-[#fef3dc] text-[#C98219] dark:bg-[#C98219]/20 dark:text-[#fbbf24]');
    document.getElementById('editUserPendingHint').classList.toggle('hidden', !isPending);
    document.getElementById('editUserSubmitBtn').innerHTML = isPending
        ? '<i class="bi bi-check-lg"></i> Duyệt &amp; kích hoạt'
        : '<i class="bi bi-check2"></i> Lưu thay đổi';

    form.querySelectorAll('input[name="role"]').forEach(function (radio) {
        radio.checked = radio.value === data.role;
    });

    const picker = document.getElementById('editUserPerms');
    PermPicker.lockViaRole(picker, []);              // mở khoá hết trước khi nạp quyền riêng mới
    PermPicker.set(picker, data.permissions || []);
    syncUserRolePerms(form);
    document.getElementById('editUserPermsDisclosure').open = (data.permissions || []).length > 0;

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
        id: {{ Illuminate\Support\Js::from(old('_edit_id')) }},
        name: {{ Illuminate\Support\Js::from(old('name')) }},
        email: {{ Illuminate\Support\Js::from(old('email')) }},
        role: {{ Illuminate\Support\Js::from(old('role')) }},
        permissions: {{ Illuminate\Support\Js::from(old('permissions', [])) }}
    });
    @else
    openModal('{{ old("_modal") }}');
    @endif
});
@endif
</script>
@endpush
