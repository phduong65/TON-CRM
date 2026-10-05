{{--
    Danh sách vai trò dạng thẻ radio (dùng chung modal Thêm/Sửa người dùng).
    Biến: $roles (Role với permissions đã eager-load), $selectedRole (string|null), $prefix ('createUser'|'editUser')
    Trạng thái chọn hiển thị bằng CSS (.role-option:has(:checked)) — không cần JS tô màu.
--}}
@php
    $roleMeta = [
        'admin'       => ['Quản trị viên', 'Toàn quyền hệ thống', 'bi-shield-fill', 'text-[#C94758]'],
        'manager'     => ['Quản lý', 'Duyệt phiếu, quản lý nhân sự', 'bi-person-badge-fill', 'text-[#2F55E7]'],
        'director'    => ['Giám đốc', 'Xem báo cáo, giám sát chi nhánh', 'bi-briefcase-fill', 'text-[#1686B8]'],
        'hr'          => ['Nhân sự (HR)', 'Ca làm, chấm công, hồ sơ', 'bi-people-fill', 'text-[#168A63]'],
        'team_leader' => ['Trưởng nhóm', 'Tạo phiếu phạt, xem nhân viên', 'bi-diagram-3-fill', 'text-[#C98219]'],
        'staff'       => ['Nhân viên', 'Chấm công, xem thông tin cá nhân', 'bi-person-fill', 'text-slate-500'],
        'employee'    => ['Nhân viên', 'Chấm công, xem thông tin cá nhân', 'bi-person-fill', 'text-slate-500'],
    ];
@endphp
<div class="grid grid-cols-1 sm:grid-cols-2 gap-2" role="radiogroup" aria-label="Vai trò">
    @foreach ($roles as $role)
        @php $meta = $roleMeta[$role->name] ?? [$role->name, 'Vai trò tuỳ chỉnh', 'bi-shield', 'text-slate-500']; @endphp
        <label class="role-option">
            <input type="radio" name="role" value="{{ $role->name }}" class="sr-only" required
                   data-role-perms="{{ $role->permissions->pluck('name')->toJson() }}"
                   @checked($selectedRole === $role->name)>
            <span class="role-option-icon {{ $meta[3] }}" aria-hidden="true"><i class="bi {{ $meta[2] }}"></i></span>
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-semibold text-slate-900 dark:text-white truncate">{{ $meta[0] }}</span>
                <span class="block text-xs text-slate-500 dark:text-slate-400 truncate">{{ $meta[1] }}</span>
            </span>
            <span class="role-option-count tabular-nums">{{ $role->permissions->count() }} quyền</span>
            <i class="bi bi-check-circle-fill role-option-check" aria-hidden="true"></i>
        </label>
    @endforeach
</div>
