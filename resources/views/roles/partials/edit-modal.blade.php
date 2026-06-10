<div id="editRoleModal" class="hidden fixed inset-0 bg-black/50 z-50 items-center justify-center p-4"
     onclick="if(event.target===this)closeModal('editRoleModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-3xl max-h-[92vh] flex flex-col">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 shrink-0">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-pencil-square text-amber-500"></i> Sửa vai trò
            </h3>
            <button onclick="closeModal('editRoleModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form id="editRoleForm" method="POST" class="px-6 py-5 space-y-5 overflow-y-auto">
            @csrf @method('PUT')
            <input type="hidden" name="_modal" value="editRoleModal">
            <input type="hidden" name="_edit_id" id="editRoleId">
            <div>
                <label class="form-label">Tên vai trò <span class="text-red-500">*</span></label>
                <input type="text" id="editRoleName" name="name" class="form-input max-w-sm" required>
                <p class="text-[11px] text-slate-400 mt-1">Chỉ dùng chữ thường và dấu gạch dưới</p>
                @error('name') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Quyền hạn</h4>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="editRoleSelectAll()" class="text-xs text-pcrm-600 dark:text-pcrm-400 hover:underline">Chọn tất cả</button>
                        <span class="text-slate-300 dark:text-slate-600">|</span>
                        <button type="button" onclick="editRoleDeselectAll()" class="text-xs text-slate-500 hover:underline">Bỏ chọn</button>
                    </div>
                </div>
                <div class="space-y-4">
                    @foreach($permissionGroups as $groupName => $perms)
                    <div>
                        <p class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">{{ $groupName }}</p>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-1.5">
                            @foreach($perms as $permKey => $permLabel)
                                <label class="flex items-start gap-2 p-2 rounded-lg border border-slate-200 dark:border-slate-700 cursor-pointer
                                              hover:border-pcrm-300 dark:hover:border-pcrm-600 transition-colors text-xs">
                                    <input type="checkbox" name="permissions[]" value="{{ $permKey }}"
                                           class="edit-role-perm rounded border-slate-300 dark:border-slate-600 text-pcrm-600 mt-0.5 shrink-0"
                                           data-perm="{{ $permKey }}">
                                    <div class="min-w-0">
                                        <p class="font-medium text-slate-700 dark:text-slate-300 leading-tight">{{ $permLabel }}</p>
                                        <p class="text-[10px] text-slate-400 font-mono leading-tight">{{ $permKey }}</p>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center justify-between pt-2 border-t border-slate-200 dark:border-slate-700">
                <p class="text-sm text-slate-500">Đã chọn: <span id="editRolePermCount" class="font-semibold text-pcrm-600">0</span> quyền</p>
                <div class="flex items-center gap-3">
                    <button type="button" onclick="closeModal('editRoleModal')" class="btn-secondary">Hủy</button>
                    <button type="submit" class="btn-primary"><i class="bi bi-floppy"></i> Cập nhật</button>
                </div>
            </div>
        </form>
    </div>
</div>
