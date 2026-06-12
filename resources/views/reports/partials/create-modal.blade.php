<div id="createReportModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-lg">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 dark:border-slate-700">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-flag-fill text-pcrm-500"></i>
                Tạo báo cáo vi phạm
            </h3>
            <button onclick="closeModal('createReportModal')"
                    class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x text-lg"></i>
            </button>
        </div>

        <form action="{{ route('reports.store') }}" method="POST" class="px-5 py-4 space-y-4">
            @csrf
            <input type="hidden" name="_modal" value="createReportModal">

            {{-- Reporter info (readonly) --}}
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-700">
                <p class="text-xs text-slate-500 dark:text-slate-400">Bạn đang báo cáo với tư cách</p>
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200 mt-0.5">
                    {{ $currentEmployee->name }}
                    <span class="font-normal text-slate-500 dark:text-slate-400 text-xs">({{ $currentEmployee->code }})</span>
                </p>
            </div>

            {{-- Reported employee --}}
            <div>
                <label class="form-label">Nhân viên bị báo cáo <span class="text-red-500">*</span></label>
                <select name="reported_employee_id"
                        class="form-select @error('reported_employee_id') border-red-400 @enderror">
                    <option value="">-- Chọn nhân viên --</option>
                    @foreach($employees as $emp)
                        @if($emp->id !== $currentEmployee->id)
                            <option value="{{ $emp->id }}" @selected(old('reported_employee_id') == $emp->id)>
                                {{ $emp->name }} ({{ $emp->code }})@if($emp->branch) · {{ $emp->branch->name }}@endif
                            </option>
                        @endif
                    @endforeach
                </select>
                @error('reported_employee_id')
                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Violation --}}
            <div>
                <label class="form-label">Vi phạm <span class="text-slate-400 text-xs font-normal">(tuỳ chọn)</span></label>
                <select name="violation_id" class="form-select">
                    <option value="">-- Không chọn vi phạm cụ thể --</option>
                    @foreach($violations as $v)
                        <option value="{{ $v->id }}" @selected(old('violation_id') == $v->id)>
                            {{ $v->name }}@if($v->points_deducted > 0) (trừ {{ $v->points_deducted }} điểm)@endif
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-slate-400 mt-1">Chọn loại vi phạm giúp hệ thống tự động tính điểm trừ khi báo cáo được duyệt.</p>
            </div>

            {{-- Description --}}
            <div>
                <label class="form-label">Mô tả sự việc <span class="text-red-500">*</span></label>
                <textarea name="description" rows="4"
                          class="form-input @error('description') border-red-400 @enderror"
                          placeholder="Mô tả chi tiết sự việc vi phạm bạn chứng kiến...">{{ old('description') }}</textarea>
                @error('description')
                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Evidence note --}}
            <div>
                <label class="form-label">Bằng chứng / Ghi chú <span class="text-slate-400 text-xs font-normal">(tuỳ chọn)</span></label>
                <textarea name="evidence_note" rows="2"
                          class="form-input"
                          placeholder="Camera, nhân chứng, thời gian cụ thể...">{{ old('evidence_note') }}</textarea>
            </div>

            <div class="pt-2 flex gap-2 justify-end border-t border-slate-100 dark:border-slate-700">
                <button type="button" onclick="closeModal('createReportModal')" class="btn-secondary">Huỷ</button>
                <button type="submit" class="btn-primary">
                    <i class="bi bi-send-fill"></i>
                    Gửi báo cáo
                </button>
            </div>
        </form>
    </div>
</div>
