<div id="dayDetailModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4 font-sans"
     onclick="if(event.target===this)closeModal('dayDetailModal')">
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="text-base sm:text-lg font-semibold text-slate-900 dark:text-white flex items-center gap-2.5 min-w-0" style="font-family: 'Bricolage Grotesque', 'Be Vietnam Pro', sans-serif !important; letter-spacing: -0.02em;">
                <span class="w-9 h-9 rounded-xl bg-pcrm-50 dark:bg-pcrm-900/30 text-pcrm-700 dark:text-pcrm-300 flex items-center justify-center flex-shrink-0"><i class="bi bi-calendar-week"></i></span>
                <span id="dayDetailTitle" class="truncate"></span>
            </h3>
            <button onclick="closeModal('dayDetailModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 flex-shrink-0">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>

        <div class="px-4 sm:px-6 py-4 sm:py-5 grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="min-w-0">
                <p class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-3" style="font-family: 'Bricolage Grotesque', 'Be Vietnam Pro', sans-serif !important; letter-spacing: 0.04em;">
                    Ca làm việc
                </p>
                <div id="dayDetailScheduleList" class="space-y-3"></div>
            </div>
            <div class="min-w-0">
                <p class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-3" style="font-family: 'Bricolage Grotesque', 'Be Vietnam Pro', sans-serif !important; letter-spacing: 0.04em;">
                    Tình trạng chấm công
                </p>
                <div id="dayDetailAttendanceList" class="space-y-3"></div>
            </div>
        </div>

        <div id="dayDetailFooter" class="hidden flex items-center justify-end gap-3 px-4 sm:px-6 py-3 sm:py-4 border-t border-slate-200 dark:border-slate-700">
            <button type="button" id="dayDetailAddShiftBtn" class="btn-primary">
                <i class="bi bi-plus-lg"></i> Xếp thêm ca
            </button>
        </div>
    </div>
</div>

{{-- Form ẩn dùng để xoá 1 ca từ modal chi tiết (destroy dùng chung route shift-schedules.destroy) --}}
<form id="dayDetailDeleteForm" method="POST" action="" class="hidden">
    @csrf
    @method('DELETE')
</form>

{{-- "Đánh dấu đi làm đúng giờ" — chấm công hộ 1 cú nhấp, không qua modal: gửi thẳng giờ
     vào/ra = đúng giờ bắt đầu/kết thúc của ca (s.start_time/s.end_time, đã tính effectiveShift()
     nên phản ánh đúng giờ đã điều chỉnh nếu có nghỉ theo giờ) tới cùng route attendance-logs.store
     như "Chấm công hộ" thường — late_minutes/early_minutes tự ra 0 vì check-in/out đúng khớp
     giờ ca (xem AttendanceLogsController::computeLateMinutesAt()/computeEarlyMinutesAt()). Chỉ
     hiện khi ca đó CHƯA có bản ghi chấm công, cùng điều kiện permission create-attendance-logs
     như nút "Chấm công hộ". --}}
@can('create-attendance-logs')
<form id="dayDetailOnTimeAttendanceForm" method="POST" action="{{ route('attendance-logs.store') }}" class="hidden">
    @csrf
    <input type="hidden" name="employee_id">
    <input type="hidden" name="work_date">
    <input type="hidden" name="shift_schedule_id">
    <input type="hidden" name="check_in_at">
    <input type="hidden" name="check_out_at">
</form>
@endcan

{{-- Sửa giờ chấm công ngay từ modal chi tiết ca — dùng chung route attendance-logs.update
     (AttendanceLogsController::update()), cùng permission edit-attendance-logs như trang
     Báo cáo chấm công. Bỏ trống 1 ô = xoá giờ đó khỏi bản ghi, giống hệt behavior chấm công hộ. --}}
