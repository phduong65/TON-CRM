@php $isCreateUserErr = old('_modal') === 'createUserModal'; @endphp
<div id="createUserModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('createUserModal')">
    <div class="pcrm-dialog max-w-3xl" role="dialog" aria-modal="true" aria-labelledby="createUserTitle">
        <div class="pcrm-dialog-head">
            <span class="pcrm-dialog-icon bg-[#E9EEFF] text-[#2F55E7] dark:bg-[#2F55E7]/20 dark:text-[#809ff9]" aria-hidden="true">
                <i class="bi bi-person-plus"></i>
            </span>
            <div class="min-w-0 flex-1">
                <h3 id="createUserTitle" class="pcrm-dialog-title">Thêm người dùng</h3>
                <p class="pcrm-dialog-sub">Tạo tài khoản đăng nhập và gán vai trò — tài khoản được kích hoạt ngay</p>
            </div>
            <button type="button" onclick="closeModal('createUserModal')" class="pcrm-dialog-close" aria-label="Đóng">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <form action="{{ route('users.store') }}" method="POST" class="pcrm-dialog-form" data-user-form>
            @csrf
            <input type="hidden" name="_modal" value="createUserModal">

            <div class="pcrm-dialog-body">
                <section class="pcrm-form-section">
                    <h4 class="pcrm-form-section-title"><span class="pcrm-step">1</span> Thông tin tài khoản</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="createUserName" class="form-label">Họ và tên <span class="text-red-500">*</span></label>
                            <input type="text" id="createUserName" name="name" class="form-input" autocomplete="off"
                                   value="{{ $isCreateUserErr ? old('name') : '' }}" placeholder="VD: Nguyễn Văn A" required>
                            @if ($isCreateUserErr) @error('name') <p class="form-error">{{ $message }}</p> @enderror @endif
                        </div>
                        <div>
                            <label for="createUserEmail" class="form-label">Email đăng nhập <span class="text-red-500">*</span></label>
                            <input type="email" id="createUserEmail" name="email" class="form-input" autocomplete="off"
                                   value="{{ $isCreateUserErr ? old('email') : '' }}" placeholder="email@congty.vn" required>
                            @if ($isCreateUserErr) @error('email') <p class="form-error">{{ $message }}</p> @enderror @endif
                        </div>
                        <div>
                            <label for="createUserPassword" class="form-label">Mật khẩu <span class="text-red-500">*</span></label>
                            <input type="password" id="createUserPassword" name="password" class="form-input" autocomplete="new-password"
                                   placeholder="Tối thiểu 8 ký tự" minlength="8" required>
                            @if ($isCreateUserErr) @error('password') <p class="form-error">{{ $message }}</p> @enderror @endif
                        </div>
                        <div>
                            <label for="createUserPasswordConfirm" class="form-label">Xác nhận mật khẩu <span class="text-red-500">*</span></label>
                            <input type="password" id="createUserPasswordConfirm" name="password_confirmation" class="form-input"
                                   autocomplete="new-password" placeholder="Nhập lại mật khẩu" required>
                        </div>
                    </div>
                </section>

                <section class="pcrm-form-section">
                    <h4 class="pcrm-form-section-title"><span class="pcrm-step">2</span> Vai trò <span class="text-red-500">*</span></h4>
                    <p class="pcrm-help mt-0">Vai trò quyết định tập quyền mặc định của người dùng.</p>
                    @if ($isCreateUserErr) @error('role') <p class="form-error">{{ $message }}</p> @enderror @endif
                    @include('users.partials.role-options', ['selectedRole' => $isCreateUserErr ? old('role') : null])
                </section>

                <details class="pcrm-form-section pcrm-disclosure" @if ($isCreateUserErr && old('permissions')) open @endif>
                    <summary>
                        <span class="pcrm-form-section-title"><span class="pcrm-step">3</span> Quyền riêng <span class="font-normal text-slate-400">(tuỳ chọn)</span></span>
                        <span class="pcrm-help mt-0">Cấp thêm quyền ngoài vai trò — quyền đã có qua vai trò được đánh dấu và khoá.</span>
                    </summary>
                    <x-permission-picker id="createUserPerms" class="mt-3" :groups="$permissionGroups"
                                         :checked="$isCreateUserErr ? old('permissions', []) : []" />
                </details>
            </div>

            <div class="pcrm-dialog-foot">
                <p class="text-sm text-slate-500 dark:text-slate-400"><strong data-perm-total class="text-[#2F55E7] dark:text-[#809ff9] tabular-nums">0</strong> quyền riêng</p>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="closeModal('createUserModal')" class="btn-secondary">Hủy</button>
                    <button type="submit" class="btn-primary"><i class="bi bi-check2"></i> Tạo người dùng</button>
                </div>
            </div>
        </form>
    </div>
</div>
