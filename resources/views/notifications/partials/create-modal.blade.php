<div id="createNotificationModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('createNotificationModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-lg max-h-[95vh] flex flex-col">
        <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 dark:border-slate-700 shrink-0">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-send-plus text-pcrm-600"></i> Tạo thông báo
            </h3>
            <button onclick="closeModal('createNotificationModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form action="{{ route('notifications.store') }}" method="POST" class="px-4 sm:px-6 py-4 sm:py-5 space-y-4 overflow-y-auto">
            @csrf
            <input type="hidden" name="_modal" value="createNotificationModal">

            <div>
                <label class="form-label">Gửi đến <span class="text-red-500">*</span></label>
                <div class="flex gap-3">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="target" value="all" {{ old('target', 'all') === 'all' ? 'checked' : '' }}
                               onchange="toggleNotifTarget(this.value)"
                               class="text-pcrm-600">
                        <span class="text-sm text-slate-700 dark:text-slate-300">
                            <i class="bi bi-people-fill mr-1 text-pcrm-500"></i>Tất cả người dùng
                        </span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="target" value="user" {{ old('target') === 'user' ? 'checked' : '' }}
                               onchange="toggleNotifTarget(this.value)"
                               class="text-pcrm-600">
                        <span class="text-sm text-slate-700 dark:text-slate-300">
                            <i class="bi bi-person-fill mr-1 text-slate-400"></i>Người dùng cụ thể
                        </span>
                    </label>
                </div>
                @error('target') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div id="notifUserSelect" class="{{ old('target') === 'user' ? '' : 'hidden' }}">
                <label class="form-label">Chọn người nhận <span class="text-red-500">*</span></label>
                <select name="user_id" class="form-input">
                    <option value="">-- Chọn người dùng --</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" {{ old('user_id') == $u->id ? 'selected' : '' }}>
                            {{ $u->name }} ({{ $u->email }})
                        </option>
                    @endforeach
                </select>
                @error('user_id') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Tiêu đề <span class="text-red-500">*</span></label>
                <input type="text" name="title" class="form-input" value="{{ old('title') }}"
                       placeholder="Tiêu đề thông báo..." required>
                @error('title') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Nội dung</label>
                <textarea name="body" class="form-input" rows="4"
                          placeholder="Nội dung chi tiết (tuỳ chọn)...">{{ old('body') }}</textarea>
                @error('body') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('createNotificationModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-primary"><i class="bi bi-send"></i> Gửi thông báo</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function toggleNotifTarget(val) {
    document.getElementById('notifUserSelect').classList.toggle('hidden', val !== 'user');
}
</script>
@endpush
