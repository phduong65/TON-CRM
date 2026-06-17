<div id="editRewardTypeModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('editRewardTypeModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-lg flex flex-col">
        <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 dark:border-slate-700 shrink-0">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-pencil text-pcrm-600"></i> Chỉnh sửa loại thưởng
            </h3>
            <button onclick="closeModal('editRewardTypeModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form id="editRewardTypeForm" action="" method="POST" class="px-4 sm:px-6 py-4 sm:py-5 space-y-4 overflow-y-auto">
            @csrf
            @method('PUT')
            <input type="hidden" id="editRewardTypeId" name="id">
            <input type="hidden" name="_modal" value="editRewardTypeModal">

            <div>
                <label class="form-label">Danh mục thưởng</label>
                <select id="editRewardTypeCategoryId" name="reward_category_id" class="form-input">
                    <option value="">— Không phân loại —</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
                @error('reward_category_id') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Tên loại thưởng <span class="text-red-500">*</span></label>
                <input type="text" id="editRewardTypeName" name="name" class="form-input" required>
                @error('name') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Mô tả</label>
                <textarea id="editRewardTypeDescription" name="description" class="form-input" rows="2"></textarea>
                @error('description') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Điểm thưởng mặc định <span class="text-red-500">*</span></label>
                <input type="number" id="editRewardTypeDefaultPoints" name="default_points" class="form-input" min="0" max="9999" required>
                @error('default_points') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center pb-1">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" id="editRewardTypeIsActive" name="is_active" value="1"
                           class="rounded border-slate-300 dark:border-slate-600 text-pcrm-600">
                    <span class="text-sm text-slate-700 dark:text-slate-300">Đang áp dụng</span>
                </label>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('editRewardTypeModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-primary"><i class="bi bi-floppy"></i> Cập nhật</button>
            </div>
        </form>
    </div>
</div>
