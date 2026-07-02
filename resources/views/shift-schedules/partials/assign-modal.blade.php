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
            <input type="hidden" id="assignEmployeeId" name="employee_id">
            <input type="hidden" id="assignWorkDate" name="work_date">
            <p class="text-sm font-medium text-slate-700 dark:text-slate-300" id="assignEmployeeLabel"></p>
            <div>
                <label class="form-label">Ca làm việc <span class="text-red-500">*</span></label>
                <select id="assignShiftId" name="shift_id" class="form-input" required>
                    <option value="">-- Chọn ca --</option>
                    @foreach($shifts as $s)
                        <option value="{{ $s->id }}">{{ $s->name }} ({{ substr($s->start_time,0,5) }}–{{ substr($s->end_time,0,5) }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Ghi chú</label>
                <input type="text" id="assignNote" name="note" class="form-input" placeholder="Không bắt buộc">
            </div>
            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('assignShiftModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-primary"><i class="bi bi-floppy"></i> Lưu</button>
            </div>
        </form>
    </div>
</div>
