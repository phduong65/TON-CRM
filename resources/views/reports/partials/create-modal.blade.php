@php
    // @json() splits its expression on every comma, so build the array here first —
    // passing a multi-key array literal inline can silently corrupt the compiled view.
    $crEmployeesData = $employees->map(fn($e) => ['id' => $e->id, 'label' => $e->name . ' (' . $e->code . ')'])->values();
@endphp
<div id="createReportModal"
     class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('createReportModal')">

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-2xl flex flex-col"
         style="max-height:95vh">

        {{-- Header --}}
        <div class="flex-shrink-0 flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-100 dark:border-slate-700">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-flag-fill text-pcrm-500"></i>
                Tạo báo cáo vi phạm
            </h3>
            <button type="button" onclick="closeModal('createReportModal')"
                    class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>

        {{-- Form (wraps scrollable body + sticky footer) --}}
        <form action="{{ route('reports.store') }}" method="POST"
              enctype="multipart/form-data"
              class="flex flex-col flex-1 min-h-0">
            @csrf
            <input type="hidden" name="_modal" value="createReportModal">
            <input type="hidden" name="type" id="cr_type" value="{{ old('type', 'individual') }}">

            {{-- Scrollable body --}}
            <div class="overflow-y-auto flex-1 px-4 sm:px-6 py-4 sm:py-5 space-y-4 sm:space-y-5">

                {{-- Reporter info --}}
                <div class="flex items-center gap-3 p-3 rounded-xl bg-pcrm-50 dark:bg-pcrm-900/20 border border-pcrm-100 dark:border-pcrm-800/30">
                    <div class="w-8 h-8 rounded-full bg-pcrm-100 dark:bg-pcrm-800/40 flex items-center justify-center flex-shrink-0">
                        <i class="bi bi-person-fill text-pcrm-600 dark:text-pcrm-400 text-sm"></i>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Bạn đang báo cáo với tư cách</p>
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                            {{ $currentEmployee->name }}
                            <span class="font-normal text-slate-400 text-xs">({{ $currentEmployee->code }})</span>
                        </p>
                    </div>
                </div>

                {{-- ── Hình thức báo cáo ─────────────────────────────────── --}}
                <div class="space-y-3">
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                        Hình thức báo cáo
                    </p>

                    <div class="flex rounded-xl border border-slate-200 dark:border-slate-600 overflow-hidden">
                        <button type="button" id="cr_type_btn_individual" onclick="crSetType('individual')"
                                class="flex-1 py-2 flex items-center justify-center gap-1.5 text-sm font-medium transition-colors text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">
                            <i class="bi bi-person"></i> Cá nhân
                        </button>
                        <button type="button" id="cr_type_btn_team" onclick="crSetType('team')"
                                class="flex-1 py-2 flex items-center justify-center gap-1.5 text-sm font-medium transition-colors border-l border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">
                            <i class="bi bi-people"></i> Cả nhóm
                        </button>
                        <button type="button" id="cr_type_btn_joint" onclick="crSetType('joint')"
                                class="flex-1 py-2 flex items-center justify-center gap-1.5 text-sm font-medium transition-colors border-l border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">
                            <i class="bi bi-diagram-3"></i> Liên đới
                        </button>
                    </div>
                </div>

                {{-- ── INDIVIDUAL panel ─────────────────────────────────── --}}
                <div id="cr_panel_individual" class="space-y-3">
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                        Nhân viên bị báo cáo
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Lọc theo chi nhánh</label>
                            <select id="cr_branch" class="form-input" onchange="crFilterTeams()">
                                <option value="">Tất cả chi nhánh</option>
                                @foreach($branches as $b)
                                    <option value="{{ $b->id }}">{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Lọc theo team</label>
                            <select id="cr_team_filter" class="form-input" onchange="crFilterEmployees()">
                                <option value="">Tất cả team</option>
                                @foreach($teams as $t)
                                    <option value="{{ $t->id }}" data-branch="{{ $t->branch_id }}">{{ $t->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Chọn nhân viên <span class="text-red-500">*</span></label>
                        <select name="reported_employee_id" id="cr_employee" data-combobox
                                data-combobox-placeholder="Gõ tên hoặc mã nhân viên..."
                                class="form-input @error('reported_employee_id') border-red-400 @enderror">
                            <option value="">-- Chọn nhân viên bị báo cáo --</option>
                            @foreach($employees as $emp)
                                @if($emp->id !== $currentEmployee->id)
                                    <option value="{{ $emp->id }}"
                                            data-branch="{{ $emp->branch_id }}"
                                            data-team="{{ $emp->team_id }}"
                                            @selected(old('reported_employee_id') == $emp->id)>
                                        {{ $emp->name }} ({{ $emp->code }})@if($emp->branch) · {{ $emp->branch->name }}@endif
                                    </option>
                                @endif
                            @endforeach
                        </select>
                        @error('reported_employee_id')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- ── TEAM panel ───────────────────────────────────────── --}}
                <div id="cr_panel_team" class="space-y-3 hidden">
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                        Team bị báo cáo
                    </p>
                    <div>
                        <label class="form-label">Chọn team <span class="text-red-500">*</span></label>
                        <select name="team_id" id="cr_team" data-combobox
                                data-combobox-placeholder="Gõ tên team..."
                                class="form-input @error('team_id') border-red-400 @enderror">
                            <option value="">-- Chọn team --</option>
                            @foreach($teams as $t)
                                <option value="{{ $t->id }}" @selected(old('team_id') == $t->id)>
                                    {{ $t->name }} ({{ $t->employees_count }} nhân viên)
                                </option>
                            @endforeach
                        </select>
                        @error('team_id')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">
                            Toàn bộ nhân viên đang hoạt động trong team sẽ được ghi nhận vào báo cáo này.
                        </p>
                    </div>
                </div>

                {{-- ── JOINT (liên đới) panel ───────────────────────────── --}}
                <div id="cr_panel_joint" class="space-y-3 hidden">
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                        Nhân viên liên đới <span class="font-normal normal-case text-slate-400">(nhiều người khác nhau)</span>
                    </p>

                    <div>
                        <label class="form-label">Nhân viên chính <span class="text-red-500">*</span></label>
                        <select id="cr_joint_primary" data-combobox
                                data-combobox-placeholder="Gõ tên hoặc mã nhân viên..."
                                class="form-input">
                            <option value="">-- Chọn nhân viên --</option>
                            @foreach($employees as $emp)
                                @if($emp->id !== $currentEmployee->id)
                                    <option value="{{ $emp->id }}">{{ $emp->name }} ({{ $emp->code }})</option>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    <div id="cr_joint_members" class="space-y-2"></div>

                    <button type="button" onclick="crAddJointMember()"
                            class="btn-secondary btn-sm">
                        <i class="bi bi-plus-lg"></i> Thêm người liên đới
                    </button>
                </div>

                <div class="border-t border-slate-100 dark:border-slate-700"></div>

                {{-- ── Loại vi phạm ─────────────────────────────────────── --}}
                <div class="space-y-3">
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                        Loại vi phạm
                        <span class="font-normal normal-case text-slate-400">(tuỳ chọn)</span>
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Danh mục / Quy chế</label>
                            <select id="cr_regulation" class="form-input" onchange="crFilterViolations()">
                                <option value="">Tất cả danh mục</option>
                                @foreach($regulations as $reg)
                                    <option value="{{ $reg->id }}">{{ $reg->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Lỗi vi phạm cụ thể</label>
                            <select name="violation_id" id="cr_violation" data-combobox
                                    data-combobox-placeholder="Gõ tên lỗi vi phạm..."
                                    class="form-input">
                                <option value="">-- Không chọn --</option>
                                @foreach($violations as $v)
                                    <option value="{{ $v->id }}"
                                            data-reg="{{ $v->regulation_id }}"
                                            @selected(old('violation_id') == $v->id)>
                                        {{ $v->name }}@if($v->points_deducted > 0) (−{{ $v->points_deducted }} điểm)@endif
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">
                                Hệ thống tự trừ điểm từng người khi báo cáo được duyệt.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-100 dark:border-slate-700"></div>

                {{-- ── Nội dung báo cáo ─────────────────────────────────── --}}
                <div class="space-y-3">
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                        Nội dung báo cáo
                    </p>

                    <div>
                        <label class="form-label">Mô tả sự việc <span class="text-red-500">*</span></label>
                        <textarea name="description" rows="3"
                                  class="form-input @error('description') border-red-400 @enderror"
                                  placeholder="Mô tả chi tiết sự việc vi phạm bạn chứng kiến...">{{ old('description') }}</textarea>
                        @error('description')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="form-label">
                            Ghi chú bổ sung
                            <span class="text-slate-400 text-xs font-normal">(tuỳ chọn)</span>
                        </label>
                        <textarea name="evidence_note" rows="2"
                                  class="form-input"
                                  placeholder="Camera số mấy, nhân chứng, thời gian cụ thể...">{{ old('evidence_note') }}</textarea>
                    </div>
                </div>

                <div class="border-t border-slate-100 dark:border-slate-700"></div>

                {{-- ── File bằng chứng ──────────────────────────────────── --}}
                <div class="space-y-3">
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                        File bằng chứng
                        <span class="font-normal normal-case text-slate-400">(tuỳ chọn · tối đa 5 file)</span>
                    </p>

                    {{-- Dropzone --}}
                    <div id="cr_dropzone"
                         class="border-2 border-dashed border-slate-200 dark:border-slate-600 rounded-xl p-4 sm:p-6 text-center cursor-pointer transition-colors hover:border-pcrm-400 dark:hover:border-pcrm-500 hover:bg-pcrm-50/30 dark:hover:bg-pcrm-900/10"
                         onclick="document.getElementById('cr_files').click()"
                         ondragover="crDragOver(event)"
                         ondragleave="crDragLeave()"
                         ondrop="crDrop(event)">
                        <i class="bi bi-cloud-arrow-up text-4xl text-slate-300 dark:text-slate-600 pointer-events-none"></i>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-2 pointer-events-none">
                            Kéo thả hoặc
                            <span class="text-pcrm-600 dark:text-pcrm-400 font-medium">nhấp để chọn file</span>
                        </p>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1 pointer-events-none hidden sm:block">
                            Ảnh JPG · PNG · WEBP (tự thu nhỏ về 1000×1000) &nbsp;·&nbsp; Video MP4 · MOV dưới 20 MB
                        </p>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1 pointer-events-none sm:hidden">
                            Ảnh, Video · Tối đa 5 file · 20 MB/file
                        </p>
                        <input type="file" id="cr_files" name="evidence_files[]"
                               multiple
                               accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/quicktime,video/webm,video/x-msvideo"
                               class="hidden"
                               onchange="crUpdatePreview()">
                    </div>

                    @error('evidence_files')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                    @error('evidence_files.*')
                        <p class="form-error">{{ $message }}</p>
                    @enderror

                    {{-- Preview grid (hidden when empty) --}}
                    <div id="cr_preview" class="grid grid-cols-4 sm:grid-cols-5 gap-2 hidden"></div>
                </div>

            </div>{{-- /scrollable body --}}

            {{-- Footer --}}
            <div class="flex-shrink-0 flex items-center justify-end gap-2 sm:gap-3 px-4 sm:px-6 py-3 sm:py-4 border-t border-slate-100 dark:border-slate-700">
                <button type="button" onclick="closeModal('createReportModal')" class="btn-secondary">Huỷ</button>
                <button type="submit" class="btn-primary">
                    <i class="bi bi-send-fill"></i>
                    Gửi báo cáo
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
// ── Create Report Modal ──────────────────────────────────────────────────────

var CR_EMPLOYEES = @json($crEmployeesData);
var crJointIdx = 0;

function crSetType(type) {
    document.getElementById('cr_type').value = type;

    ['individual', 'team', 'joint'].forEach(function (t) {
        var panel = document.getElementById('cr_panel_' + t);
        var btn   = document.getElementById('cr_type_btn_' + t);
        var active = t === type;
        panel.classList.toggle('hidden', !active);
        btn.classList.toggle('bg-pcrm-600', active);
        btn.classList.toggle('text-white', active);
        btn.classList.toggle('text-slate-600', !active);
        btn.classList.toggle('dark:text-slate-300', !active);
    });

    // reported_employee_id is submitted for individual; joint uses its own primary select
    // whose value gets copied into reported_employee_id on submit (see form submit handler).
    document.getElementById('cr_employee').required = (type === 'individual');
    document.getElementById('cr_team').required      = (type === 'team');
}

function crFilterTeams() {
    var branchId = document.getElementById('cr_branch').value;
    var teamSel  = document.getElementById('cr_team_filter');
    teamSel.value = '';
    Array.from(teamSel.options).forEach(function(opt) {
        if (!opt.dataset.branch) return;
        var show     = !branchId || opt.dataset.branch === branchId;
        opt.hidden   = !show;
        opt.disabled = !show;
    });
    crFilterEmployees();
}

function crFilterEmployees() {
    var branchId = document.getElementById('cr_branch').value;
    var teamId   = document.getElementById('cr_team_filter').value;
    var empSel   = document.getElementById('cr_employee');
    empSel.value = '';
    Array.from(empSel.options).forEach(function(opt) {
        if (!opt.value) return;
        var ok       = (!branchId || opt.dataset.branch === branchId)
                    && (!teamId   || opt.dataset.team   === teamId);
        opt.hidden   = !ok;
        opt.disabled = !ok;
    });
    if (window.comboboxRefresh) comboboxRefresh(empSel);
}

function crFilterViolations() {
    var regId   = document.getElementById('cr_regulation').value;
    var violSel = document.getElementById('cr_violation');
    violSel.value = '';
    Array.from(violSel.options).forEach(function(opt) {
        if (!opt.value) return;
        var show     = !regId || opt.dataset.reg === regId;
        opt.hidden   = !show;
        opt.disabled = !show;
    });
    if (window.comboboxRefresh) comboboxRefresh(violSel);
}

// ── Joint (liên đới) member rows ────────────────────────────────────────────

function crAddJointMember() {
    var idx = crJointIdx++;
    var wrap = document.createElement('div');
    wrap.className = 'flex items-center gap-2';
    wrap.id = 'cr_joint_row_' + idx;

    var selectWrap = document.createElement('div');
    selectWrap.className = 'flex-1';

    var select = document.createElement('select');
    select.name = 'members[]';
    select.className = 'form-input text-sm';
    select.setAttribute('data-combobox', '');
    select.setAttribute('data-combobox-placeholder', 'Gõ tên hoặc mã nhân viên...');

    var opt0 = document.createElement('option');
    opt0.value = '';
    opt0.textContent = '-- Chọn người liên đới --';
    select.appendChild(opt0);

    CR_EMPLOYEES.forEach(function (e) {
        var o = document.createElement('option');
        o.value = e.id;
        o.textContent = e.label;
        select.appendChild(o);
    });

    selectWrap.appendChild(select);
    wrap.appendChild(selectWrap);

    var rmBtn = document.createElement('button');
    rmBtn.type = 'button';
    rmBtn.className = 'w-9 h-9 flex items-center justify-center rounded-lg text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 shrink-0';
    rmBtn.innerHTML = '<i class="bi bi-trash"></i>';
    rmBtn.onclick = function () { wrap.remove(); };
    wrap.appendChild(rmBtn);

    document.getElementById('cr_joint_members').appendChild(wrap);
    if (window.comboboxInit) window.comboboxInit(wrap);
}

// Copy the "primary" joint employee select into the real reported_employee_id
// field right before submit, so the backend always reads one field name.
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('createReportModal')?.querySelector('form');
    if (!form) return;
    form.addEventListener('submit', function () {
        if (document.getElementById('cr_type').value === 'joint') {
            document.getElementById('cr_employee').value = document.getElementById('cr_joint_primary').value;
        }
    });
    crSetType(document.getElementById('cr_type').value || 'individual');
});

// ── Drag-and-drop ────────────────────────────────────────────────────────────

function crDragOver(e) {
    e.preventDefault();
    var dz = document.getElementById('cr_dropzone');
    dz.style.borderColor = 'var(--color-pcrm-400, #6366f1)';
    dz.style.background  = 'rgba(99,102,241,0.04)';
}

function crDragLeave() {
    var dz = document.getElementById('cr_dropzone');
    dz.style.borderColor = '';
    dz.style.background  = '';
}

function crDrop(e) {
    e.preventDefault();
    crDragLeave();
    var input = document.getElementById('cr_files');
    var dt    = new DataTransfer();
    Array.from(input.files).forEach(function(f) { dt.items.add(f); });
    Array.from(e.dataTransfer.files).forEach(function(f) { dt.items.add(f); });
    input.files = dt.files;
    crUpdatePreview();
}

function crRemoveFile(idx) {
    var input = document.getElementById('cr_files');
    var dt    = new DataTransfer();
    Array.from(input.files).forEach(function(f, i) { if (i !== idx) dt.items.add(f); });
    input.files = dt.files;
    crUpdatePreview();
}

function crUpdatePreview() {
    var input   = document.getElementById('cr_files');
    var preview = document.getElementById('cr_preview');
    var files   = Array.from(input.files);

    // Enforce 5-file limit
    if (files.length > 5) {
        var dt = new DataTransfer();
        files.slice(0, 5).forEach(function(f) { dt.items.add(f); });
        input.files = dt.files;
        files = files.slice(0, 5);
    }

    if (files.length === 0) {
        preview.classList.add('hidden');
        preview.innerHTML = '';
        return;
    }

    preview.classList.remove('hidden');
    preview.innerHTML = '';

    files.forEach(function(file, idx) {
        var item = document.createElement('div');
        item.className = 'relative rounded-xl overflow-hidden aspect-square bg-slate-100 dark:bg-slate-700/60 group';

        if (file.type.startsWith('image/')) {
            var img = document.createElement('img');
            var url = URL.createObjectURL(file);
            img.src = url;
            img.className = 'w-full h-full object-cover';
            img.onload = function() { URL.revokeObjectURL(url); };
            item.appendChild(img);
        } else {
            var sizeMB = (file.size / 1024 / 1024).toFixed(1);
            var inner  = document.createElement('div');
            inner.className = 'w-full h-full flex flex-col items-center justify-center gap-1 p-2';
            inner.innerHTML = '<i class="bi bi-camera-video-fill text-2xl text-slate-400 dark:text-slate-500"></i>'
                + '<span style="font-size:10px" class="text-slate-500 dark:text-slate-400 text-center leading-tight line-clamp-2 w-full">'
                + file.name + '</span>'
                + '<span style="font-size:10px" class="text-slate-400">' + sizeMB + ' MB</span>';
            item.appendChild(inner);
        }

        var rmBtn = document.createElement('button');
        rmBtn.type      = 'button';
        rmBtn.className = 'absolute top-1 right-1 w-5 h-5 rounded-full bg-red-500 hover:bg-red-600 flex items-center justify-center text-white opacity-0 group-hover:opacity-100 transition-opacity';
        rmBtn.innerHTML = '<i class="bi bi-x" style="font-size:11px"></i>';
        rmBtn.onclick   = (function(i) { return function(e) { e.stopPropagation(); crRemoveFile(i); }; })(idx);
        item.appendChild(rmBtn);

        preview.appendChild(item);
    });
}
</script>
@endpush
