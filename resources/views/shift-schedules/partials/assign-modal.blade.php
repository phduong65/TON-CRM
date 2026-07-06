<div id="assignShiftModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('assignShiftModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-calendar-check text-pcrm-600"></i> <span id="assignModalTitle">Xếp ca (đa ca)</span>
            </h3>
            <button onclick="closeModal('assignShiftModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form id="assignShiftForm" action="{{ route('shift-schedules.store') }}" method="POST" class="px-4 sm:px-6 py-4 sm:py-5 space-y-4">
            @csrf
            <input type="hidden" id="assignMethodField" name="_method" value="">
            <input type="hidden" name="_modal" value="assignShiftModal">
            <input type="hidden" id="assignEditId" name="_edit_id">
            <input type="hidden" id="assignEmployeeId" name="employee_id">
            <input type="hidden" id="assignWorkDate" name="work_date">
            <p class="text-sm font-medium text-slate-700 dark:text-slate-300" id="assignEmployeeLabel"></p>

            <div class="flex gap-4">
                <label class="inline-flex items-center gap-1.5 text-sm">
                    <input type="radio" name="assign_mode" id="assignModeTemplate" value="template" checked
                        onchange="toggleAssignMode()">
                    Ca có sẵn
                </label>
                <label class="inline-flex items-center gap-1.5 text-sm">
                    <input type="radio" name="assign_mode" id="assignModeFlexible" value="flexible"
                        onchange="toggleAssignMode()">
                    Ca linh hoạt
                </label>
            </div>

            <div id="assignTemplateFields">
                <label class="form-label">Ca làm việc <span class="text-red-500">*</span></label>
                <select id="assignShiftId" name="shift_id" class="form-input" required>
                    <option value="">-- Chọn ca --</option>
                    @foreach($shifts as $s)
                        <option value="{{ $s->id }}">{{ $s->name }} ({{ substr($s->start_time,0,5) }}–{{ substr($s->end_time,0,5) }})</option>
                    @endforeach
                </select>
                @error('shift_id') <p class="form-error">{{ $message }}</p> @enderror
            </div>

            <div id="assignFlexibleFields" class="hidden space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">Giờ vào <span class="text-red-500">*</span></label>
                        <input type="time" id="assignCustomStartTime" name="custom_start_time" class="form-input">
                        @error('custom_start_time') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="form-label">Giờ ra <span class="text-red-500">*</span></label>
                        <input type="time" id="assignCustomEndTime" name="custom_end_time" class="form-input">
                        @error('custom_end_time') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label class="form-label">Nghỉ giữa ca (phút)</label>
                    <input type="number" id="assignCustomBreakMinutes" name="custom_break_minutes" class="form-input" min="0" max="600" placeholder="0">
                    @error('custom_break_minutes') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <label class="inline-flex items-center gap-1.5 text-sm">
                    <input type="checkbox" id="assignCustomIsOvernight" name="custom_is_overnight" value="1">
                    Ca qua đêm (giờ kết thúc thuộc ngày hôm sau)
                </label>
                <label class="inline-flex items-center gap-1.5 text-sm">
                    <input type="checkbox" id="assignCustomIsWfh" name="custom_is_wfh" value="1">
                    Ca WFH (bỏ qua xác thực vị trí khi chấm công)
                </label>
            </div>

            <div>
                <label class="form-label">Ghi chú</label>
                <input type="text" id="assignNote" name="note" class="form-input" placeholder="Không bắt buộc">
                @error('note') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('assignShiftModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-primary"><i class="bi bi-floppy"></i> Lưu</button>
            </div>
        </form>
    </div>
</div>

@once
    @push('scripts')
    <script>
    function toggleAssignMode() {
        const isFlexible = document.getElementById('assignModeFlexible').checked;

        document.getElementById('assignTemplateFields').classList.toggle('hidden', isFlexible);
        document.getElementById('assignFlexibleFields').classList.toggle('hidden', !isFlexible);

        document.getElementById('assignShiftId').disabled = isFlexible;
        document.getElementById('assignShiftId').required = !isFlexible;

        ['assignCustomStartTime', 'assignCustomEndTime'].forEach(function (id) {
            document.getElementById(id).disabled = !isFlexible;
            document.getElementById(id).required = isFlexible;
        });
        ['assignCustomBreakMinutes', 'assignCustomIsOvernight', 'assignCustomIsWfh'].forEach(function (id) {
            document.getElementById(id).disabled = !isFlexible;
        });
    }
    </script>
    @endpush
@endonce
