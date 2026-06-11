<div id="editRewardCategoryModal" class="hidden fixed inset-0 bg-black/50 z-50 items-center justify-center p-4"
     onclick="if(event.target===this)closeModal('editRewardCategoryModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-lg max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 shrink-0">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-pencil-square text-amber-500"></i> Sửa danh mục thưởng
            </h3>
            <button onclick="closeModal('editRewardCategoryModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form id="editRewardCategoryForm" method="POST" class="px-6 py-5 space-y-4 overflow-y-auto">
            @csrf @method('PUT')
            <input type="hidden" name="_modal" value="editRewardCategoryModal">
            <input type="hidden" name="_edit_id" id="editRewardCategoryId">

            <div>
                <label class="form-label">Tên danh mục <span class="text-red-500">*</span></label>
                <input type="text" id="editRewardCategoryName" name="name" class="form-input" required>
                @error('name') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Mô tả</label>
                <textarea id="editRewardCategoryDesc" name="description" class="form-input" rows="3"></textarea>
                @error('description') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center pb-1">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" id="editRewardCategoryActive" name="is_active" value="1"
                           class="rounded border-slate-300 dark:border-slate-600 text-pcrm-600">
                    <span class="text-sm text-slate-700 dark:text-slate-300">Đang áp dụng</span>
                </label>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('editRewardCategoryModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-primary"><i class="bi bi-floppy"></i> Cập nhật</button>
            </div>
        </form>
    </div>
</div>
