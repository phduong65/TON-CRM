<div id="editRegulationModal" class="hidden fixed inset-0 bg-black/50 z-50 items-center justify-center p-4"
     onclick="if(event.target===this)closeModal('editRegulationModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 shrink-0">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-pencil-square text-amber-500"></i> Sửa quy chế xử phạt
            </h3>
            <button onclick="closeModal('editRegulationModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form id="editRegulationForm" method="POST" class="px-6 py-5 space-y-4 overflow-y-auto">
            @csrf @method('PUT')
            <input type="hidden" name="_modal" value="editRegulationModal">
            <input type="hidden" name="_edit_id" id="editRegId">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Mã quy chế <span class="text-red-500">*</span></label>
                    <input type="text" id="editRegCode" name="code" class="form-input" required>
                    @error('code') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Loại phạt <span class="text-red-500">*</span></label>
                    <select id="editRegType" name="type" class="form-input" required onchange="toggleRegMoneyField(this.value, 'edit')">
                        <option value="">-- Chọn loại --</option>
                        <option value="points">Trừ điểm</option>
                        <option value="money">Phạt tiền</option>
                        <option value="both">Cả hai</option>
                    </select>
                    @error('type') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="form-label">Tên quy chế <span class="text-red-500">*</span></label>
                <input type="text" id="editRegName" name="name" class="form-input" required>
                @error('name') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">Mô tả</label>
                <textarea id="editRegDesc" name="description" class="form-input" rows="2"></textarea>
                @error('description') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div id="editRegPoints">
                    <label class="form-label">Điểm trừ mặc định</label>
                    <input type="number" id="editRegDefaultPoints" name="default_points" class="form-input" min="0" max="100">
                    @error('default_points') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div id="editRegMoney">
                    <label class="form-label">Tiền phạt mặc định (₫)</label>
                    <input type="number" id="editRegDefaultMoney" name="default_money" class="form-input" min="0" step="1000">
                    @error('default_money') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Hiệu lực từ ngày</label>
                    <input type="date" id="editRegEffective" name="effective_date" class="form-input">
                    @error('effective_date') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div class="flex items-end pb-1">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="editRegActive" name="is_active" value="1"
                               class="rounded border-slate-300 dark:border-slate-600 text-pcrm-600">
                        <span class="text-sm text-slate-700 dark:text-slate-300">Đang áp dụng</span>
                    </label>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('editRegulationModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-primary"><i class="bi bi-floppy"></i> Cập nhật</button>
            </div>
        </form>
    </div>
</div>