@can('edit-attendance-logs')
<div id="dayDetailEditAttendanceModal" class="hidden fixed inset-0 bg-black/50 z-[60] flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('dayDetailEditAttendanceModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-pencil-square text-amber-500"></i> Sửa giờ chấm công
            </h3>
            <button onclick="closeModal('dayDetailEditAttendanceModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form id="dayDetailEditAttendanceForm" method="POST" action="" class="px-4 sm:px-6 py-4 sm:py-5 space-y-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="_modal" value="dayDetailEditAttendanceModal">
            <input type="hidden" id="dayDetailEditAttendanceIdField" name="_attendance_id">
            <input type="hidden" id="dayDetailEditAttendanceEmployeeField" name="_employee_name">
            <input type="hidden" id="dayDetailEditAttendanceDateField" name="_date_label">
            <p class="text-sm text-slate-500 dark:text-slate-400">
                <span id="dayDetailEditAttendanceEmployee" class="font-medium text-slate-700 dark:text-slate-200"></span>
            </p>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Giờ vào</label>
                    <input type="time" id="dayDetailEditAttendanceCheckIn" name="check_in_at" class="form-input">
                    @error('check_in_at') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Giờ ra</label>
                    <input type="time" id="dayDetailEditAttendanceCheckOut" name="check_out_at" class="form-input">
                    @error('check_out_at') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <p class="text-xs text-slate-400">Để trống 1 ô để xoá giờ đó khỏi bản ghi. Trễ/sớm sẽ được tính lại tự động theo ca đã xếp (nếu có).</p>
            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('dayDetailEditAttendanceModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-primary"><i class="bi bi-floppy"></i> Cập nhật</button>
            </div>
        </form>
    </div>
</div>
@endcan

{{-- Chấm công hộ ngay từ modal chi tiết ca — dùng chung route attendance-logs.store
     (AttendanceLogsController::store()), cùng permission create-attendance-logs (chỉ seed cho
     admin, xem AttendanceLogEditPermissionSeeder). Chỉ hiện khi ca đó CHƯA có bản ghi chấm công. --}}
@can('create-attendance-logs')
<div id="dayDetailCreateAttendanceModal" class="hidden fixed inset-0 bg-black/50 z-[60] flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('dayDetailCreateAttendanceModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-person-check text-emerald-500"></i> Chấm công hộ
            </h3>
            <button onclick="closeModal('dayDetailCreateAttendanceModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form id="dayDetailCreateAttendanceForm" method="POST" action="{{ route('attendance-logs.store') }}" class="px-4 sm:px-6 py-4 sm:py-5 space-y-4">
            @csrf
            <input type="hidden" name="_modal" value="dayDetailCreateAttendanceModal">
            <input type="hidden" id="dayDetailCreateAttendanceEmployeeId" name="employee_id">
            <input type="hidden" id="dayDetailCreateAttendanceWorkDate" name="work_date">
            <input type="hidden" id="dayDetailCreateAttendanceScheduleId" name="shift_schedule_id">
            <input type="hidden" id="dayDetailCreateAttendanceEmployeeField" name="_employee_name">
            <input type="hidden" id="dayDetailCreateAttendanceDateField" name="_date_label">
            <p class="text-sm text-slate-500 dark:text-slate-400">
                <span id="dayDetailCreateAttendanceEmployee" class="font-medium text-slate-700 dark:text-slate-200"></span>
            </p>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Giờ vào</label>
                    <input type="time" id="dayDetailCreateAttendanceCheckIn" name="check_in_at" class="form-input">
                    @error('check_in_at') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="form-label">Giờ ra</label>
                    <input type="time" id="dayDetailCreateAttendanceCheckOut" name="check_out_at" class="form-input">
                    @error('check_out_at') <p class="form-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <p class="text-xs text-slate-400">Nhập ít nhất 1 trong 2 ô giờ vào/ra.</p>
            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('dayDetailCreateAttendanceModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-primary"><i class="bi bi-floppy"></i> Lưu</button>
            </div>
        </form>
    </div>
</div>
@endcan

@once
    @push('scripts')
    <script>
    (function () {
        function escapeHtml(str) {
            if (str === null || str === undefined) return '';
            return String(str)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        function methodBadge(method) {
            const labels = { gps: 'GPS', ip: 'WiFi văn phòng', gps_ip: 'GPS + WiFi', wfh: 'WFH', skip: 'Không xác thực' };
            return labels[method] || '—';
        }

        // Chấm tròn trạng thái chấm công: đen/xám = chưa chấm công, xanh = đúng giờ, đỏ = trễ/về sớm
        function attendanceDotHtml(timestamp, deltaMinutes, lateLabel, onTimeLabel, noneLabel) {
            if (!timestamp) {
                return '<span class="inline-block w-1.5 h-1.5 rounded-full bg-slate-800 dark:bg-slate-400" title="' + escapeHtml(noneLabel) + '"></span>';
            }
            const isLate = deltaMinutes > 0;
            const color  = isLate ? 'bg-red-500' : 'bg-emerald-500';
            const label  = isLate ? lateLabel : onTimeLabel;
            return '<span class="inline-block w-1.5 h-1.5 rounded-full ' + color + '" title="' + escapeHtml(label) + '"></span>';
        }

        function scheduleCardHtml(s, employeeId, employeeName, workDate, ctx) {
            const perms = window.SCHED_PERMS || {};
            const badges = [];
            if (s.is_wfh) badges.push('<span class="badge badge-info">WFH</span>');
            if (s.is_flexible) {
                badges.push('<span class="badge badge-neutral">Linh hoạt</span>');
            } else {
                badges.push(s.assignment_type === 'fixed'
                    ? '<span class="badge badge-neutral">Cố định</span>'
                    : '<span class="badge badge-neutral">Đa ca</span>');
            }

            const canSwap = !s.is_flexible && !ctx.isOwnEmployee && ctx.dayIsFutureOrToday && perms.canSwap && perms.hasUpcoming
                && typeof window.openSwapModal === 'function';

            let actions = '';
            if (perms.canEdit) {
                actions += '<button type="button" class="btn-ghost btn-sm day-detail-edit-btn" title="Sửa ca"><i class="bi bi-pencil"></i></button>';
            }
            if (perms.canDelete) {
                actions += '<button type="button" class="btn-ghost btn-sm text-red-600 dark:text-red-400 day-detail-delete-btn" title="Xoá ca"><i class="bi bi-trash"></i></button>';
            }
            if (canSwap) {
                actions += '<button type="button" class="btn-ghost btn-sm text-violet-600 dark:text-violet-400 day-detail-swap-btn" title="Đề xuất đổi ca"><i class="bi bi-arrow-left-right"></i></button>';
            }

            return '' +
            '<div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-800/60 p-4" style="min-height:150px;box-sizing:border-box" data-schedule-id="' + s.id + '">' +
                    '<div class="flex items-start justify-between gap-2">' +
                        '<div class="min-w-0">' +
                            '<p class="text-sm font-semibold text-slate-900 dark:text-slate-100 truncate" style="font-family: \'Bricolage Grotesque\', \'Be Vietnam Pro\', sans-serif !important; letter-spacing:-0.01em">' + escapeHtml(s.shift_name) + '</p>' +
                            '<p class="text-sm font-medium text-slate-600 dark:text-slate-300 tabular-nums">' + escapeHtml(s.start_time) + ' – ' + escapeHtml(s.end_time) + '</p>' +
                        '</div>' +
                        (actions ? '<div class="flex items-center gap-1 flex-shrink-0">' + actions + '</div>' : '') +
                    '</div>' +
                    '<div class="flex flex-wrap gap-1 mt-2">' + badges.join('') + '</div>' +
                    (s.note ? '<p class="text-xs text-slate-500 dark:text-slate-400 mt-2">' + escapeHtml(s.note) + '</p>' : '') +
                    '<p class="text-xs text-slate-500 dark:text-slate-400 mt-3 pt-2 border-t border-slate-200/80 dark:border-slate-700">' +
                        (s.assigned_by ? 'Người xếp: ' + escapeHtml(s.assigned_by) : 'Tự động xếp') +
                        (s.created_at ? ' — ' + escapeHtml(s.created_at) : '') +
                    '</p>' +
                '</div>';
        }

        function attendanceCardHtml(s, employeeId, workDate) {
            const a = s.attendance;
            const perms = window.SCHED_PERMS || {};
            const timeRange = '<p class="text-sm text-slate-600 dark:text-slate-300 tabular-nums">' + escapeHtml(s.start_time) + ' – ' + escapeHtml(s.end_time) + '</p>';

            if (!a) {
                const createBtn = perms.canCreateAttendance
                    ? '<button type="button" class="btn-secondary btn-sm attendance-create-btn mt-2 h-9 min-h-9 inline-flex items-center justify-center font-semibold" title="Chấm công" ' +
                        'data-schedule-id="' + s.id + '" data-employee-id="' + employeeId + '" data-work-date="' + escapeHtml(workDate) + '">' +
                        '<i class="bi bi-person-check"></i> Chấm công</button>'
                    : '';
                const onTimeBtn = (perms.canCreateAttendance && s.start_time && s.end_time)
                    ? '<button type="button" class="btn-primary btn-sm attendance-ontime-btn mt-2 h-9 min-h-9 inline-flex items-center justify-center font-semibold" title="Đánh dấu đi làm đúng giờ — giờ vào/ra sẽ lấy đúng giờ ca" ' +
                        'data-schedule-id="' + s.id + '" data-employee-id="' + employeeId + '" data-work-date="' + escapeHtml(workDate) + '" ' +
                        'data-start-time="' + escapeHtml(s.start_time) + '" data-end-time="' + escapeHtml(s.end_time) + '">' +
                        '<i class="bi bi-check2-circle"></i> Đánh dấu đúng giờ</button>'
                    : '';
                return '' +
                    '<div class="rounded-xl border border-dashed border-slate-300 dark:border-slate-600 bg-slate-50/50 dark:bg-slate-800/40 p-4" style="min-height:150px;box-sizing:border-box">' +
                        '<div class="flex items-center gap-1.5">' +
                            attendanceDotHtml(null, 0, '', '', 'Chưa chấm công') +
                            '<p class="text-sm font-semibold text-slate-900 dark:text-slate-100 truncate" style="font-family: \'Bricolage Grotesque\', \'Be Vietnam Pro\', sans-serif !important; letter-spacing:-0.01em">' + escapeHtml(s.shift_name) + '</p>' +
                        '</div>' +
                        timeRange +
                        '<p class="text-sm font-medium text-amber-700 dark:text-amber-300 mt-2">Chưa ghi nhận chấm công</p>' +
                        '<div class="flex flex-wrap items-center gap-1">' + createBtn + onTimeBtn + '</div>' +
                    '</div>';
            }

            const late  = a.late_minutes > 0 ? '<span class="text-amber-700 dark:text-amber-300 text-xs">(trễ ' + a.late_minutes + ' phút)</span>' : '';
            const early = a.early_minutes > 0 ? '<span class="text-amber-700 dark:text-amber-300 text-xs">(sớm ' + a.early_minutes + ' phút)</span>' : '';
            const deviceWarning = a.device_changed
                ? '<p class="text-amber-700 dark:text-amber-300 text-xs flex items-center gap-1 mt-2" title="Thiết bị lúc tan ca khác với lúc vào ca">' +
                    '<i class="bi bi-exclamation-triangle-fill"></i> Khác thiết bị chấm công</p>'
                : '';
            const overtimeNote = a.overtime_hours > 0
                ? '<p class="text-violet-700 dark:text-violet-300 text-xs flex items-center gap-1 mt-2">' +
                    '<i class="bi bi-clock-history"></i> Tăng ca đã duyệt: +' + a.overtime_hours + ' giờ</p>'
                : '';
            const editBtn = perms.canEditAttendance
                ? '<button type="button" class="btn-ghost btn-sm attendance-edit-btn" title="Sửa giờ chấm công" ' +
                    'data-attendance-id="' + a.id + '" data-check-in="' + escapeHtml(a.check_in_at || '') + '" data-check-out="' + escapeHtml(a.check_out_at || '') + '">' +
                    '<i class="bi bi-pencil"></i></button>'
                : '';

            return '' +
                '<div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-800/60 p-4" style="min-height:150px;box-sizing:border-box">' +
                    '<div class="flex items-start justify-between gap-2">' +
                        '<p class="text-sm font-semibold text-slate-900 dark:text-slate-100 truncate" style="font-family: \'Bricolage Grotesque\', \'Be Vietnam Pro\', sans-serif !important; letter-spacing:-0.01em">' + escapeHtml(s.shift_name) + '</p>' +
                        (editBtn ? '<div class="flex-shrink-0">' + editBtn + '</div>' : '') +
                    '</div>' +
                    timeRange +
                    '<div class="grid grid-cols-2 gap-2 mt-2 text-sm">' +
                        '<div>' +
                            '<p class="text-xs font-medium text-slate-500 dark:text-slate-400 flex items-center gap-1.5">' +
                                attendanceDotHtml(a.check_in_at, a.late_minutes,
                                    'Vào ca trễ ' + a.late_minutes + ' phút', 'Vào ca đúng giờ', 'Chưa vào ca') +
                                ' Vào ca' +
                            '</p>' +
                            '<p class="text-sm font-semibold text-slate-900 dark:text-white tabular-nums">' + (a.check_in_at ? escapeHtml(a.check_in_at) + ' ' + late : '<span class="text-slate-400 font-normal">Chưa có</span>') + '</p>' +
                            (a.check_in_method ? '<p class="text-xs text-slate-400">' + escapeHtml(methodBadge(a.check_in_method)) + '</p>' : '') +
                        '</div>' +
                        '<div>' +
                            '<p class="text-xs font-medium text-slate-500 dark:text-slate-400 flex items-center gap-1.5">' +
                                attendanceDotHtml(a.check_out_at, a.early_minutes,
                                    'Tan ca sớm ' + a.early_minutes + ' phút', 'Tan ca đúng giờ', 'Chưa tan ca') +
                                ' Tan ca' +
                            '</p>' +
                            '<p class="text-sm font-semibold text-slate-900 dark:text-white tabular-nums">' + (a.check_out_at ? escapeHtml(a.check_out_at) + ' ' + early : '<span class="text-slate-400 font-normal">Chưa có</span>') + '</p>' +
                            (a.check_out_method ? '<p class="text-xs text-slate-400">' + escapeHtml(methodBadge(a.check_out_method)) + '</p>' : '') +
                        '</div>' +
                    '</div>' +
                    deviceWarning +
                    overtimeNote +
                '</div>';
        }

        window.openDayDetailModal = function (employeeId, employeeName, workDate, dateLabel, schedules, ctx) {
            ctx = ctx || {};
            document.getElementById('dayDetailTitle').textContent = employeeName + ' — ' + dateLabel;

            const scheduleList   = document.getElementById('dayDetailScheduleList');
            const attendanceList = document.getElementById('dayDetailAttendanceList');

            if (!schedules.length) {
                scheduleList.innerHTML = '<p class="text-sm text-slate-400">Chưa có ca nào được xếp.</p>';
                attendanceList.innerHTML = '<p class="text-sm text-slate-400">Chưa có dữ liệu chấm công.</p>';
            } else {
                scheduleList.innerHTML = schedules.map(s => scheduleCardHtml(s, employeeId, employeeName, workDate, ctx)).join('');
                attendanceList.innerHTML = schedules.map(s => attendanceCardHtml(s, employeeId, workDate)).join('');
            }

            schedules.forEach(function (s) {
                const card = scheduleList.querySelector('[data-schedule-id="' + s.id + '"]');
                if (!card) return;

                const editBtn = card.querySelector('.day-detail-edit-btn');
                if (editBtn) {
                    editBtn.addEventListener('click', function () {
                        closeModal('dayDetailModal');
                        window.openAssignModal(employeeId, employeeName, workDate, s.id, s.shift_id, s.note,
                            s.is_flexible, s.custom_start_time, s.custom_end_time, s.custom_break_minutes,
                            s.custom_is_overnight, s.custom_is_wfh);
                    });
                }

                const deleteBtn = card.querySelector('.day-detail-delete-btn');
                if (deleteBtn) {
                    deleteBtn.addEventListener('click', function () {
                        const msg = 'Xoá ca "' + s.shift_name + '" của ' + employeeName + ' ngày ' + dateLabel + '?';
                        if (!confirm(msg)) return;
                        const form = document.getElementById('dayDetailDeleteForm');
                        form.action = '/shift-schedules/' + s.id;
                        form.submit();
                    });
                }

                const swapBtn = card.querySelector('.day-detail-swap-btn');
                if (swapBtn) {
                    swapBtn.addEventListener('click', function () {
                        closeModal('dayDetailModal');
                        window.openSwapModal(s.id, employeeName, dateLabel, s.shift_name);
                    });
                }
            });

            attendanceList.querySelectorAll('.attendance-edit-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    closeModal('dayDetailModal');
                    window.openDayDetailEditAttendance(btn.dataset.attendanceId, employeeName, dateLabel,
                        btn.dataset.checkIn, btn.dataset.checkOut);
                });
            });

            attendanceList.querySelectorAll('.attendance-create-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    closeModal('dayDetailModal');
                    window.openDayDetailCreateAttendance(btn.dataset.employeeId, employeeName,
                        btn.dataset.workDate, dateLabel, btn.dataset.scheduleId);
                });
            });

            attendanceList.querySelectorAll('.attendance-ontime-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    window.submitOnTimeAttendance(btn.dataset.employeeId, btn.dataset.workDate,
                        btn.dataset.scheduleId, btn.dataset.startTime, btn.dataset.endTime);
                });
            });

            const footer = document.getElementById('dayDetailFooter');
            const perms = window.SCHED_PERMS || {};
            if (perms.canCreate) {
                footer.classList.remove('hidden');
                footer.classList.add('flex');
                document.getElementById('dayDetailAddShiftBtn').onclick = function () {
                    closeModal('dayDetailModal');
                    window.openAssignModal(employeeId, employeeName, workDate, null, null, null, false, null, null, null, false, false);
                };
            } else {
                footer.classList.add('hidden');
                footer.classList.remove('flex');
            }

            openModal('dayDetailModal');
        };

        window.openDayDetailEditAttendance = function (attendanceId, employeeName, dateLabel, checkInAt, checkOutAt) {
            document.getElementById('dayDetailEditAttendanceForm').action = '/attendance-logs/' + attendanceId;
            document.getElementById('dayDetailEditAttendanceEmployee').textContent = employeeName + ' — ' + dateLabel;
            document.getElementById('dayDetailEditAttendanceIdField').value = attendanceId ?? '';
            document.getElementById('dayDetailEditAttendanceEmployeeField').value = employeeName ?? '';
            document.getElementById('dayDetailEditAttendanceDateField').value = dateLabel ?? '';
            document.getElementById('dayDetailEditAttendanceCheckIn').value = checkInAt ? checkInAt.substring(0, 5) : '';
            document.getElementById('dayDetailEditAttendanceCheckOut').value = checkOutAt ? checkOutAt.substring(0, 5) : '';
            openModal('dayDetailEditAttendanceModal');
        };

        @if($errors->any() && old('_modal') === 'dayDetailEditAttendanceModal')
        document.addEventListener('DOMContentLoaded', function () {
            window.openDayDetailEditAttendance(
                '{{ old('_attendance_id') }}',
                {{ Illuminate\Support\Js::from(old('_employee_name')) }},
                {{ Illuminate\Support\Js::from(old('_date_label')) }},
                '{{ old('check_in_at') }}',
                '{{ old('check_out_at') }}'
            );
        });
        @endif

        // Gửi thẳng "Chấm công hộ" với giờ vào/ra = đúng giờ ca, không qua modal xác nhận —
        // dùng cho nút "Đúng giờ" trên từng ca chưa chấm công.
        window.submitOnTimeAttendance = function (employeeId, workDate, scheduleId, startTime, endTime) {
            const form = document.getElementById('dayDetailOnTimeAttendanceForm');
            if (!form) return;
            form.querySelector('[name="employee_id"]').value = employeeId ?? '';
            form.querySelector('[name="work_date"]').value = workDate ?? '';
            form.querySelector('[name="shift_schedule_id"]').value = scheduleId ?? '';
            form.querySelector('[name="check_in_at"]').value = startTime ?? '';
            form.querySelector('[name="check_out_at"]').value = endTime ?? '';
            form.submit();
        };

        window.openDayDetailCreateAttendance = function (employeeId, employeeName, workDate, dateLabel, scheduleId) {
            document.getElementById('dayDetailCreateAttendanceEmployeeId').value = employeeId ?? '';
            document.getElementById('dayDetailCreateAttendanceWorkDate').value = workDate ?? '';
            document.getElementById('dayDetailCreateAttendanceScheduleId').value = scheduleId ?? '';
            document.getElementById('dayDetailCreateAttendanceEmployeeField').value = employeeName ?? '';
            document.getElementById('dayDetailCreateAttendanceDateField').value = dateLabel ?? '';
            document.getElementById('dayDetailCreateAttendanceEmployee').textContent = employeeName + ' — ' + dateLabel;
            document.getElementById('dayDetailCreateAttendanceCheckIn').value = '';
            document.getElementById('dayDetailCreateAttendanceCheckOut').value = '';
            openModal('dayDetailCreateAttendanceModal');
        };

        @if($errors->any() && old('_modal') === 'dayDetailCreateAttendanceModal')
        document.addEventListener('DOMContentLoaded', function () {
            window.openDayDetailCreateAttendance(
                '{{ old('employee_id') }}',
                {{ Illuminate\Support\Js::from(old('_employee_name')) }},
                '{{ old('work_date') }}',
                {{ Illuminate\Support\Js::from(old('_date_label')) }},
                '{{ old('shift_schedule_id') }}'
            );
            document.getElementById('dayDetailCreateAttendanceCheckIn').value = '{{ old('check_in_at') }}';
            document.getElementById('dayDetailCreateAttendanceCheckOut').value = '{{ old('check_out_at') }}';
        });
        @endif
    })();
    </script>
    @endpush
@endonce
