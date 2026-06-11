{{-- ── Penalty Edit Modal ── --}}
<div id="editPenaltyModal"
     class="hidden fixed inset-0 bg-black/50 z-50 items-center justify-center p-4"
     onclick="if(event.target===this)closeModal('editPenaltyModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-2xl flex flex-col"
         style="max-height:92vh">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 shrink-0">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-pencil-square text-amber-500"></i> Sửa phiếu phạt
            </h3>
            <button onclick="closeModal('editPenaltyModal')"
                    class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>

        {{-- Form --}}
        <form id="editPenaltyForm" method="POST"
              enctype="multipart/form-data"
              class="flex flex-col flex-1 overflow-hidden">
            @csrf @method('PUT')
            <input type="hidden" name="_modal" value="editPenaltyModal">

            <div class="overflow-y-auto flex-1 px-6 py-5 space-y-5">

                {{-- ① Quy chế --}}
                <div>
                    <label class="form-label">Quy chế</label>
                    <select id="ep_regulation" name="_regulation_id" class="form-input"
                            onchange="epOnRegulationChange(this.value)">
                        <option value="">-- Tất cả quy chế --</option>
                        @foreach($regulations as $reg)
                        <option value="{{ $reg->id }}">{{ $reg->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Chọn quy chế để lọc danh sách vi phạm.</p>
                </div>

                {{-- ② Vi phạm --}}
                <div>
                    <label class="form-label">Vi phạm <span class="text-red-500">*</span></label>
                    <select name="violation_id" id="ep_violation_id" class="form-input"
                            onchange="epOnViolationChange(this.value)" required>
                        <option value="">-- Chọn vi phạm --</option>
                        @foreach($violations as $v)
                        <option value="{{ $v->id }}"
                                data-reg="{{ $v->regulation_id ?? 0 }}">
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

                {{-- ③ Nhân viên vi phạm --}}
                <div>
                    <label class="form-label">Nhân viên vi phạm <span class="text-red-500">*</span></label>
                    <select name="employee_id" id="ep_employee_id" class="form-input" required>
                        <option value="">-- Chọn nhân viên --</option>
                        @foreach($employees as $e)
                        <option value="{{ $e->id }}">
                            {{ $e->code }} — {{ $e->name }}
                            @if($e->branch) ({{ $e->branch->name }}) @endif
                        </option>
                        @endforeach
                    </select>
                    @error('employee_id') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                {{-- ④ Thành viên liên đới --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="form-label mb-0 text-sm">Thành viên liên đới</label>
                        <button type="button" onclick="epAddMember()"
                                class="inline-flex items-center gap-1 text-xs text-pcrm-600 dark:text-pcrm-400 hover:underline">
                            <i class="bi bi-person-plus"></i> Thêm
                        </button>
                    </div>
                    <div id="ep_members" class="space-y-2"></div>
                </div>

                {{-- ⑤ Mức phạt --}}
                <div class="flex flex-wrap gap-4">
                    <div class="flex-1 min-w-[120px]">
                        <label class="form-label">Điểm trừ <span class="text-red-500">*</span></label>
                        <input type="number" name="points_deducted" id="ep_points"
                               class="form-input" min="0" max="100" required>
                        @error('points_deducted') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                    <div id="ep_money_wrap" class="hidden flex-1 min-w-[120px]">
                        <label class="form-label">Tiền phạt</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none">₫</span>
                            <input type="text" id="ep_money_display"
                                   class="form-input pl-7 bg-slate-50 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300"
                                   readonly tabindex="-1">
                        </div>
                        <input type="hidden" id="ep_money" name="money_deducted" value="0">
                        @error('money_deducted') <p class="form-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- ⑥ Mô tả --}}
                <div>
                    <label class="form-label">Mô tả / Ghi chú</label>
                    <textarea name="description" id="ep_description" rows="2" class="form-input"
                              placeholder="Chi tiết về vi phạm..."></textarea>
                    @error('description') <p class="form-error">{{ $message }}</p> @enderror
                </div>

                {{-- ⑦ Đính kèm hiện tại --}}
                <div id="ep_existing_wrap" class="hidden">
                    <label class="form-label">Ảnh / Video đã đính kèm</label>
                    <div id="ep_existing_grid"
                         class="grid grid-cols-3 sm:grid-cols-4 gap-2"></div>
                </div>

                {{-- ⑧ Thêm đính kèm mới --}}
                <div>
                    <label class="form-label">Thêm ảnh / video mới</label>
                    <label for="ep_attachments"
                           id="ep_dropzone"
                           class="flex flex-col items-center justify-center gap-2 w-full rounded-xl border-2 border-dashed border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/30 py-5 cursor-pointer hover:border-pcrm-400 dark:hover:border-pcrm-500 transition-colors"
                           ondragover="event.preventDefault();this.classList.add('border-pcrm-400')"
                           ondragleave="this.classList.remove('border-pcrm-400')"
                           ondrop="epHandleDrop(event)">
                        <i class="bi bi-cloud-arrow-up text-2xl text-slate-400 dark:text-slate-500"></i>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            Kéo thả hoặc <span class="text-pcrm-600 dark:text-pcrm-400 font-medium">chọn file</span>
                        </p>
                        <p class="text-xs text-slate-400 dark:text-slate-500">
                            Ảnh: jpg · png · gif · webp · heic (max 10MB) &nbsp;|&nbsp; Video: mp4 · mov · avi · webm (max 20MB)
                        </p>
                        <input type="file" id="ep_attachments" name="attachments[]"
                               multiple
                               accept="image/jpeg,image/png,image/gif,image/webp,image/heic,image/heif,.heic,.heif,video/mp4,video/quicktime,video/avi,video/webm,.mov,.avi"
                               class="hidden"
                               onchange="epOnFilesChange(this.files)">
                    </label>
                    <div id="ep_new_preview"
                         class="hidden grid grid-cols-3 sm:grid-cols-4 gap-2 mt-3"></div>
                </div>

            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-200 dark:border-slate-700 shrink-0">
                <button type="button" onclick="closeModal('editPenaltyModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-primary">
                    <i class="bi bi-floppy"></i> Cập nhật
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function () {
    'use strict';

    var EP_DEFAULTS = @json($violationDefaults);
    var _epIdx     = 0;
    var _epFiles   = [];

    /* ── ① Regulation filter ── */
    window.epOnRegulationChange = function (regId) {
        var sel  = document.getElementById('ep_violation_id');
        var opts = sel.querySelectorAll('option');
        opts.forEach(function (opt) {
            if (!opt.value) return;
            var match  = !regId || String(opt.dataset.reg) === String(regId);
            opt.hidden   = !match;
            opt.disabled = !match;
        });
        var cur = sel.options[sel.selectedIndex];
        if (cur && cur.hidden) { sel.value = ''; }
    };

    /* ── ② Violation → fill points/money ── */
    window.epOnViolationChange = function (violId) {
        var d = EP_DEFAULTS[violId];
        if (!d) return;

        var hasMoney     = d.type === 'money' || d.type === 'both';
        var moneyWrap    = document.getElementById('ep_money_wrap');
        var moneyHidden  = document.getElementById('ep_money');
        var moneyDisplay = document.getElementById('ep_money_display');

        document.getElementById('ep_points').value = (d.type === 'money') ? 0 : d.points;
        moneyHidden.value = d.money;

        moneyWrap.classList.toggle('hidden', !hasMoney);
        if (hasMoney) {
            moneyDisplay.value = new Intl.NumberFormat('vi-VN').format(d.money);
        }
    };

    /* ── ③ Add linked member row ── */
    window.epAddMember = function (empId, pts) {
        var container = document.getElementById('ep_members');
        var points    = (pts !== undefined && pts !== null) ? pts : (document.getElementById('ep_points').value || 0);
        var opts      = _buildEpEmpOptions(empId);
        var row       = document.createElement('div');
        row.className = 'flex gap-2 items-center ep-member-row';
        row.innerHTML =
            '<select name="members[' + _epIdx + '][employee_id]" class="form-input flex-1 text-sm" required>'
            + '<option value="">-- Chọn NV --</option>' + opts + '</select>'
            + '<input type="number" name="members[' + _epIdx + '][points_deducted]"'
            + '       class="form-input w-24 text-sm" min="0" max="100" value="' + points + '" required>'
            + '<button type="button" onclick="this.closest(\'.ep-member-row\').remove()"'
            + '        class="w-9 h-9 flex items-center justify-center rounded-lg text-red-400'
            + '               hover:bg-red-50 dark:hover:bg-red-900/20 shrink-0">'
            + '<i class="bi bi-trash text-sm"></i></button>';
        container.appendChild(row);
        _epIdx++;
    };

    function _buildEpEmpOptions(selectedId) {
        var sel = document.getElementById('ep_employee_id');
        if (!sel) return '';
        return Array.from(sel.options).slice(1).map(function (o) {
            var sel = String(o.value) === String(selectedId) ? ' selected' : '';
            return '<option value="' + o.value + '"' + sel + '>' + o.textContent.trim() + '</option>';
        }).join('');
    }

    /* ── ④ Open modal (called from data-* button) ── */
    window.epOpenFromBtn = function (btn) {
        openEditPenaltyModal(
            btn.dataset.epId,
            btn.dataset.epViolation,
            btn.dataset.epRegulation,
            btn.dataset.epEmployee,
            btn.dataset.epPoints,
            btn.dataset.epMoney,
            btn.dataset.epDesc,
            JSON.parse(btn.dataset.epMembers  || '[]'),
            JSON.parse(btn.dataset.epAttachments || '[]')
        );
    };

    window.openEditPenaltyModal = function (id, violationId, regulationId, employeeId, points, money, description, members, existingAttachments) {
        _epIdx   = 0;
        _epFiles = [];

        // Reset dynamic sections
        document.getElementById('ep_members').innerHTML = '';
        document.getElementById('ep_new_preview').innerHTML = '';
        document.getElementById('ep_new_preview').classList.add('hidden');
        document.getElementById('ep_attachments').value = '';
        document.querySelectorAll('.ep-del-att').forEach(function (el) { el.remove(); });

        // Form action
        document.getElementById('editPenaltyForm').action = '/penalties/' + id;

        // Regulation filter (set first, then filter violations)
        var regSel = document.getElementById('ep_regulation');
        regSel.value = regulationId || '';
        epOnRegulationChange(regulationId || '');

        // Violation (after filter so option is visible)
        document.getElementById('ep_violation_id').value = violationId;

        // Employee
        document.getElementById('ep_employee_id').value = employeeId;

        // Points / Description
        document.getElementById('ep_points').value      = points;
        document.getElementById('ep_description').value = description || '';

        // Money: set hidden + display if violation has money
        var d         = EP_DEFAULTS[violationId] || {};
        var hasMoney  = d.type === 'money' || d.type === 'both';
        var moneyWrap = document.getElementById('ep_money_wrap');
        document.getElementById('ep_money').value = money || 0;
        moneyWrap.classList.toggle('hidden', !hasMoney);
        if (hasMoney) {
            document.getElementById('ep_money_display').value =
                new Intl.NumberFormat('vi-VN').format(money || 0);
        }

        // Existing members
        if (members && members.length) {
            members.forEach(function (m) { epAddMember(m.employee_id, m.points_deducted); });
        }

        // Existing attachments
        _renderExisting(existingAttachments || []);

        openModal('editPenaltyModal');
    };

    /* ── Existing attachments ── */
    function _renderExisting(list) {
        var wrap = document.getElementById('ep_existing_wrap');
        var grid = document.getElementById('ep_existing_grid');
        grid.innerHTML = '';

        if (!list || list.length === 0) { wrap.classList.add('hidden'); return; }
        wrap.classList.remove('hidden');

        list.forEach(function (att) {
            var cell = document.createElement('div');
            cell.className = 'relative group rounded-lg overflow-hidden border border-slate-200 dark:border-slate-600 bg-slate-100 dark:bg-slate-700 aspect-square flex items-center justify-center';

            if (att.type === 'image') {
                cell.innerHTML = '<img src="' + att.url + '" class="w-full h-full object-cover" alt="">';
            } else {
                cell.innerHTML = '<div class="flex flex-col items-center gap-1 p-2 text-center">'
                    + '<i class="bi bi-film text-2xl text-slate-400"></i>'
                    + '<p class="text-[10px] text-slate-400 break-all leading-tight">' + att.filename + '</p>'
                    + '</div>';
            }

            var rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'absolute top-1 right-1 w-5 h-5 rounded-full bg-black/60 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity text-xs';
            rm.innerHTML = '<i class="bi bi-x"></i>';
            rm.onclick = function () {
                var inp = document.createElement('input');
                inp.type      = 'hidden';
                inp.name      = 'delete_attachment_ids[]';
                inp.value     = att.id;
                inp.className = 'ep-del-att';
                document.getElementById('editPenaltyForm').appendChild(inp);
                cell.remove();
                if (grid.children.length === 0) wrap.classList.add('hidden');
            };
            cell.appendChild(rm);
            grid.appendChild(cell);
        });
    }

    /* ── New file uploads ── */
    window.epOnFilesChange = function (fileList) {
        Array.from(fileList).forEach(function (f) { _epFiles.push(f); });
        _renderNewPreview();
    };

    window.epHandleDrop = function (e) {
        e.preventDefault();
        document.getElementById('ep_dropzone').classList.remove('border-pcrm-400');
        epOnFilesChange(e.dataTransfer.files);
    };

    function _renderNewPreview() {
        var grid = document.getElementById('ep_new_preview');
        grid.innerHTML = '';
        if (_epFiles.length === 0) { grid.classList.add('hidden'); return; }
        grid.classList.remove('hidden');

        _epFiles.forEach(function (f, idx) {
            var wrap = document.createElement('div');
            wrap.className = 'relative group rounded-lg overflow-hidden border border-slate-200 dark:border-slate-600 bg-slate-100 dark:bg-slate-700 aspect-square flex items-center justify-center';

            if (f.type.startsWith('image/')) {
                var img = document.createElement('img');
                img.src = URL.createObjectURL(f);
                img.className = 'w-full h-full object-cover';
                wrap.appendChild(img);
            } else {
                wrap.innerHTML = '<div class="flex flex-col items-center gap-1 p-2 text-center">'
                    + '<i class="bi bi-film text-2xl text-slate-400"></i>'
                    + '<p class="text-[10px] text-slate-400 break-all leading-tight">' + f.name + '</p>'
                    + '</div>';
            }

            var rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'absolute top-1 right-1 w-5 h-5 rounded-full bg-black/60 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity text-xs';
            rm.innerHTML = '<i class="bi bi-x"></i>';
            rm.dataset.idx = idx;
            rm.onclick = function () {
                _epFiles.splice(parseInt(this.dataset.idx), 1);
                _rebuildEpInput();
                _renderNewPreview();
            };
            wrap.appendChild(rm);
            grid.appendChild(wrap);
        });

        _rebuildEpInput();
    }

    function _rebuildEpInput() {
        try {
            var dt = new DataTransfer();
            _epFiles.forEach(function (f) { dt.items.add(f); });
            document.getElementById('ep_attachments').files = dt.files;
        } catch (e) {}
    }
}());
</script>
@endpush
