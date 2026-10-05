<div id="createRequirementModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="relative w-full max-w-2xl bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 overflow-hidden transform transition-all animate-fadeIn">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                    <i class="bi bi-people-fill text-lg"></i>
                </div>
                <div>
                    <h3 class="font-bold text-slate-800 dark:text-white text-base">Thêm quy tắc định biên nhân sự</h3>
                    <p class="text-xs text-slate-400 dark:text-slate-500">Khai báo số nhân viên tối thiểu và mục tiêu theo khung giờ</p>
                </div>
            </div>
            <button type="button" onclick="closeModal('createRequirementModal')" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                <i class="bi bi-x-lg text-lg"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('shift-coverage-requirements.store') }}" class="p-6 space-y-4">
            @csrf
            <input type="hidden" name="_modal" value="createRequirementModal">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Chi nhánh <span class="text-red-500">*</span></label>
                    <select name="branch_id" id="createBranchId" required onchange="filterTeamsByBranch('createBranchId', 'createTeamId')"
                        class="w-full text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white px-3 py-2 focus:ring-2 focus:ring-indigo-500">
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}" {{ $selectedBranchId == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Bộ phận áp dụng <span class="text-red-500">*</span></label>
                    <select name="team_id" id="createTeamId" required
                        class="w-full text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white px-3 py-2 focus:ring-2 focus:ring-indigo-500">
                        @foreach($teams as $t)
                            <option value="{{ $t->id }}">{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Tên khung vận hành <span class="text-red-500">*</span></label>
                <input type="text" name="name" required placeholder="VD: Ca sáng vận hành (11h-15h), Ca tối cao điểm"
                    class="w-full text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white px-3 py-2 focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Ngày áp dụng trong tuần <span class="text-red-500">*</span></label>
                    <div class="flex gap-2">
                        <button type="button" onclick="selectDaysPreset('create', [1,2,3,4,5,6,7])" class="text-[11px] text-indigo-600 dark:text-indigo-400 hover:underline">Tất cả</button>
                        <span class="text-slate-300">|</span>
                        <button type="button" onclick="selectDaysPreset('create', [1,2,3,4,5])" class="text-[11px] text-indigo-600 dark:text-indigo-400 hover:underline">T2–T6</button>
                        <span class="text-slate-300">|</span>
                        <button type="button" onclick="selectDaysPreset('create', [6,7])" class="text-[11px] text-indigo-600 dark:text-indigo-400 hover:underline">Cuối tuần</button>
                    </div>
                </div>
                <div class="grid grid-cols-7 gap-2">
                    @foreach([1 => 'T2', 2 => 'T3', 3 => 'T4', 4 => 'T5', 5 => 'T6', 6 => 'T7', 7 => 'CN'] as $dayNum => $dayName)
                        <label class="flex flex-col items-center p-2 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50 cursor-pointer">
                            <input type="checkbox" name="days_of_week[]" value="{{ $dayNum }}" class="create-day-checkbox rounded text-indigo-600 focus:ring-indigo-500" checked>
                            <span class="text-xs font-medium text-slate-700 dark:text-slate-300 mt-1">{{ $dayName }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Giờ bắt đầu <span class="text-red-500">*</span></label>
                    <input type="time" name="start_time" required value="11:00"
                        class="w-full text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white px-3 py-2 focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Giờ kết thúc <span class="text-red-500">*</span></label>
                    <input type="time" name="end_time" required value="15:00"
                        class="w-full text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white px-3 py-2 focus:ring-2 focus:ring-indigo-500">
                    <p class="text-[10px] text-slate-400 mt-1">Kết thúc <= bắt đầu hoặc 00:00 tính là qua nửa đêm.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Số NV tối thiểu <span class="text-red-500">*</span></label>
                    <input type="number" name="minimum_staff" required min="0" value="2"
                        class="w-full text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white px-3 py-2 focus:ring-2 focus:ring-indigo-500">
                    <p class="text-[10px] text-slate-400 mt-1">Mức thấp nhất phải có mặt đồng thời.</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Mục tiêu nhân sự (tuỳ chọn)</label>
                    <input type="number" name="target_staff" min="0" placeholder="Để trống nếu bằng tối thiểu"
                        class="w-full text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white px-3 py-2 focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Hiệu lực từ ngày <span class="text-red-500">*</span></label>
                    <input type="date" name="effective_from" required value="{{ now()->startOfWeek()->toDateString() }}"
                        class="w-full text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white px-3 py-2 focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Hiệu lực đến ngày (tuỳ chọn)</label>
                    <input type="date" name="effective_until"
                        class="w-full text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white px-3 py-2 focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center pt-2">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Mẫu ca gợi ý (tuỳ chọn)</label>
                    <select name="shift_id"
                        class="w-full text-sm rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white px-3 py-2 focus:ring-2 focus:ring-indigo-500">
                        <option value="">-- Không chọn --</option>
                        @foreach($shifts as $sh)
                            <option value="{{ $sh->id }}">{{ $sh->name }} ({{ substr($sh->start_time, 0, 5) }}–{{ substr($sh->end_time, 0, 5) }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="pt-4">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded text-indigo-600 focus:ring-indigo-500">
                        <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">Đang kích hoạt áp dụng</span>
                    </label>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 dark:border-slate-700 flex justify-end gap-3">
                <button type="button" onclick="closeModal('createRequirementModal')"
                    class="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-700 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors">
                    Huỷ
                </button>
                <button type="submit"
                    class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 focus:ring-4 focus:ring-indigo-300 transition-colors shadow-sm">
                    Lưu quy tắc
                </button>
            </div>
        </form>
    </div>
</div>
