<div id="createBranchModal" class="hidden fixed inset-0 bg-black/50 z-50 items-center justify-center p-4"
     onclick="if(event.target===this)closeModal('createBranchModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-lg">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-building-add text-pcrm-600"></i> Thêm chi nhánh
            </h3>
            <button onclick="closeModal('createBranchModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form action="{{ route('branches.store') }}" method="POST" class="px-6 py-5 space-y-4">
            @csrf
            <input type="hidden" name="_modal" value="createBranchModal">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Mã chi nhánh <span class="text-red-500">*</span></label>
                    <input type="text" name="code" class="form-input" value="{{ old('code') }}" placeholder="VD: BR-HUE" required>
                    @error('code') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Số điện thoại</label>
                    <input type="text" name="phone" class="form-input" value="{{ old('phone') }}" placeholder="VD: 0236123456">
                    @error('phone') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="form-label">Tên chi nhánh <span class="text-red-500">*</span></label>
                <input type="text" name="name" class="form-input" value="{{ old('name') }}" placeholder="VD: Chi nhánh Huế" required>
                @error('name') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">Địa chỉ</label>
                <textarea name="address" rows="2" class="form-input" placeholder="Địa chỉ chi nhánh...">{{ old('address') }}</textarea>
                @error('address') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}
                       class="rounded border-slate-300 dark:border-slate-600 text-pcrm-600">
                <span class="text-sm text-slate-700 dark:text-slate-300">Đang hoạt động</span>
            </label>
            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('createBranchModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-primary"><i class="bi bi-floppy"></i> Lưu</button>
            </div>
        </form>
    </div>
</div>
