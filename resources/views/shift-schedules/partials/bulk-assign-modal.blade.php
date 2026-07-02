<div id="bulkAssignModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
    onclick="if(event.target===this)closeModal('bulkAssignModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div
            class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-calendar-plus text-pcrm-600"></i> Xếp ca cố định (hàng loạt)
            </h3>
            <button onclick="closeModal('bulkAssignModal')"
                class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form action="{{ route('shift-schedules.bulk-store') }}" method="POST"
            class="px-4 sm:px-6 py-4 sm:py-5 space-y-4">
            @csrf
            <div>
                <label class="form-label">Nhân viên áp dụng <span class="text-red-500">*</span></label>

                <div class="grid grid-cols-2 gap-2 mb-2">
                    <select id="bulkAssignFilterBranch" class="form-input h-9 text-sm">
                        <option value="">-- Chi nhánh --</option>
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                        @endforeach
                    </select>
                    <select id="bulkAssignFilterTeam" class="form-input h-9 text-sm">
                        <option value="">-- Đội nhóm --</option>
                        @foreach ($teams as $t)
                            <option value="{{ $t->id }}" data-branch="{{ $t->branch_id ?? '' }}">{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-3 text-xs mb-2">
                    <button type="button" onclick="bulkAssignSelectByBranch()"
                        class="inline-flex items-center gap-1 text-pcrm-600 dark:text-pcrm-400 hover:underline">
                        <i class="bi bi-building"></i> Chọn cả chi nhánh
                    </button>
                    <span class="text-slate-300">|</span>
                    <button type="button" onclick="bulkAssignSelectByTeam()"
                        class="inline-flex items-center gap-1 text-pcrm-600 dark:text-pcrm-400 hover:underline">
                        <i class="bi bi-people"></i> Chọn cả đội nhóm
                    </button>
                    <span class="text-slate-300">|</span>
                    <button type="button" onclick="bulkAssignClearEmployees()"
                        class="inline-flex items-center gap-1 text-slate-500 dark:text-slate-400 hover:underline">
                        <i class="bi bi-x-circle"></i> Bỏ chọn tất cả
                    </button>
                </div>

                <select id="bulkAssignEmployeeSelect" name="employee_ids[]" multiple required>
                    @foreach ($allEmployees as $emp)
                        <option value="{{ $emp->id }}" data-branch="{{ $emp->branch_id ?? '' }}"
                            data-team="{{ $emp->team_id ?? '' }}">{{ $emp->name }} — {{ $emp->code }}
                            ({{ $emp->team?->name ?? '—' }})</option>
                    @endforeach
                </select>
                <p class="text-xs text-slate-400 mt-1">Chọn chi nhánh để lọc đội nhóm, rồi bấm "Chọn cả chi nhánh" /
                    "Chọn cả đội nhóm" để thêm hàng loạt, hoặc gõ tìm để chọn từng nhân viên.</p>
                @error('employee_ids')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="form-label">Ca làm việc <span class="text-red-500">*</span></label>
                <select id="bulkAssignShiftSelect" name="shift_ids[]" multiple required>
                    @foreach ($shifts as $s)
                        <option value="{{ $s->id }}">{{ $s->name }}
                            ({{ substr($s->start_time, 0, 5) }}–{{ substr($s->end_time, 0, 5) }})</option>
                    @endforeach
                </select>
                <p class="text-xs text-slate-400 mt-1">Có thể chọn nhiều ca — mỗi ca được xếp riêng cho nhân viên
                    trong cùng những ngày đã chọn (đa ca).</p>
                @error('shift_ids')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Từ ngày <span class="text-red-500">*</span></label>
                    <input type="date" name="date_from" class="form-input" required>
                    @error('date_from')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="form-label">Đến ngày</label>
                    <input type="date" name="date_to" class="form-input">
                    <p class="text-xs text-slate-400 mt-1">Để trống để ca lặp lại hàng tuần không giới hạn.</p>
                    @error('date_to')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            <div>
                <label class="form-label">Các thứ trong tuần áp dụng <span class="text-red-500">*</span></label>
                <div class="flex flex-wrap gap-2">
                    @foreach (['1' => 'T2', '2' => 'T3', '3' => 'T4', '4' => 'T5', '5' => 'T6', '6' => 'T7', '7' => 'CN'] as $val => $label)
                        <label
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-600 text-sm cursor-pointer has-[:checked]:bg-pcrm-50 has-[:checked]:border-pcrm-400 dark:has-[:checked]:bg-pcrm-900/20">
                            <input type="checkbox" name="weekdays[]" value="{{ $val }}"
                                {{ in_array($val, ['1', '2', '3', '4', '5']) ? 'checked' : '' }}
                                class="rounded border-slate-300 dark:border-slate-600 text-pcrm-600">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('weekdays')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>
            <p class="text-xs text-slate-400">Nếu nhân viên đã có đúng ca đó vào ngày đó thì sẽ được giữ nguyên (bỏ
                qua, không ghi đè). Xoá bất kỳ ca nào trong đợt này sẽ xoá toàn bộ đợt (mọi nhân viên, mọi ngày, kể cả
                các ngày lặp lại trong tương lai).</p>
            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('bulkAssignModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-primary"><i class="bi bi-floppy"></i> Xếp ca</button>
            </div>
        </form>
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof TomSelect === 'undefined') return;

                var employeeTS = new TomSelect('#bulkAssignEmployeeSelect', {
                    plugins: ['remove_button'],
                    placeholder: 'Tìm và chọn nhân viên...',
                    maxItems: null,
                    maxOptions: null,
                });

                new TomSelect('#bulkAssignShiftSelect', {
                    plugins: ['remove_button'],
                    placeholder: 'Chọn 1 hoặc nhiều ca...',
                    maxItems: null,
                });

                var branchFilter = document.getElementById('bulkAssignFilterBranch');
                var teamFilter   = document.getElementById('bulkAssignFilterTeam');

                // Chọn chi nhánh → chỉ còn hiện các đội nhóm thuộc chi nhánh đó
                branchFilter.addEventListener('change', function () {
                    var branchId = branchFilter.value;
                    Array.from(teamFilter.options).forEach(function (opt) {
                        if (!opt.value) return;
                        var match = !branchId || opt.dataset.branch === branchId;
                        opt.hidden   = !match;
                        opt.disabled = !match;
                    });
                    var current = teamFilter.options[teamFilter.selectedIndex];
                    if (current && current.value && current.hidden) teamFilter.value = '';
                });

                function addEmployeesMatching(matchFn) {
                    Array.from(document.querySelectorAll('#bulkAssignEmployeeSelect option'))
                        .filter(matchFn)
                        .forEach(function (opt) { employeeTS.addItem(opt.value, true); });
                }

                window.bulkAssignSelectByBranch = function () {
                    var branchId = branchFilter.value;
                    if (!branchId) { alert('Vui lòng chọn chi nhánh trước.'); return; }
                    addEmployeesMatching(function (opt) { return opt.dataset.branch === branchId; });
                };

                window.bulkAssignSelectByTeam = function () {
                    var teamId = teamFilter.value;
                    if (!teamId) { alert('Vui lòng chọn đội nhóm trước.'); return; }
                    addEmployeesMatching(function (opt) { return opt.dataset.team === teamId; });
                };

                window.bulkAssignClearEmployees = function () {
                    employeeTS.clear();
                };
            });
        </script>
    @endpush
@endonce
