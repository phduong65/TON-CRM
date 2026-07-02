<div id="createShiftModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('createShiftModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-clock-history text-pcrm-600"></i> Thêm ca làm việc
            </h3>
            <button onclick="closeModal('createShiftModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form action="{{ route('shifts.store') }}" method="POST" class="px-4 sm:px-6 py-4 sm:py-5 space-y-4">
            @csrf
            <input type="hidden" name="_modal" value="createShiftModal">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Mã ca <span class="text-red-500">*</span></label>
                    <input type="text" name="code" class="form-input" value="{{ old('code') }}" placeholder="VD: CA-HC" required>
                    @error('code') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Chi nhánh</label>
                    <select name="branch_id" class="form-input">
                        <option value="">Mọi chi nhánh</option>
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}" @selected(old('branch_id') == $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div>
                <label class="form-label">Tên ca <span class="text-red-500">*</span></label>
                <input type="text" name="name" class="form-input" value="{{ old('name') }}" placeholder="VD: Ca hành chính" required>
                @error('name') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Giờ bắt đầu <span class="text-red-500">*</span></label>
                    <input type="time" name="start_time" class="form-input" value="{{ old('start_time') }}" required>
                    @error('start_time') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Giờ kết thúc <span class="text-red-500">*</span></label>
                    <input type="time" name="end_time" class="form-input" value="{{ old('end_time') }}" required>
                    @error('end_time') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="form-label">Nghỉ giữa ca (phút)</label>
                    <input type="number" name="break_minutes" class="form-input" value="{{ old('break_minutes', 0) }}" min="0">
                </div>
                <div>
                    <label class="form-label">Cho phép trễ (phút)</label>
                    <input type="number" name="grace_late_minutes" class="form-input" value="{{ old('grace_late_minutes', 0) }}" min="0">
                </div>
                <div>
                    <label class="form-label">Cho phép sớm (phút)</label>
                    <input type="number" name="grace_early_minutes" class="form-input" value="{{ old('grace_early_minutes', 0) }}" min="0">
                </div>
            </div>
            <div>
                <label class="form-label">Loại ca <span class="text-red-500">*</span></label>
                <select name="shift_type" class="form-input" required>
                    <option value="fulltime" @selected(old('shift_type', 'fulltime') === 'fulltime')>Full-time / Văn phòng (1 ca đủ vào-ra = 1 công)</option>
                    <option value="parttime" @selected(old('shift_type') === 'parttime')>Part-time (quy đổi công theo giờ chuẩn)</option>
                </select>
                @error('shift_type') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">Giờ công tiêu chuẩn (1 công = ? giờ) <span class="text-red-500">*</span></label>
                <input type="number" name="standard_work_hours" class="form-input" value="{{ old('standard_work_hours', 8) }}" min="1" max="24" step="0.5" required>
                <p class="text-xs text-slate-400 mt-1">Chỉ áp dụng cho ca Part-time để quy đổi giờ làm thực tế sang "công". VD: ca part-time cố định 10h/ngày = 2 công.</p>
                @error('standard_work_hours') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">Chế độ làm việc <span class="text-red-500">*</span></label>
                <select name="work_mode" class="form-input" required>
                    <option value="onsite" @selected(old('work_mode', 'onsite') === 'onsite')>Tại chỗ (văn phòng / nhà hàng)</option>
                    <option value="wfh" @selected(old('work_mode') === 'wfh')>WFH (làm việc từ xa)</option>
                </select>
            </div>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="is_overnight" value="1" {{ old('is_overnight') ? 'checked' : '' }}
                       class="rounded border-slate-300 dark:border-slate-600 text-pcrm-600">
                <span class="text-sm text-slate-700 dark:text-slate-300">Ca qua đêm (giờ kết thúc thuộc ngày hôm sau)</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}
                       class="rounded border-slate-300 dark:border-slate-600 text-pcrm-600">
                <span class="text-sm text-slate-700 dark:text-slate-300">Đang hoạt động</span>
            </label>
            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('createShiftModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-primary"><i class="bi bi-floppy"></i> Lưu</button>
            </div>
        </form>
    </div>
</div>
