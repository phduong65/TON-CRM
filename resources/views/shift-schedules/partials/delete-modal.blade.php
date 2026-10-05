<div id="deleteShiftScheduleModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
    onclick="if(event.target===this)closeModal('deleteShiftScheduleModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-trash3 text-red-600"></i> Xoá ca làm việc
            </h3>
            <button onclick="closeModal('deleteShiftScheduleModal')"
                class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form id="deleteShiftScheduleForm" action="{{ route('shift-schedules.destroy-filtered') }}" method="POST"
            class="px-4 sm:px-6 py-4 sm:py-5 space-y-4">
            @csrf
            @method('DELETE')
            <input type="hidden" name="_modal" value="deleteShiftScheduleModal">

            <div>
                <label class="form-label">Phạm vi xoá</label>
                <div class="flex flex-wrap gap-2">
                    <label class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-600 text-sm cursor-pointer has-[:checked]:bg-pcrm-50 has-[:checked]:border-pcrm-400 dark:has-[:checked]:bg-pcrm-900/20">
                        <input type="radio" name="scope" value="employee" checked
                            class="border-slate-300 dark:border-slate-600 text-pcrm-600" onchange="deleteSchedToggleScope()">
                        Theo nhân viên
                    </label>
                    <label class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-600 text-sm cursor-pointer has-[:checked]:bg-pcrm-50 has-[:checked]:border-pcrm-400 dark:has-[:checked]:bg-pcrm-900/20">
                        <input type="radio" name="scope" value="team"
                            class="border-slate-300 dark:border-slate-600 text-pcrm-600" onchange="deleteSchedToggleScope()">
                        Theo đội nhóm
                    </label>
                </div>
            </div>

            {{-- Phạm vi: theo nhân viên --}}
            <div id="deleteSchedScopeEmployee">
                <label class="form-label">Nhân viên <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-2 gap-2 mb-2">
                    <select id="deleteSchedFilterBranch" class="form-input h-9 text-sm">
                        <option value="">-- Chi nhánh --</option>
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                        @endforeach
                    </select>
                    <select id="deleteSchedFilterTeam" class="form-input h-9 text-sm">
                        <option value="">-- Đội nhóm --</option>
                        @foreach ($teams as $t)
                            <option value="{{ $t->id }}" data-branch="{{ $t->branch_id ?? '' }}">{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-3 text-xs mb-2">
                    <button type="button" onclick="deleteSchedSelectByBranch()"
                        class="inline-flex items-center gap-1 text-pcrm-600 dark:text-pcrm-400 hover:underline">
                        <i class="bi bi-building"></i> Chọn cả chi nhánh
                    </button>
                    <span class="text-slate-300">|</span>
                    <button type="button" onclick="deleteSchedSelectByTeam()"
                        class="inline-flex items-center gap-1 text-pcrm-600 dark:text-pcrm-400 hover:underline">
                        <i class="bi bi-people"></i> Chọn cả đội nhóm
                    </button>
                    <span class="text-slate-300">|</span>
                    <button type="button" onclick="deleteSchedClearEmployees()"
                        class="inline-flex items-center gap-1 text-slate-500 dark:text-slate-400 hover:underline">
                        <i class="bi bi-x-circle"></i> Bỏ chọn tất cả
                    </button>
                </div>
                <select id="deleteSchedEmployeeSelect" name="employee_ids[]" multiple>
                    @foreach ($allEmployees as $emp)
                        <option value="{{ $emp->id }}" data-branch="{{ $emp->branch_id ?? '' }}"
                            data-team="{{ $emp->team_id ?? '' }}">{{ $emp->name }} — {{ $emp->code }}
                            ({{ $emp->team?->name ?? '—' }})</option>
                    @endforeach
                </select>
                @error('employee_ids')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            {{-- Phạm vi: theo đội nhóm (áp dụng cho toàn bộ nhân viên trong nhóm) --}}
            <div id="deleteSchedScopeTeam" class="hidden">
                <label class="form-label">Đội nhóm <span class="text-red-500">*</span></label>
                <select id="deleteSchedTeamSelect" name="team_ids[]" multiple>
                    @foreach ($teams as $t)
                        <option value="{{ $t->id }}">{{ $t->name }} @if($t->branch)({{ $t->branch->name }})@endif</option>
                    @endforeach
                </select>
                <p class="text-xs text-slate-400 mt-1">Áp dụng cho toàn bộ nhân viên đang thuộc (các) đội nhóm được chọn.</p>
                @error('team_ids')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="form-label">Khoảng thời gian xoá</label>
                <div class="flex flex-wrap gap-2 mb-2">
                    <label class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-600 text-sm cursor-pointer has-[:checked]:bg-pcrm-50 has-[:checked]:border-pcrm-400 dark:has-[:checked]:bg-pcrm-900/20">
                        <input type="radio" name="range_type" value="day" checked
                            class="border-slate-300 dark:border-slate-600 text-pcrm-600" onchange="deleteSchedToggleRange()">
                        Theo ngày
                    </label>
                    <label class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-600 text-sm cursor-pointer has-[:checked]:bg-pcrm-50 has-[:checked]:border-pcrm-400 dark:has-[:checked]:bg-pcrm-900/20">
                        <input type="radio" name="range_type" value="month"
                            class="border-slate-300 dark:border-slate-600 text-pcrm-600" onchange="deleteSchedToggleRange()">
                        Theo tháng
                    </label>
                    <label class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-600 text-sm cursor-pointer has-[:checked]:bg-pcrm-50 has-[:checked]:border-pcrm-400 dark:has-[:checked]:bg-pcrm-900/20">
                        <input type="radio" name="range_type" value="range"
                            class="border-slate-300 dark:border-slate-600 text-pcrm-600" onchange="deleteSchedToggleRange()">
                        Từ ngày — đến ngày
                    </label>
                    <label class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-600 text-sm cursor-pointer has-[:checked]:bg-pcrm-50 has-[:checked]:border-pcrm-400 dark:has-[:checked]:bg-pcrm-900/20">
                        <input type="radio" name="range_type" value="all"
                            class="border-slate-300 dark:border-slate-600 text-pcrm-600" onchange="deleteSchedToggleRange()">
                        Tất cả ca (mọi thời điểm)
                    </label>
                </div>

                <div id="deleteSchedRangeDay">
                    <input type="date" name="date" class="form-input h-9 text-sm">
                    @error('date')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>
                <div id="deleteSchedRangeMonth" class="hidden">
                    <input type="month" name="month" class="form-input h-9 text-sm">
                    @error('month')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>
                <div id="deleteSchedRangeRange" class="hidden">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <input type="date" name="date_from" class="form-input h-9 text-sm" placeholder="Từ ngày">
                            @error('date_from')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <input type="date" name="date_to" class="form-input h-9 text-sm" placeholder="Đến ngày">
                            @error('date_to')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Bỏ trống "Đến ngày" để xoá tất cả ca từ "Từ ngày" trở đi. Bỏ trống "Từ ngày" để xoá tất cả ca đến trước "Đến ngày".</p>
                </div>
                <div id="deleteSchedRangeAll" class="hidden">
                    <p class="text-xs text-amber-600 dark:text-amber-400">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        Sẽ xoá TOÀN BỘ ca đã xếp của (các) nhân viên/đội nhóm được chọn, không giới hạn ngày (kể cả quá khứ và tương lai).
                    </p>
                </div>
            </div>

            <p class="text-xs text-slate-400">Nếu chọn "Tất cả ca (mọi thời điểm)": ca nào thuộc một đợt xếp ca cố định (hàng loạt) sẽ bị huỷ toàn bộ đợt — mọi nhân viên, mọi ngày liên quan. Với "Theo ngày/tháng/khoảng ngày": chỉ xoá đúng ca trong phạm vi đã chọn, các ngày khác của đợt cố định không bị ảnh hưởng.</p>

            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('deleteShiftScheduleModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-danger"><i class="bi bi-trash3"></i> Xoá ca</button>
            </div>
        </form>
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof TomSelect === 'undefined') return;

                var deleteEmployeeTS = new TomSelect('#deleteSchedEmployeeSelect', {
                    plugins: ['remove_button'],
                    placeholder: 'Tìm và chọn nhân viên...',
                    maxItems: null,
                    maxOptions: null,
                });

                var deleteTeamTS = new TomSelect('#deleteSchedTeamSelect', {
                    plugins: ['remove_button'],
                    placeholder: 'Tìm và chọn đội nhóm...',
                    maxItems: null,
                    maxOptions: null,
                });

                var branchFilter = document.getElementById('deleteSchedFilterBranch');
                var teamFilter   = document.getElementById('deleteSchedFilterTeam');

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
                    Array.from(document.querySelectorAll('#deleteSchedEmployeeSelect option'))
                        .filter(matchFn)
                        .forEach(function (opt) { deleteEmployeeTS.addItem(opt.value, true); });
                }

                window.deleteSchedSelectByBranch = function () {
                    var branchId = branchFilter.value;
                    if (!branchId) { alert('Vui lòng chọn chi nhánh trước.'); return; }
                    addEmployeesMatching(function (opt) { return opt.dataset.branch === branchId; });
                };

                window.deleteSchedSelectByTeam = function () {
                    var teamId = teamFilter.value;
                    if (!teamId) { alert('Vui lòng chọn đội nhóm trước.'); return; }
                    addEmployeesMatching(function (opt) { return opt.dataset.team === teamId; });
                };

                window.deleteSchedClearEmployees = function () {
                    deleteEmployeeTS.clear();
                };

                window.deleteSchedToggleScope = function () {
                    var scope = document.querySelector('input[name="scope"]:checked').value;
                    document.getElementById('deleteSchedScopeEmployee').classList.toggle('hidden', scope !== 'employee');
                    document.getElementById('deleteSchedScopeTeam').classList.toggle('hidden', scope !== 'team');
                };

                window.deleteSchedToggleRange = function () {
                    var rangeType = document.querySelector('input[name="range_type"]:checked').value;
                    document.getElementById('deleteSchedRangeDay').classList.toggle('hidden', rangeType !== 'day');
                    document.getElementById('deleteSchedRangeMonth').classList.toggle('hidden', rangeType !== 'month');
                    document.getElementById('deleteSchedRangeRange').classList.toggle('hidden', rangeType !== 'range');
                    document.getElementById('deleteSchedRangeAll').classList.toggle('hidden', rangeType !== 'all');
                };

                var deleteSchedForm = document.getElementById('deleteShiftScheduleForm');

                // Global admin.blade.php dùng capture-phase submit listener để disable nút
                // ngay khi sự kiện 'submit' fire — chạy TRƯỚC handler này (bubble-phase).
                // Nên mọi nhánh preventDefault() bên dưới phải tự bật lại nút, nếu không
                // nút sẽ bị khoá vĩnh viễn dù request chưa từng được gửi đi.
                function reEnableDeleteSchedBtn() {
                    var btn = deleteSchedForm.querySelector('button[type="submit"]');
                    if (btn) {
                        btn.disabled = false;
                        btn.classList.remove('opacity-60', 'cursor-not-allowed');
                    }
                }

                deleteSchedForm.addEventListener('submit', function (e) {
                    var scope = document.querySelector('input[name="scope"]:checked').value;
                    var rangeType = document.querySelector('input[name="range_type"]:checked').value;

                    if (scope === 'employee' && deleteEmployeeTS.items.length === 0) {
                        e.preventDefault();
                        reEnableDeleteSchedBtn();
                        alert('Vui lòng chọn ít nhất 1 nhân viên.');
                        return;
                    }
                    if (scope === 'team' && deleteTeamTS.items.length === 0) {
                        e.preventDefault();
                        reEnableDeleteSchedBtn();
                        alert('Vui lòng chọn ít nhất 1 đội nhóm.');
                        return;
                    }
                    if (rangeType === 'day' && !document.querySelector('#deleteSchedRangeDay input').value) {
                        e.preventDefault();
                        reEnableDeleteSchedBtn();
                        alert('Vui lòng chọn ngày cần xoá.');
                        return;
                    }
                    if (rangeType === 'month' && !document.querySelector('#deleteSchedRangeMonth input').value) {
                        e.preventDefault();
                        reEnableDeleteSchedBtn();
                        alert('Vui lòng chọn tháng cần xoá.');
                        return;
                    }
                    if (rangeType === 'range') {
                        var from = document.querySelector('#deleteSchedRangeRange input[name="date_from"]').value;
                        var to   = document.querySelector('#deleteSchedRangeRange input[name="date_to"]').value;
                        if (!from && !to) {
                            e.preventDefault();
                            reEnableDeleteSchedBtn();
                            alert('Vui lòng nhập ít nhất từ ngày hoặc đến ngày.');
                            return;
                        }
                    }

                    var msg = rangeType === 'all'
                        ? 'Xoá TOÀN BỘ ca đã xếp của (các) ' + (scope === 'team' ? 'đội nhóm' : 'nhân viên') + ' đã chọn, không giới hạn ngày? Hành động này không thể hoàn tác.'
                        : 'Xoá ca theo điều kiện đã chọn? Ca nào thuộc đợt xếp ca cố định sẽ bị huỷ toàn bộ đợt. Hành động này không thể hoàn tác.';
                    if (!confirm(msg)) {
                        e.preventDefault();
                        reEnableDeleteSchedBtn();
                    }
                });
            });
        </script>
    @endpush
@endonce
