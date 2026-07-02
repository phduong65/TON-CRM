<div id="dayDetailModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
     onclick="if(event.target===this)closeModal('dayDetailModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2 min-w-0">
                <i class="bi bi-calendar-week text-pcrm-600 flex-shrink-0"></i>
                <span id="dayDetailTitle" class="truncate"></span>
            </h3>
            <button onclick="closeModal('dayDetailModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 flex-shrink-0">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>

        <div class="px-4 sm:px-6 py-4 sm:py-5 grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-3">
                    Thông tin ca hôm đó
                </p>
                <div id="dayDetailScheduleList" class="space-y-3"></div>
            </div>
            <div class="min-w-0">
                <p class="text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-3">
                    Lịch sử chấm công
                </p>
                <div id="dayDetailAttendanceList" class="space-y-3"></div>
            </div>
        </div>

        <div id="dayDetailFooter" class="hidden flex items-center justify-end gap-3 px-4 sm:px-6 py-3 sm:py-4 border-t border-slate-200 dark:border-slate-700">
            <button type="button" id="dayDetailAddShiftBtn" class="btn-primary">
                <i class="bi bi-plus-lg"></i> Thêm ca
            </button>
        </div>
    </div>
</div>

{{-- Form ẩn dùng để xoá 1 ca từ modal chi tiết (destroy dùng chung route shift-schedules.destroy) --}}
<form id="dayDetailDeleteForm" method="POST" action="" class="hidden">
    @csrf
    @method('DELETE')
