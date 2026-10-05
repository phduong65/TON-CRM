@php $isCreateRoleErr = old('_modal') === 'createRoleModal'; @endphp
<div id="createRoleModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('createRoleModal')">
    <div class="pcrm-dialog max-w-4xl" role="dialog" aria-modal="true" aria-labelledby="createRoleTitle">
        <div class="pcrm-dialog-head">
            <span class="pcrm-dialog-icon bg-[#E9EEFF] text-[#2F55E7] dark:bg-[#2F55E7]/20 dark:text-[#809ff9]" aria-hidden="true">
                <i class="bi bi-shield-plus"></i>
            </span>
            <div class="min-w-0 flex-1">
                <h3 id="createRoleTitle" class="pcrm-dialog-title">Thêm vai trò</h3>
                <p class="pcrm-dialog-sub">Đặt tên vai trò và chọn những việc vai trò này được phép làm</p>
            </div>
            <button type="button" onclick="closeModal('createRoleModal')" class="pcrm-dialog-close" aria-label="Đóng">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <form action="{{ route('roles.store') }}" method="POST" class="pcrm-dialog-form">
            @csrf
            <input type="hidden" name="_modal" value="createRoleModal">

            <div class="pcrm-dialog-body">
                <section class="pcrm-form-section">
                    <label for="createRoleName" class="form-label">Tên vai trò <span class="text-red-500">*</span></label>
                    <input type="text" id="createRoleName" name="name" class="form-input sm:max-w-sm"
                           value="{{ $isCreateRoleErr ? old('name') : '' }}" placeholder="VD: supervisor" required
                           aria-describedby="createRoleNameHelp">
                    <p id="createRoleNameHelp" class="pcrm-help">Chữ thường và dấu gạch dưới, VD: <span class="font-mono">team_leader</span></p>
                    @if ($isCreateRoleErr) @error('name') <p class="form-error">{{ $message }}</p> @enderror @endif
                </section>

                <section class="pcrm-form-section">
                    <div class="pcrm-form-section-head">
                        <h4 class="pcrm-form-section-title">Quyền hạn</h4>
                        <p class="pcrm-help mt-0">Mỗi nhóm tương ứng một module; dùng "Chọn nhóm" để cấp nhanh toàn bộ quyền của module.</p>
                    </div>
                    @if ($isCreateRoleErr) @error('permissions') <p class="form-error">{{ $message }}</p> @enderror @endif
                    <x-permission-picker id="createRolePerms" :groups="$permissionGroups"
                                         :checked="$isCreateRoleErr ? old('permissions', []) : []" />
                </section>
            </div>

            <div class="pcrm-dialog-foot">
                <p class="text-sm text-slate-500 dark:text-slate-400">Đã chọn <strong data-perm-total class="text-[#2F55E7] dark:text-[#809ff9] tabular-nums">0</strong> quyền</p>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="closeModal('createRoleModal')" class="btn-secondary">Hủy</button>
                    <button type="submit" class="btn-primary"><i class="bi bi-check2"></i> Tạo vai trò</button>
                </div>
            </div>
        </form>
    </div>
</div>
