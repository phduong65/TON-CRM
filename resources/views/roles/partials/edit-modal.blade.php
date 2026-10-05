@php $isEditRoleErr = old('_modal') === 'editRoleModal'; @endphp
<div id="editRoleModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('editRoleModal')">
    <div class="pcrm-dialog max-w-4xl" role="dialog" aria-modal="true" aria-labelledby="editRoleTitle">
        <div class="pcrm-dialog-head">
            <span class="pcrm-dialog-icon bg-[#fef3dc] text-[#C98219] dark:bg-[#C98219]/20 dark:text-[#fbbf24]" aria-hidden="true">
                <i class="bi bi-pencil-square"></i>
            </span>
            <div class="min-w-0 flex-1">
                <h3 id="editRoleTitle" class="pcrm-dialog-title">Sửa vai trò <span id="editRoleTitleName" class="font-mono text-[#2F55E7] dark:text-[#809ff9]"></span></h3>
                <p class="pcrm-dialog-sub">Thay đổi quyền áp dụng ngay cho mọi người dùng thuộc vai trò này</p>
            </div>
            <button type="button" onclick="closeModal('editRoleModal')" class="pcrm-dialog-close" aria-label="Đóng">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <form id="editRoleForm" method="POST" class="pcrm-dialog-form">
            @csrf @method('PUT')
            <input type="hidden" name="_modal" value="editRoleModal">
            <input type="hidden" name="_edit_id" id="editRoleId">

            <div class="pcrm-dialog-body">
                <section class="pcrm-form-section">
                    <label for="editRoleName" class="form-label">Tên vai trò <span class="text-red-500">*</span></label>
                    <input type="text" id="editRoleName" name="name" class="form-input sm:max-w-sm" required aria-describedby="editRoleNameHelp">
                    <p id="editRoleNameHelp" class="pcrm-help">Chữ thường và dấu gạch dưới</p>
                    @if ($isEditRoleErr) @error('name') <p class="form-error">{{ $message }}</p> @enderror @endif
                </section>

                <section class="pcrm-form-section">
                    <div class="pcrm-form-section-head">
                        <h4 class="pcrm-form-section-title">Quyền hạn</h4>
                        <p class="pcrm-help mt-0">Bỏ chọn một quyền sẽ thu hồi quyền đó của tất cả người dùng có vai trò này (trừ khi họ được cấp riêng).</p>
                    </div>
                    @if ($isEditRoleErr) @error('permissions') <p class="form-error">{{ $message }}</p> @enderror @endif
                    <x-permission-picker id="editRolePerms" :groups="$permissionGroups" />
                </section>
            </div>

            <div class="pcrm-dialog-foot">
                <p class="text-sm text-slate-500 dark:text-slate-400">Đã chọn <strong data-perm-total class="text-[#2F55E7] dark:text-[#809ff9] tabular-nums">0</strong> quyền</p>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="closeModal('editRoleModal')" class="btn-secondary">Hủy</button>
                    <button type="submit" class="btn-primary"><i class="bi bi-check2"></i> Lưu thay đổi</button>
                </div>
            </div>
        </form>
    </div>
</div>