</form>

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
            const labels = { gps: 'GPS', ip: 'WiFi văn phòng', gps_ip: 'GPS + WiFi', wfh: 'WFH' };
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
            badges.push(s.assignment_type === 'fixed'
                ? '<span class="badge badge-neutral">Cố định</span>'
                : '<span class="badge badge-neutral">Đa ca</span>');

            const canSwap = !ctx.isOwnEmployee && ctx.dayIsFutureOrToday && perms.canSwap && perms.hasUpcoming
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
                '<div class="rounded-xl border border-slate-200 dark:border-slate-700 p-3" data-schedule-id="' + s.id + '">' +
                    '<div class="flex items-start justify-between gap-2">' +
                        '<div class="min-w-0">' +
                            '<p class="font-medium text-slate-800 dark:text-slate-100 truncate">' + escapeHtml(s.shift_name) + '</p>' +
                            '<p class="text-xs text-slate-400">' + escapeHtml(s.start_time) + '–' + escapeHtml(s.end_time) + '</p>' +
                        '</div>' +
                        (actions ? '<div class="flex items-center gap-1 flex-shrink-0">' + actions + '</div>' : '') +
                    '</div>' +
                    '<div class="flex flex-wrap gap-1 mt-2">' + badges.join('') + '</div>' +
                    (s.note ? '<p class="text-xs text-slate-500 dark:text-slate-400 mt-2">' + escapeHtml(s.note) + '</p>' : '') +
                    '<p class="text-xs text-slate-400 mt-2">' +
                        (s.assigned_by ? 'Xếp bởi: ' + escapeHtml(s.assigned_by) : 'Xếp tự động') +
                        (s.created_at ? ' — ' + escapeHtml(s.created_at) : '') +
                    '</p>' +
                '</div>';
        }

        function attendanceCardHtml(s) {
            const a = s.attendance;
            const timeRange = '<p class="text-xs text-slate-400">' + escapeHtml(s.start_time) + '–' + escapeHtml(s.end_time) + '</p>';

            if (!a) {
                return '' +
                    '<div class="rounded-xl border border-dashed border-slate-200 dark:border-slate-700 p-3">' +
                        '<div class="flex items-center gap-1.5">' +
                            attendanceDotHtml(null, 0, '', '', 'Chưa chấm công') +
                            '<p class="font-medium text-slate-800 dark:text-slate-100 truncate">' + escapeHtml(s.shift_name) + '</p>' +
                        '</div>' +
                        timeRange +
                        '<p class="text-sm text-slate-400 mt-1">Chưa chấm công</p>' +
                    '</div>';
            }

            const late  = a.late_minutes > 0 ? '<span class="text-amber-600 dark:text-amber-400 text-xs">(trễ ' + a.late_minutes + 'p)</span>' : '';
            const early = a.early_minutes > 0 ? '<span class="text-amber-600 dark:text-amber-400 text-xs">(sớm ' + a.early_minutes + 'p)</span>' : '';

            return '' +
                '<div class="rounded-xl border border-slate-200 dark:border-slate-700 p-3">' +
                    '<p class="font-medium text-slate-800 dark:text-slate-100 truncate">' + escapeHtml(s.shift_name) + '</p>' +
                    timeRange +
                    '<div class="grid grid-cols-2 gap-2 mt-2 text-sm">' +
                        '<div>' +
                            '<p class="text-xs text-slate-400 flex items-center gap-1.5">' +
                                attendanceDotHtml(a.check_in_at, a.late_minutes,
                                    'Check-in trễ ' + a.late_minutes + ' phút', 'Check-in đúng giờ', 'Chưa check-in') +
                                ' Check-in' +
                            '</p>' +
                            '<p class="font-medium">' + (a.check_in_at ? escapeHtml(a.check_in_at) + ' ' + late : '<span class="text-slate-400 font-normal">—</span>') + '</p>' +
                            (a.check_in_method ? '<p class="text-xs text-slate-400">' + escapeHtml(methodBadge(a.check_in_method)) + '</p>' : '') +
                        '</div>' +
                        '<div>' +
                            '<p class="text-xs text-slate-400 flex items-center gap-1.5">' +
                                attendanceDotHtml(a.check_out_at, a.early_minutes,
                                    'Check-out sớm ' + a.early_minutes + ' phút', 'Check-out đúng giờ', 'Chưa check-out') +
                                ' Check-out' +
                            '</p>' +
                            '<p class="font-medium">' + (a.check_out_at ? escapeHtml(a.check_out_at) + ' ' + early : '<span class="text-slate-400 font-normal">—</span>') + '</p>' +
                            (a.check_out_method ? '<p class="text-xs text-slate-400">' + escapeHtml(methodBadge(a.check_out_method)) + '</p>' : '') +
                        '</div>' +
                    '</div>' +
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
                attendanceList.innerHTML = schedules.map(s => attendanceCardHtml(s)).join('');
            }

            schedules.forEach(function (s) {
                const card = scheduleList.querySelector('[data-schedule-id="' + s.id + '"]');
                if (!card) return;

                const editBtn = card.querySelector('.day-detail-edit-btn');
                if (editBtn) {
                    editBtn.addEventListener('click', function () {
                        closeModal('dayDetailModal');
                        window.openAssignModal(employeeId, employeeName, workDate, s.id, s.shift_id, s.note);
                    });
                }

                const deleteBtn = card.querySelector('.day-detail-delete-btn');
                if (deleteBtn) {
                    deleteBtn.addEventListener('click', function () {
                        const msg = s.batch_id
                            ? 'Ca "' + s.shift_name + '" thuộc một đợt xếp ca cố định (hàng loạt). ' +
                              'Xoá sẽ huỷ TOÀN BỘ đợt này — mọi nhân viên, mọi ngày, kể cả các ngày lặp lại ' +
                              'trong tương lai. Bạn có chắc chắn?'
                            : 'Xoá ca "' + s.shift_name + '" của ' + employeeName + '?';
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

            const footer = document.getElementById('dayDetailFooter');
            const perms = window.SCHED_PERMS || {};
            if (perms.canCreate) {
                footer.classList.remove('hidden');
                footer.classList.add('flex');
                document.getElementById('dayDetailAddShiftBtn').onclick = function () {
                    closeModal('dayDetailModal');
                    window.openAssignModal(employeeId, employeeName, workDate, null, null, null);
                };
            } else {
                footer.classList.add('hidden');
                footer.classList.remove('flex');
            }

            openModal('dayDetailModal');
        };
    })();
    </script>
    @endpush
@endonce
