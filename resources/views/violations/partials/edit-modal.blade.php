<div id="editViolationModal" class="hidden fixed inset-0 bg-black/50 z-50 items-center justify-center p-4"
     onclick="if(event.target===this)closeModal('editViolationModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 shrink-0">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-pencil-square text-amber-500"></i> Sửa lỗi vi phạm
            </h3>
            <button onclick="closeModal('editViolationModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form id="editViolationForm" method="POST" class="px-6 py-5 space-y-4 overflow-y-auto">
            @csrf @method('PUT')
            <input type="hidden" name="_modal" value="editViolationModal">
            <input type="hidden" name="_edit_id" id="editViolationId">

            <div>
                <label class="form-label">Quy chế <span class="text-red-500">*</span></label>
                <select id="editViolationRegulation" name="regulation_id" class="form-input" required>
                    <option value="">-- Chọn quy chế --</option>
                    @foreach($regulations as $reg)
                        <option value="{{ $reg->id }}">{{ $reg->name }}</option>
                    @endforeach
                </select>
                @error('regulation_id') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Tên vi phạm <span class="text-red-500">*</span></label>
                <input type="text" id="editViolationName" name="name" class="form-input" required>
                @error('name') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Mô tả</label>
                <textarea id="editViolationDesc" name="description" class="form-input" rows="2"></textarea>
                @error('description') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Mức độ <span class="text-red-500">*</span></label>
                    <select id="editViolationSeverity" name="severity" class="form-input" required>
                        <option value="low">Nhẹ</option>
                        <option value="medium">Trung bình</option>
                        <option value="high">Nặng</option>
                        <option value="critical">Nghiêm trọng</option>
                    </select>
                    @error('severity') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Hình thức xử phạt <span class="text-red-500">*</span></label>
                    <select id="editViolationPenaltyType" name="penalty_type" class="form-input" required
                            onchange="toggleViolationPenaltyFields(this.value, 'edit')">
                        <option value="points">Trừ điểm</option>
                        <option value="money">Phạt tiền</option>
                        <option value="both">Cả hai</option>
                    </select>
                    @error('penalty_type') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div id="editViolationPoints">
                    <label class="form-label">Số điểm trừ</label>
                    <input type="number" id="editViolationPointsVal" name="points_deducted"
                           class="form-input" min="0" max="100">
                    @error('points_deducted') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div id="editViolationMoney">
                    <label class="form-label">Số tiền phạt (₫)</label>
                    <input type="number" id="editViolationMoneyVal" name="money_deducted"
                           class="form-input" min="0" step="1000">
                    @error('money_deducted') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex items-center pb-1">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" id="editViolationActive" name="is_active" value="1"
                           class="rounded border-slate-300 dark:border-slate-600 text-pcrm-600">
                    <span class="text-sm text-slate-700 dark:text-slate-300">Đang áp dụng</span>
                </label>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('editViolationModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-primary"><i class="bi bi-floppy"></i> Cập nhật</button>
            </div>
        </form>
    </div>
</div>
