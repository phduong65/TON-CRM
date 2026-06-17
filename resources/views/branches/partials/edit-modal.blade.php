<div id="editBranchModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('editBranchModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-lg">
        <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-pencil-square text-amber-500"></i> Sửa chi nhánh
            </h3>
            <button onclick="closeModal('editBranchModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form id="editBranchForm" method="POST" class="px-4 sm:px-6 py-4 sm:py-5 space-y-4">
            @csrf @method('PUT')
            <input type="hidden" name="_modal" value="editBranchModal">
            <input type="hidden" id="editBranchId" name="_edit_id">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Mã chi nhánh <span class="text-red-500">*</span></label>
                    <input type="text" id="editBranchCode" name="code" class="form-input" required>
                    @error('code') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Số điện thoại</label>
                    <input type="text" id="editBranchPhone" name="phone" class="form-input">
                    @error('phone') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="form-label">Tên chi nhánh <span class="text-red-500">*</span></label>
                <input type="text" id="editBranchName" name="name" class="form-input" required>
                @error('name') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">Địa chỉ</label>
                <textarea id="editBranchAddress" name="address" rows="2" class="form-input"></textarea>
                @error('address') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" id="editBranchActive" name="is_active" value="1"
                       class="rounded border-slate-300 dark:border-slate-600 text-pcrm-600">
                <span class="text-sm text-slate-700 dark:text-slate-300">Đang hoạt động</span>
            </label>
            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('editBranchModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-primary"><i class="bi bi-floppy"></i> Cập nhật</button>
            </div>
        </form>
    </div>
</div>
