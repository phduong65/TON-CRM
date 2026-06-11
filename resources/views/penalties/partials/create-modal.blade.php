{{-- ── Penalty Create Modal ── --}}
<div id="createPenaltyModal"
     class="hidden fixed inset-0 bg-black/50 z-50 items-center justify-center p-4"
     onclick="if(event.target===this)closeModal('createPenaltyModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-2xl flex flex-col"
         style="max-height:92vh">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 shrink-0">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-gear text-red-500"></i> Tạo phiếu xử phạt
            </h3>
            <button onclick="closeModal('createPenaltyModal')"
                    class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>

        {{-- Form --}}
        <form id="createPenaltyForm" action="{{ route('penalties.store') }}" method="POST"
              enctype="multipart/form-data"
              class="flex flex-col flex-1 overflow-hidden"
              onsubmit="return cpBeforeSubmit(this)">
            @csrf
            <input type="hidden" name="_modal" value="createPenaltyModal">
            {{-- employee_id is always set by JS before submit --}}
            <input type="hidden" name="employee_id" id="cp_employee_id">

            <div class="overflow-y-auto flex-1 px-6 py-5 space-y-5">

                {{-- ① Quy chế --}}
                <div>
                    <label class="form-label">Quy chế <span class="text-red-500">*</span></label>
                    <select id="cp_regulation" name="_regulation_id" class="form-input" onchange="cpOnRegulationChange(this.value)" required>
                        <option value="">-- Chọn quy chế --</option>
                        @foreach($regulations as $reg)
                        <option value="{{ $reg->id }}"
                                @if(old('_regulation_id') == $reg->id) selected @endif>
                            {{ $reg->name }}
                        </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Chọn quy chế trước để lọc danh sách vi phạm.</p>
                </div>

                {{-- ② Vi phạm --}}
                <div>
                    <label class="form-label">Vi phạm <span class="text-red-500">*</span></label>
                    <select name="violation_id" id="cp_violation" class="form-input"
                            onchange="cpOnViolationChange(this.value)" required>
                        <option value="">-- Chọn vi phạm --</option>
                        {{-- All violations shown initially; JS filters by regulation --}}
                        @foreach($violations as $v)
                        <option value="{{ $v->id }}"
                                data-reg="{{ $v->regulation_id ?? 0 }}"
                                @if(old('violation_id') == $v->id) selected @endif>
                            {{ $v->name }}
                            @if($v->points_deducted > 0) — {{ $v->points_deducted }}đ @endif
                            @if($v->penalty_type === 'money' || $v->penalty_type === 'both')
                                @if($v->money_deducted > 0) / {{ number_format($v->money_deducted, 0, ',', '.') }}₫ @endif
                            @endif
                        </option>
                        @endforeach
                    </select>
                    @error('violation_id') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                {{-- ③ Hình thức phạt --}}
                <div>
                    <label class="form-label">Hình thức phạt <span class="text-red-500">*</span></label>
                    <div class="flex rounded-lg border border-slate-300 dark:border-slate-600 overflow-hidden text-sm font-medium">
                        <button type="button" id="cp_type_btn_individual"
                                onclick="cpSetType('individual')"
                                class="flex-1 py-2 flex items-center justify-center gap-2 transition-colors
                                       bg-pcrm-600 text-white">
                            <i class="ph-user"></i> Cá nhân
                        </button>
                        <button type="button" id="cp_type_btn_team"
                                onclick="cpSetType('team')"
                                class="flex-1 py-2 flex items-center justify-center gap-2 transition-colors
                                       text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">
                            <i class="ph-users-three"></i> Cả nhóm
                        </button>
                    </div>
                </div>

                {{-- ── Individual panel ── --}}
                <div id="cp_panel_individual" class="space-y-4">
                    <div>
                        <label class="form-label">Nhân viên vi phạm <span class="text-red-500">*</span></label>
                        <select id="cp_individual_employee" class="form-input">
                            <option value="">-- Chọn nhân viên --</option>
                            @foreach($employees as $e)
                            <option value="{{ $e->id }}"
                                    @if(old('employee_id') == $e->id) selected @endif>
                                {{ $e->code }} — {{ $e->name }}
                                @if($e->branch) ({{ $e->branch->name }}) @endif
                            </option>
                            @endforeach
                        </select>
                        @error('employee_id') <p class="form-error">{{ $message }}</p> @enderror
                    </div>

                    {{-- Additional members --}}
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="form-label mb-0 text-sm">Thành viên liên đới</label>
                            <button type="button" onclick="cpAddIndividualMember()"
                                    class="inline-flex items-center gap-1 text-xs text-pcrm-600 dark:text-pcrm-400 hover:underline">
                                <i class="bi bi-person-plus"></i> Thêm
                            </button>
                        </div>
                        <div id="cp_individual_members" class="space-y-2">
                            @if(old('members'))
                                @foreach(old('members') as $idx => $m)
                                <div class="flex gap-2 items-center cp-member-row">
                                    <select name="members[{{ $idx }}][employee_id]" class="form-input flex-1 text-sm" required>
                                        <option value="">-- Chọn NV --</option>
                                        @foreach($employees as $e)
                                        <option value="{{ $e->id }}" @if($m['employee_id'] == $e->id) selected @endif>
                                            {{ $e->code }} — {{ $e->name }}
                                        </option>
                                        @endforeach
                                    </select>
                                    <input type="number" name="members[{{ $idx }}][points_deducted]"
                                           class="form-input w-24 text-sm" min="0" max="100"
                                           value="{{ $m['points_deducted'] ?? 0 }}" required>
                                    <button type="button" onclick="this.closest('.cp-member-row').remove()"
                                            class="w-9 h-9 flex items-center justify-center rounded-lg text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 shrink-0">
                                        <i class="bi bi-trash text-sm"></i>
                                    </button>
                                </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>

                {{-- ── Team panel ── --}}
                <div id="cp_panel_team" class="hidden space-y-3">
                    <div>
                        <label class="form-label">Nhóm <span class="text-red-500">*</span></label>
                        <select id="cp_team_select" class="form-input" onchange="cpOnTeamChange(this.value)">
                            <option value="">-- Chọn nhóm --</option>
                            @foreach($teams as $t)
                            <option value="{{ $t->id }}">
                                {{ $t->name }}
                                @if($t->branch) · {{ $t->branch->name }} @endif
                                ({{ $t->employees->count() }} NV)
                            </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Team member table --}}
                    <div id="cp_team_members_wrap" class="hidden">
                        <div class="flex items-center justify-between mb-2">
                            <label class="form-label mb-0 text-sm">Thành viên trong nhóm</label>
                            <span class="text-xs text-slate-400 dark:text-slate-500">
                                <i class="ph-info"></i> Chọn ● để đặt người chịu TN chính
                            </span>
                        </div>
                        <div class="rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50 dark:bg-slate-700/50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 w-8">TN</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 w-8">☑</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 dark:text-slate-400">Nhân viên</th>
                                        <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500 dark:text-slate-400 w-24">Điểm trừ</th>
                                    </tr>
                                </thead>
                                <tbody id="cp_team_member_rows" class="divide-y divide-slate-100 dark:divide-slate-700"></tbody>
                            </table>
                        </div>
                        <div class="flex items-center justify-between mt-2 px-1">
                            <p id="cp_team_member_count" class="text-xs text-slate-400 dark:text-slate-500"></p>
                            <div class="flex items-center gap-2">
                                <button type="button" onclick="cpSelectAllTeamMembers(true)"
                                        class="text-xs text-pcrm-600 dark:text-pcrm-400 hover:underline">Chọn tất cả</button>
                                <span class="text-slate-300 dark:text-slate-600">·</span>
                                <button type="button" onclick="cpSelectAllTeamMembers(false)"
                                        class="text-xs text-slate-500 hover:underline">Bỏ chọn</button>
                            </div>
                        </div>
                    </div>

                    <div id="cp_team_empty" class="hidden text-center py-4 text-xs text-slate-400">
                        Nhóm này chưa có nhân viên nào.
                    </div>
                </div>

                {{-- ④ Mức phạt --}}
                <div class="flex flex-wrap gap-4">
                    <div class="flex-1 min-w-[120px]">
                        <label class="form-label">Điểm trừ <span class="text-red-500">*</span></label>
                        <input type="number" id="cp_points" name="points_deducted"
                               class="form-input" min="0" max="100"
                               value="{{ old('points_deducted', 0) }}"
                               oninput="cpSyncTeamPoints(this.value)" required>
                        @error('points_deducted') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div id="cp_money_wrap" class="hidden flex-1 min-w-[120px]">
                        <label class="form-label">Tiền phạt</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none">₫</span>
                            <input type="text" id="cp_money_display"
                                   class="form-input pl-7 bg-slate-50 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300"
                                   readonly tabindex="-1">
                        </div>
                        <input type="hidden" id="cp_money" name="money_deducted" value="{{ old('money_deducted', 0) }}">
                        @error('money_deducted') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- ⑤ Mô tả --}}
                <div>
                    <label class="form-label">Mô tả / Ghi chú</label>
                    <textarea name="description" rows="2" class="form-input"
                              placeholder="Chi tiết về vi phạm...">{{ old('description') }}</textarea>
                    @error('description') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                {{-- ⑥ Đính kèm --}}
                <div>
                    <label class="form-label">Hình ảnh / Video đính kèm</label>

                    {{-- Drop zone --}}
                    <label for="cp_attachments"
                           id="cp_dropzone"
                           class="flex flex-col items-center justify-center gap-2 w-full rounded-xl border-2 border-dashed border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/30 py-5 cursor-pointer hover:border-pcrm-400 dark:hover:border-pcrm-500 transition-colors"
                           ondragover="event.preventDefault();this.classList.add('border-pcrm-400')"
                           ondragleave="this.classList.remove('border-pcrm-400')"
                           ondrop="cpHandleDrop(event)">
                        <i class="bi bi-cloud-arrow-up text-2xl text-slate-400 dark:text-slate-500"></i>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            Kéo thả hoặc <span class="text-pcrm-600 dark:text-pcrm-400 font-medium">chọn file</span>
                        </p>
                        <p class="text-xs text-slate-400 dark:text-slate-500">
                            Ảnh: jpg · png · gif · webp · heic (max 10MB) &nbsp;|&nbsp; Video: mp4 · mov · avi · webm (max 20MB)
                        </p>
                        <input type="file" id="cp_attachments" name="attachments[]"
                               multiple
                               accept="image/jpeg,image/png,image/gif,image/webp,image/heic,image/heif,.heic,.heif,video/mp4,video/quicktime,video/avi,video/webm,.mov,.avi"
                               class="hidden"
                               onchange="cpOnFilesChange(this.files)">
                    </label>

                    @error('attachments') <p class="form-error mt-1">{{ $message }}</p> @enderror
                    @foreach($errors->get('attachments.*') as $msgs)
                        @foreach($msgs as $msg)
                            <p class="form-error mt-1">{{ $msg }}</p>
                        @endforeach
                    @endforeach

                    {{-- Preview grid --}}
                    <div id="cp_attach_preview"
                         class="hidden grid grid-cols-3 sm:grid-cols-4 gap-2 mt-3"></div>
                </div>

            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-200 dark:border-slate-700 shrink-0">
                <button type="button" onclick="closeModal('createPenaltyModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-danger">
                    <i class="bi bi-gavel"></i> Tạo phiếu phạt
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function () {
    'use strict';

    /* ── Static data from Blade ── */
    var REG_VIOLATIONS = @json($regulationViolationsMap);   // { reg_id: [{id,name,points,money,type},...] }
    var TEAM_EMPLOYEES  = @json($teamEmployeesMap);          // { team_id: [{id,code,name},...] }

    var _type      = 'individual'; // 'individual' | 'team'
    var _memberIdx = {{ old('members') ? count(old('members')) : 0 }};

    /* ──────────────────────────────────────────────────
       ① Regulation → filter violations
    ────────────────────────────────────────────────── */
    window.cpOnRegulationChange = function (regId) {
        var sel   = document.getElementById('cp_violation');
        var opts  = sel.querySelectorAll('option');
        var found = false;

        opts.forEach(function (opt) {
            if (!opt.value) return; // keep placeholder
            var match = !regId || String(opt.dataset.reg) === String(regId);
            opt.hidden   = !match;
            opt.disabled = !match;
        });

        // reset violation if current selection no longer visible
        var cur = sel.options[sel.selectedIndex];
        if (cur && cur.hidden) { sel.value = ''; cpOnViolationChange(''); }
    };

    /* ──────────────────────────────────────────────────
       ② Violation → fill points / money
    ────────────────────────────────────────────────── */
    window.cpOnViolationChange = function (violId) {
        var regId = document.getElementById('cp_regulation').value;
        var vios  = REG_VIOLATIONS[regId] || [];
        var vio   = vios.find(function (v) { return String(v.id) === String(violId); });

        if (!vio) {
            Object.values(REG_VIOLATIONS).forEach(function (arr) {
                arr.forEach(function (v) { if (String(v.id) === String(violId)) vio = v; });
            });
        }

        var moneyWrap    = document.getElementById('cp_money_wrap');
        var moneyHidden  = document.getElementById('cp_money');
        var moneyDisplay = document.getElementById('cp_money_display');

        if (vio) {
            var hasMoney  = vio.type === 'money' || vio.type === 'both';
            var pts       = (vio.type === 'money') ? 0 : vio.points;

            document.getElementById('cp_points').value = pts;
            moneyHidden.value = vio.money;

            moneyWrap.classList.toggle('hidden', !hasMoney);
            if (hasMoney) {
                moneyDisplay.value = new Intl.NumberFormat('vi-VN').format(vio.money);
            }

            cpSyncTeamPoints(pts);
        } else {
            document.getElementById('cp_points').value = 0;
            moneyHidden.value = 0;
            moneyWrap.classList.add('hidden');
        }
    };

    /* ──────────────────────────────────────────────────
       ③ Penalty type toggle
    ────────────────────────────────────────────────── */
    window.cpSetType = function (type) {
        _type = type;
        var isInd  = type === 'individual';
        var active = 'bg-pcrm-600 text-white';
        var idle   = 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700';

        document.getElementById('cp_type_btn_individual').className =
            'flex-1 py-2 flex items-center justify-center gap-2 transition-colors ' + (isInd ? active : idle);
        document.getElementById('cp_type_btn_team').className =
            'flex-1 py-2 flex items-center justify-center gap-2 transition-colors ' + (!isInd ? active : idle);

        document.getElementById('cp_panel_individual').classList.toggle('hidden', !isInd);
        document.getElementById('cp_panel_team').classList.toggle('hidden', isInd);
    };

    /* ──────────────────────────────────────────────────
       ④ Individual: add extra member row
    ────────────────────────────────────────────────── */
    window.cpAddIndividualMember = function () {
        var container = document.getElementById('cp_individual_members');
        var points    = document.getElementById('cp_points').value || 0;
        var opts      = _buildEmployeeOptions();
        var row       = document.createElement('div');
        row.className = 'flex gap-2 items-center cp-member-row';
        row.innerHTML =
            '<select name="members[' + _memberIdx + '][employee_id]" class="form-input flex-1 text-sm" required>'
            + '<option value="">-- Chọn NV --</option>' + opts + '</select>'
            + '<input type="number" name="members[' + _memberIdx + '][points_deducted]"'
            + '       class="form-input w-24 text-sm" min="0" max="100" value="' + points + '" required>'
            + '<button type="button" onclick="this.closest(\'.cp-member-row\').remove()"'
            + '        class="w-9 h-9 flex items-center justify-center rounded-lg text-red-400'
            + '               hover:bg-red-50 dark:hover:bg-red-900/20 shrink-0">'
            + '<i class="bi bi-trash text-sm"></i></button>';
        container.appendChild(row);
        _memberIdx++;
    };

    function _buildEmployeeOptions() {
        // Build from DOM (regulation-filtered violations already handled; just all employees)
        var sel  = document.getElementById('cp_individual_employee');
        if (!sel) return '';
        return Array.from(sel.options).slice(1).map(function (o) {
            return '<option value="' + o.value + '">' + o.textContent.trim() + '</option>';
        }).join('');
    }

    /* ──────────────────────────────────────────────────
       ⑤ Team: load members into table
    ────────────────────────────────────────────────── */
    window.cpOnTeamChange = function (teamId) {
        var wrap      = document.getElementById('cp_team_members_wrap');
        var emptyMsg  = document.getElementById('cp_team_empty');
        var tbody     = document.getElementById('cp_team_member_rows');
        var employees = TEAM_EMPLOYEES[teamId] || [];

        if (!teamId || employees.length === 0) {
            wrap.classList.add('hidden');
            emptyMsg.classList.toggle('hidden', !teamId);
            tbody.innerHTML = '';
            return;
        }

        emptyMsg.classList.add('hidden');
        var points = document.getElementById('cp_points').value || 0;

        tbody.innerHTML = employees.map(function (e, idx) {
            var isFirst = idx === 0;
            return '<tr id="cp_team_row_' + e.id + '" data-id="' + e.id + '"'
                + '    class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/30">'
                + '  <td class="px-3 py-2.5">'
                + '    <input type="radio" name="cp_team_primary_radio" value="' + e.id + '"'
                + '           class="accent-pcrm-600 cursor-pointer"'
                + '           onchange="cpUpdateTeamMemberCount()"'
                + (isFirst ? ' checked' : '') + '>'
                + '  </td>'
                + '  <td class="px-3 py-2.5">'
                + '    <input type="checkbox" class="cp-team-cb accent-pcrm-600 cursor-pointer"'
                + '           data-id="' + e.id + '" checked'
                + '           onchange="cpUpdateTeamMemberCount()">'
                + '  </td>'
                + '  <td class="px-3 py-2.5">'
                + '    <p class="font-medium text-slate-800 dark:text-slate-200 text-sm leading-none">' + e.name + '</p>'
                + '    <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">' + e.code + '</p>'
                + '  </td>'
                + '  <td class="px-3 py-2.5 text-right">'
                + '    <input type="number" class="cp-team-points form-input text-sm text-right py-1 px-2 w-20"'
                + '           data-id="' + e.id + '" min="0" max="100" value="' + points + '">'
                + '  </td>'
                + '</tr>';
        }).join('');

        wrap.classList.remove('hidden');
        cpUpdateTeamMemberCount();
    };

    window.cpSyncTeamPoints = function (val) {
        document.querySelectorAll('.cp-team-points').forEach(function (inp) {
            inp.value = val;
        });
    };

    window.cpSelectAllTeamMembers = function (checked) {
        document.querySelectorAll('.cp-team-cb').forEach(function (cb) { cb.checked = checked; });
        cpUpdateTeamMemberCount();
    };

    window.cpUpdateTeamMemberCount = function () {
        var total   = document.querySelectorAll('.cp-team-cb').length;
        var checked = document.querySelectorAll('.cp-team-cb:checked').length;
        var el = document.getElementById('cp_team_member_count');
        if (el) el.textContent = checked + ' / ' + total + ' thành viên được chọn';
    };

    /* ──────────────────────────────────────────────────
       ⑥ Pre-submit: serialize form based on type
    ────────────────────────────────────────────────── */
    window.cpBeforeSubmit = function (form) {
        if (_type === 'individual') {
            var sel = document.getElementById('cp_individual_employee');
            if (!sel || !sel.value) {
                alert('Vui lòng chọn nhân viên vi phạm.');
                return false;
            }
            document.getElementById('cp_employee_id').value = sel.value;
            return true;
        }

        // === Team type ===
        var teamSel = document.getElementById('cp_team_select');
        if (!teamSel || !teamSel.value) {
            alert('Vui lòng chọn nhóm.');
            return false;
        }

        var radios   = document.querySelectorAll('input[name="cp_team_primary_radio"]');
        var primaryId = null;
        radios.forEach(function (r) { if (r.checked) primaryId = r.value; });

        if (!primaryId) {
            alert('Vui lòng chọn người chịu trách nhiệm chính (●).');
            return false;
        }

        // Check at least one member checked
        var checkedMembers = Array.from(document.querySelectorAll('.cp-team-cb:checked'));
        if (checkedMembers.length === 0) {
            alert('Vui lòng chọn ít nhất một thành viên để phạt.');
            return false;
        }

        document.getElementById('cp_employee_id').value = primaryId;

        // Sync primary employee's individual points override to the global field
        var primaryPtsInput = form.querySelector('.cp-team-points[data-id="' + primaryId + '"]');
        if (primaryPtsInput && primaryPtsInput.value !== '') {
            document.getElementById('cp_points').value = primaryPtsInput.value;
        }

        // Remove previously injected team member inputs
        form.querySelectorAll('.cp-team-hidden').forEach(function (el) { el.remove(); });

        // Inject members[] for every checked row EXCEPT primary
        var idx = 0;
        checkedMembers.forEach(function (cb) {
            var empId = cb.dataset.id;
            if (empId === primaryId) return; // primary → employee_id, not members[]
            var pts = (form.querySelector('.cp-team-points[data-id="' + empId + '"]') || {}).value || 0;
            _injectHidden(form, 'members[' + idx + '][employee_id]',    empId);
            _injectHidden(form, 'members[' + idx + '][points_deducted]', pts);
            idx++;
        });

        return true;
    };

    function _injectHidden(form, name, value) {
        var inp = document.createElement('input');
        inp.type      = 'hidden';
        inp.name      = name;
        inp.value     = value;
        inp.className = 'cp-team-hidden';
        form.appendChild(inp);
    }

    /* ── File attachment preview ── */
    var _attachFiles = [];  // DataTransfer cannot be modified in all browsers, track manually

    window.cpOnFilesChange = function (fileList) {
        Array.from(fileList).forEach(function (f) { _attachFiles.push(f); });
        _renderAttachPreview();
    };

    window.cpHandleDrop = function (e) {
        e.preventDefault();
        document.getElementById('cp_dropzone').classList.remove('border-pcrm-400');
        cpOnFilesChange(e.dataTransfer.files);
    };

    function _renderAttachPreview() {
        var grid = document.getElementById('cp_attach_preview');
        if (!grid) return;

        grid.innerHTML = '';
        if (_attachFiles.length === 0) { grid.classList.add('hidden'); return; }
        grid.classList.remove('hidden');

        _attachFiles.forEach(function (f, idx) {
            var wrap = document.createElement('div');
            wrap.className = 'relative group rounded-lg overflow-hidden border border-slate-200 dark:border-slate-600 bg-slate-100 dark:bg-slate-700 aspect-square flex items-center justify-center';

            if (f.type.startsWith('image/')) {
                var img = document.createElement('img');
                img.src = URL.createObjectURL(f);
                img.className = 'w-full h-full object-cover';
                wrap.appendChild(img);
            } else {
                // Video placeholder
                var icon = document.createElement('div');
                icon.className = 'flex flex-col items-center gap-1 p-2 text-center';
                icon.innerHTML = '<i class="bi bi-film text-2xl text-slate-400"></i>'
                    + '<p class="text-[10px] text-slate-400 break-all leading-tight">' + f.name + '</p>'
                    + '<p class="text-[9px] text-slate-300 dark:text-slate-500">' + _fmtSize(f.size) + '</p>';
                wrap.appendChild(icon);
            }

            // Remove button
            var rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'absolute top-1 right-1 w-5 h-5 rounded-full bg-black/60 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity text-xs';
            rm.innerHTML = '<i class="bi bi-x"></i>';
            rm.dataset.idx = idx;
            rm.onclick = function () {
                _attachFiles.splice(parseInt(this.dataset.idx), 1);
                _rebuildFileInput();
                _renderAttachPreview();
            };
            wrap.appendChild(rm);
            grid.appendChild(wrap);
        });

        _rebuildFileInput();
    }

    function _rebuildFileInput() {
        // Replace the file input's files with a new DataTransfer (Chrome/Firefox/Edge)
        try {
            var dt = new DataTransfer();
            _attachFiles.forEach(function (f) { dt.items.add(f); });
            document.getElementById('cp_attachments').files = dt.files;
        } catch (e) { /* Safari fallback — files still tracked in _attachFiles */ }
    }

    function _fmtSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return Math.round(bytes / 1024) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    /* ── Auto-open modal on validation error ── */
    @if($errors->any())
    document.addEventListener('DOMContentLoaded', function () {
        openModal('createPenaltyModal');
        @if(old('_regulation_id'))
        cpOnRegulationChange('{{ old('_regulation_id') }}');
        @endif
    });
    @endif
}());
</script>
@endpush
