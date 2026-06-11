<div id="createViolationModal" class="hidden fixed inset-0 bg-black/50 z-50 items-center justify-center p-4"
     onclick="if(event.target===this)closeModal('createViolationModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 shrink-0">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-plus-circle text-pcrm-600"></i> Thêm lỗi vi phạm
            </h3>
            <button onclick="closeModal('createViolationModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form action="{{ route('violations.store') }}" method="POST" class="px-6 py-5 space-y-4 overflow-y-auto">
            @csrf
            <input type="hidden" name="_modal" value="createViolationModal">

            <div>
                <label class="form-label">Quy chế <span class="text-red-500">*</span></label>
                <select name="regulation_id" class="form-input" required>
                    <option value="">-- Chọn quy chế --</option>
                    @foreach($regulations as $reg)
                        <option value="{{ $reg->id }}" {{ old('regulation_id') == $reg->id ? 'selected' : '' }}>
                            {{ $reg->name }}
                        </option>
                    @endforeach
                </select>
                @error('regulation_id') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Tên vi phạm <span class="text-red-500">*</span></label>
                <input type="text" name="name" class="form-input" value="{{ old('name') }}" placeholder="VD: Đi trễ không báo cáo" required>
                @error('name') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="form-label">Mô tả</label>
                <textarea name="description" class="form-input" rows="2" placeholder="Mô tả chi tiết...">{{ old('description') }}</textarea>
                @error('description') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Mức độ <span class="text-red-500">*</span></label>
                    <select name="severity" class="form-input" required>
                        <option value="low"      {{ old('severity') === 'low'      ? 'selected' : '' }}>Nhẹ</option>
                        <option value="medium"   {{ old('severity', 'medium') === 'medium' ? 'selected' : '' }}>Trung bình</option>
                        <option value="high"     {{ old('severity') === 'high'     ? 'selected' : '' }}>Nặng</option>
                        <option value="critical" {{ old('severity') === 'critical' ? 'selected' : '' }}>Nghiêm trọng</option>
                    </select>
                    @error('severity') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Hình thức xử phạt <span class="text-red-500">*</span></label>
                    <select name="penalty_type" class="form-input" required
                            onchange="toggleViolationPenaltyFields(this.value, 'create')">
                        <option value="points" {{ old('penalty_type', 'points') === 'points' ? 'selected' : '' }}>Trừ điểm</option>
                        <option value="money"  {{ old('penalty_type') === 'money'  ? 'selected' : '' }}>Phạt tiền</option>
                        <option value="both"   {{ old('penalty_type') === 'both'   ? 'selected' : '' }}>Cả hai</option>
                    </select>
                    @error('penalty_type') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div id="createViolationPoints" class="{{ old('penalty_type') === 'money' ? 'hidden' : '' }}">
                    <label class="form-label">Số điểm trừ</label>
                    <input type="number" name="points_deducted" class="form-input"
                           value="{{ old('points_deducted', 0) }}" min="0" max="100">
                    @error('points_deducted') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div id="createViolationMoney" class="{{ old('penalty_type', 'points') === 'points' ? 'hidden' : '' }}">
                    <label class="form-label">Số tiền phạt (₫)</label>
                    <input type="number" name="money_deducted" class="form-input"
                           value="{{ old('money_deducted', 0) }}" min="0" step="1000">
                    @error('money_deducted') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex items-center pb-1">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}
                           class="rounded border-slate-300 dark:border-slate-600 text-pcrm-600">
                    <span class="text-sm text-slate-700 dark:text-slate-300">Đang áp dụng</span>
                </label>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('createViolationModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-primary"><i class="bi bi-floppy"></i> Lưu</button>
            </div>
        </form>
    </div>
</div>
