@php $isCreateNotifErr = old('_modal') === 'createNotificationModal'; @endphp
<div id="createNotificationModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('createNotificationModal')">
    <div class="pcrm-dialog max-w-lg" role="dialog" aria-modal="true" aria-labelledby="createNotifTitle">
        <div class="pcrm-dialog-head">
            <span class="pcrm-dialog-icon bg-[#E9EEFF] text-[#2F55E7] dark:bg-[#2F55E7]/20 dark:text-[#809ff9]" aria-hidden="true"><i class="bi bi-send"></i></span>
            <div class="min-w-0 flex-1">
                <h3 id="createNotifTitle" class="pcrm-dialog-title">Gửi thông báo</h3>
                <p class="pcrm-dialog-sub">Thông báo xuất hiện trong hộp thư và chuông thông báo của người nhận</p>
            </div>
            <button type="button" onclick="closeModal('createNotificationModal')" class="pcrm-dialog-close" aria-label="Đóng"><i class="bi bi-x-lg"></i></button>
        </div>

        <form action="{{ route('notifications.store') }}" method="POST" class="pcrm-dialog-form">
            @csrf
            <input type="hidden" name="_modal" value="createNotificationModal">
            @php $target = $isCreateNotifErr ? old('target', 'all') : 'all'; @endphp

            <div class="pcrm-dialog-body">
                <fieldset>
                    <legend class="form-label">Gửi đến <span class="text-red-500">*</span></legend>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <label class="role-option">
                            <input type="radio" name="target" value="all" class="sr-only" @checked($target === 'all') onchange="toggleNotifTarget(this.value)">
                            <span class="role-option-icon text-[#2F55E7]" aria-hidden="true"><i class="bi bi-people-fill"></i></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold text-slate-900 dark:text-white">Tất cả người dùng</span>
                                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $users->count() }} tài khoản</span>
                            </span>
                            <i class="bi bi-check-circle-fill role-option-check" aria-hidden="true"></i>
                        </label>
                        <label class="role-option">
                            <input type="radio" name="target" value="user" class="sr-only" @checked($target === 'user') onchange="toggleNotifTarget(this.value)">
                            <span class="role-option-icon text-slate-500" aria-hidden="true"><i class="bi bi-person-fill"></i></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold text-slate-900 dark:text-white">Một người cụ thể</span>
                                <span class="block text-xs text-slate-500 dark:text-slate-400">Chọn người nhận bên dưới</span>
                            </span>
                            <i class="bi bi-check-circle-fill role-option-check" aria-hidden="true"></i>
                        </label>
                    </div>
                    @if ($isCreateNotifErr) @error('target') <p class="form-error">{{ $message }}</p> @enderror @endif
                </fieldset>

                <div id="notifUserSelect" class="{{ $target === 'user' ? '' : 'hidden' }}">
                    <label for="notifUserId" class="form-label">Người nhận <span class="text-red-500">*</span></label>
                    <select id="notifUserId" name="user_id" class="form-input" data-combobox data-combobox-placeholder="Gõ tên hoặc email…">
                        <option value="">— Chọn người dùng —</option>
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}" @selected($isCreateNotifErr && old('user_id') == $u->id)>{{ $u->name }} ({{ $u->email }})</option>
                        @endforeach
                    </select>
                    @if ($isCreateNotifErr) @error('user_id') <p class="form-error">{{ $message }}</p> @enderror @endif
                </div>

                <div>
                    <label for="notifTitleInput" class="form-label">Tiêu đề <span class="text-red-500">*</span></label>
                    <input id="notifTitleInput" type="text" name="title" class="form-input" maxlength="255" required
                           value="{{ $isCreateNotifErr ? old('title') : '' }}" placeholder="VD: Lịch họp toàn nhân viên tuần sau">
                    @if ($isCreateNotifErr) @error('title') <p class="form-error">{{ $message }}</p> @enderror @endif
                </div>

                <div>
                    <label for="notifBodyInput" class="form-label">Nội dung</label>
                    <textarea id="notifBodyInput" name="body" class="form-input" rows="4" maxlength="1000"
                              placeholder="Nội dung chi tiết (tuỳ chọn)…">{{ $isCreateNotifErr ? old('body') : '' }}</textarea>
                    <p class="pcrm-help">Tối đa 1000 ký tự.</p>
                    @if ($isCreateNotifErr) @error('body') <p class="form-error">{{ $message }}</p> @enderror @endif
                </div>
            </div>

            <div class="pcrm-dialog-foot justify-end">
                <div class="flex items-center gap-2">
                    <button type="button" onclick="closeModal('createNotificationModal')" class="btn-secondary">Hủy</button>
                    <button type="submit" class="btn-primary"><i class="bi bi-send"></i> Gửi thông báo</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function toggleNotifTarget(val) {
    const box = document.getElementById('notifUserSelect');
    box.classList.toggle('hidden', val !== 'user');
    document.getElementById('notifUserId').required = val === 'user';
}
</script>
