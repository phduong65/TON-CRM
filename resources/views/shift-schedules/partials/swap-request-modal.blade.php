<div id="swapRequestModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('swapRequestModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-arrow-left-right text-violet-600"></i> Đề xuất đổi ca
            </h3>
            <button onclick="closeModal('swapRequestModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form action="{{ route('shift-swap-requests.store') }}" method="POST" class="px-4 sm:px-6 py-4 sm:py-5 space-y-4">
            @csrf
            <input type="hidden" id="swapTargetScheduleId" name="target_schedule_id">
            <p id="swapTargetLabel" class="text-sm font-medium text-slate-700 dark:text-slate-300 bg-slate-50 dark:bg-slate-700/40 rounded-lg px-3 py-2"></p>
            <div>
                <label class="form-label">Ca của bạn muốn đổi <span class="text-red-500">*</span></label>
                <select name="requester_schedule_id" class="form-input" required>
                    <option value="">-- Chọn ca của bạn --</option>
                    @foreach($myUpcomingSchedules as $s)
                        <option value="{{ $s->id }}">{{ $s->work_date->format('d/m/Y') }} — {{ $s->shift->name }}</option>
                    @endforeach
                </select>
                @error('requester_schedule_id') <p class="form-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="form-label">Lý do (không bắt buộc)</label>
                <textarea name="reason" rows="2" class="form-input" placeholder="VD: có việc gia đình vào ngày đó..."></textarea>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('swapRequestModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-primary"><i class="bi bi-send"></i> Gửi yêu cầu</button>
            </div>
        </form>
    </div>
</div>
