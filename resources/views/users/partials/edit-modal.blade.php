@php $isEditUserErr = old('_modal') === 'editUserModal'; @endphp
<div id="editUserModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('editUserModal')">
    <div class="pcrm-dialog max-w-3xl" role="dialog" aria-modal="true" aria-labelledby="editUserModalTitle">
        <div class="pcrm-dialog-head">
            <span id="editUserModalIconWrap" class="pcrm-dialog-icon bg-[#fef3dc] text-[#C98219] dark:bg-[#C98219]/20 dark:text-[#fbbf24]" aria-hidden="true">
                <i id="editUserModalIcon" class="bi bi-pencil-square"></i>
            </span>
            <div class="min-w-0 flex-1">
                <h3 id="editUserModalTitle" class="pcrm-dialog-title">Sửa người dùng</h3>
                <p id="editUserModalSub" class="pcrm-dialog-sub truncate"></p>
            </div>
            <button type="button" onclick="closeModal('editUserModal')" class="pcrm-dialog-close" aria-label="Đóng">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <form id="editUserForm" method="POST" class="pcrm-dialog-form" data-user-form>
            @csrf @method('PUT')
            <input type="hidden" name="_modal" value="editUserModal">
            <input type="hidden" name="_edit_id" id="editUserId">
            {{-- Báo cho UsersController::update() biết form có mục quyền riêng → đồng bộ quyền riêng
                 theo đúng các ô đã chọn (kể cả bỏ hết). Thiếu cờ này thì giữ nguyên quyền riêng. --}}
            <input type="hidden" name="sync_permissions" value="1">

            <div class="pcrm-dialog-body">
                <p id="editUserPendingHint" class="hidden pcrm-callout pcrm-callout-warning">
                    <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                    <span>Tài khoản tự đăng ký đang <strong>chờ duyệt</strong>. Chọn vai trò rồi bấm "Duyệt &amp; kích hoạt" để cho phép đăng nhập.</span>
                </p>

                <section class="pcrm-form-section">
                    <h4 class="pcrm-form-section-title"><span class="pcrm-step">1</span> Thông tin tài khoản</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="editUserName" class="form-label">Họ và tên <span class="text-red-500">*</span></label>
                            <input type="text" id="editUserName" name="name" class="form-input" autocomplete="off" required>
                            @if ($isEditUserErr) @error('name') <p class="form-error">{{ $message }}</p> @enderror @endif
                        </div>
                        <div>
                            <label for="editUserEmail" class="form-label">Email đăng nhập <span class="text-red-500">*</span></label>
                            <input type="email" id="editUserEmail" name="email" class="form-input" autocomplete="off" required>
                            @if ($isEditUserErr) @error('email') <p class="form-error">{{ $message }}</p> @enderror @endif
                        </div>
                        <div>
                            <label for="editUserPassword" class="form-label">Mật khẩu mới</label>
                            <input type="password" id="editUserPassword" name="password" class="form-input" autocomplete="new-password"
                                   placeholder="Để trống nếu không đổi" minlength="8" aria-describedby="editUserPasswordHelp">
                            <p id="editUserPasswordHelp" class="pcrm-help">Tối thiểu 8 ký tự</p>
                            @if ($isEditUserErr) @error('password') <p class="form-error">{{ $message }}</p> @enderror @endif
                        </div>
                        <div>
                            <label for="editUserPasswordConfirm" class="form-label">Xác nhận mật khẩu mới</label>
                            <input type="password" id="editUserPasswordConfirm" name="password_confirmation" class="form-input"
                                   autocomplete="new-password" placeholder="Nhập lại mật khẩu mới">
                        </div>
                    </div>
                </section>

                <section class="pcrm-form-section">
                    <h4 class="pcrm-form-section-title"><span class="pcrm-step">2</span> Vai trò <span class="text-red-500">*</span></h4>
                    @if ($isEditUserErr) @error('role') <p class="form-error">{{ $message }}</p> @enderror @endif
                    @include('users.partials.role-options', ['selectedRole' => null])
                </section>

                <details id="editUserPermsDisclosure" class="pcrm-form-section pcrm-disclosure">
                    <summary>
                        <span class="pcrm-form-section-title"><span class="pcrm-step">3</span> Quyền riêng <span class="font-normal text-slate-400">(tuỳ chọn)</span></span>
                        <span class="pcrm-help mt-0">Cấp thêm quyền ngoài vai trò — quyền đã có qua vai trò được đánh dấu và khoá.</span>
                    </summary>
                    <x-permission-picker id="editUserPerms" class="mt-3" :groups="$permissionGroups" />
                </details>
            </div>

            <div class="pcrm-dialog-foot">
                <p class="text-sm text-slate-500 dark:text-slate-400"><strong data-perm-total class="text-[#2F55E7] dark:text-[#809ff9] tabular-nums">0</strong> quyền riêng</p>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="closeModal('editUserModal')" class="btn-secondary">Hủy</button>
                    <button type="submit" id="editUserSubmitBtn" class="btn-primary"><i class="bi bi-check2"></i> Lưu thay đổi</button>
                </div>
            </div>
        </form>
    </div>
</div>
