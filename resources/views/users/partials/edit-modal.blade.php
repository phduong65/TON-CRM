<div id="editUserModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('editUserModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-3xl max-h-[95vh] flex flex-col">
        <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 dark:border-slate-700 shrink-0">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-pencil-square text-amber-500"></i> Sửa người dùng
            </h3>
            <button onclick="closeModal('editUserModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form id="editUserForm" method="POST" class="px-4 sm:px-6 py-4 sm:py-5 space-y-5 overflow-y-auto">
            @csrf @method('PUT')
            <input type="hidden" name="_modal" value="editUserModal">
            <input type="hidden" name="_edit_id" id="editUserId">

            {{-- Basic info --}}
            <div class="space-y-3">
                <h4 class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Thông tin cơ bản</h4>
                <div>
                    <label class="form-label">Họ và tên <span class="text-red-500">*</span></label>
                    <input type="text" id="editUserName" name="name" class="form-input" required>
                    @error('name') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Email <span class="text-red-500">*</span></label>
                    <input type="email" id="editUserEmail" name="email" class="form-input" required>
                    @error('email') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Mật khẩu mới</label>
                        <p class="text-[11px] text-slate-400 mb-1">Để trống nếu không đổi</p>
                        <input type="password" name="password" class="form-input" placeholder="Tối thiểu 8 ký tự">
                        @error('password') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">Xác nhận mật khẩu</label>
                        <p class="text-[11px] text-slate-400 mb-1">&nbsp;</p>
                        <input type="password" name="password_confirmation" class="form-input" placeholder="Nhập lại mật khẩu">
                    </div>
                </div>
            </div>

            {{-- Role selection --}}
            <div class="space-y-2">
                <h4 class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Vai trò <span class="text-red-500">*</span></h4>
                @error('role') <p class="form-error">{{ $message }}</p> @enderror
                <div class="grid grid-cols-2 gap-2" id="editUserRoleList">
                    @php
                        $roleLabels = [
                            'admin'       => ['Quản trị viên', 'Toàn quyền hệ thống', 'bi-shield-fill', 'text-red-600 dark:text-red-400', 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800'],
                            'manager'     => ['Quản lý', 'Duyệt phiếu, quản lý nhân sự', 'bi-person-badge-fill', 'text-blue-600 dark:text-blue-400', 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800'],
                            'team_leader' => ['Trưởng nhóm', 'Tạo phiếu phạt, xem nhân viên', 'bi-people-fill', 'text-yellow-600 dark:text-yellow-400', 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800'],
                            'staff'       => ['Nhân viên', 'Xem cơ bản', 'bi-person-fill', 'text-slate-600 dark:text-slate-400', 'bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700'],
                        ];
                    @endphp
                    @foreach($roles as $role)
                        @php $meta = $roleLabels[$role->name] ?? [$role->name, '', 'bi-shield', 'text-slate-500', 'bg-slate-50 dark:bg-slate-800 border-slate-200']; @endphp
                        <label class="edit-role-card flex items-center gap-3 p-3 rounded-xl border-2 cursor-pointer transition-all border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600"
                               data-role="{{ $role->name }}" data-colors="{{ $meta[4] }}">
                            <input type="radio" name="role" value="{{ $role->name }}" class="sr-only">
                            <span class="w-7 h-7 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center shrink-0">
                                <i class="{{ $meta[2] }} {{ $meta[3] }} text-xs"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="font-semibold text-xs text-slate-800 dark:text-slate-200">{{ $meta[0] }}</p>
                                <p class="text-[10px] text-slate-400 leading-tight">{{ $meta[1] }}</p>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('editUserModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-primary"><i class="bi bi-floppy"></i> Cập nhật</button>
            </div>
        </form>
    </div>
</div>
