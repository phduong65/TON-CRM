<div id="createViolationModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('createViolationModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-2xl max-h-[95vh] flex flex-col">
        <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 dark:border-slate-700 shrink-0">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-plus-circle text-pcrm-600"></i> Thêm lỗi vi phạm
            </h3>
            <button onclick="closeModal('createViolationModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form action="{{ route('violations.store') }}" method="POST" class="px-4 sm:px-6 py-4 sm:py-5 space-y-4 overflow-y-auto">
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

            {{-- Hình thức xử phạt --}}
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

            {{-- Mức độ vi phạm — click để tự động điền điểm --}}
            <div id="createViolationPoints" class="{{ old('penalty_type') === 'money' ? 'hidden' : '' }}">
                <label class="form-label">Mức độ vi phạm <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-5 gap-1.5">
                    @foreach([
                        ['value' => 'low',      'label' => 'Nhẹ',          'pts' => 1,  'idle' => 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-600'],
                        ['value' => 'medium',   'label' => 'Trung bình',   'pts' => 3,  'idle' => 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800'],
                        ['value' => 'high',     'label' => 'Nặng',         'pts' => 5,  'idle' => 'bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800'],
                        ['value' => 'critical', 'label' => 'Nghiêm trọng', 'pts' => 10, 'idle' => 'bg-orange-50 dark:bg-orange-900/20 text-orange-700 dark:text-orange-300 border-orange-200 dark:border-orange-800'],
                        ['value' => 'extreme',  'label' => 'Đặc biệt NT',  'pts' => 20, 'idle' => 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 border-red-200 dark:border-red-800'],
                    ] as $s)
                    <button type="button"
                            id="createSevBtn_{{ $s['value'] }}"
                            data-severity="{{ $s['value'] }}"
                            data-pts="{{ $s['pts'] }}"
                            onclick="setViolationSeverity('{{ $s['value'] }}', 'create')"
                            class="viol-sev-btn-create flex flex-col items-center gap-0.5 rounded-lg border px-2 py-2.5 text-center transition-all cursor-pointer {{ $s['idle'] }}
                                   {{ old('severity', 'medium') === $s['value'] ? 'ring-2 ring-offset-1 ring-pcrm-500 dark:ring-offset-slate-800 scale-105 font-bold' : '' }}">
                        <span class="text-xs font-semibold leading-none">{{ $s['label'] }}</span>
                        <span class="text-[11px] font-mono leading-none opacity-75 mt-0.5">-{{ $s['pts'] }}đ</span>
                    </button>
                    @endforeach
                </div>
                <input type="hidden" name="severity" id="createViolationSeverityInput"
                       value="{{ old('severity', 'medium') }}">
                @error('severity') <p class="form-error mt-1">{{ $message }}</p> @enderror

                <div class="mt-3">
                    <label class="form-label">Điểm trừ</label>
                    <input type="number" name="points_deducted" id="createViolationPointsVal"
                           class="form-input bg-slate-50 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300"
                           value="{{ old('points_deducted', 3) }}" min="0" max="100" readonly tabindex="-1">
                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Tự động theo mức độ đã chọn.</p>
                </div>
                @error('points_deducted') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            {{-- Tiền phạt — nhập tay --}}
            <div id="createViolationMoney" class="{{ old('penalty_type', 'points') === 'points' ? 'hidden' : '' }}">
                <label class="form-label">Số tiền phạt (₫)</label>
                <input type="number" name="money_deducted" class="form-input"
                       value="{{ old('money_deducted', 0) }}" min="0" step="1000"
                       placeholder="VD: 200000">
                @error('money_deducted') <p class="form-error">{{ $message }}</p> @enderror
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
