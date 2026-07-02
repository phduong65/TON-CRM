@extends('layouts.admin')

@section('title', 'Lịch làm việc')
@section('page-title', 'Lịch làm việc')
@section('breadcrumb', 'Ca làm việc & Chấm công')

@push('styles')
<style>
    /* Tinh chỉnh FullCalendar cho hợp giao diện Tailwind + dark mode của hệ thống.
       Dùng CSS custom properties thật (đã khai báo trong resources/css/app.css) — KHÔNG dùng
       hàm theme() vì đây là <style> thuần render ra browser, không qua PostCSS/Tailwind build. */
    #workCalendar { --fc-border-color: var(--color-slate-200); --fc-page-bg-color: transparent; }
    .dark #workCalendar { --fc-border-color: var(--color-slate-700); }
    #workCalendar .fc-toolbar-title { font-size: 1.125rem; font-weight: 700; color: var(--color-slate-900); }
    .dark #workCalendar .fc-toolbar-title { color: var(--color-white); }
    #workCalendar .fc-button {
        background: var(--color-white); border: 1px solid var(--color-slate-200); color: var(--color-slate-600);
        box-shadow: none !important; text-transform: none; font-weight: 500; padding: 0.4rem 0.8rem;
    }
    .dark #workCalendar .fc-button { background: var(--color-slate-800); border-color: var(--color-slate-700); color: var(--color-slate-300); }
    #workCalendar .fc-button:hover { background: var(--color-slate-50); }
    .dark #workCalendar .fc-button:hover { background: var(--color-slate-700); }
    #workCalendar .fc-button-active { background: var(--color-pcrm-600) !important; border-color: var(--color-pcrm-600) !important; color: var(--color-white) !important; }
    #workCalendar .fc-col-header-cell { background: var(--color-slate-50); }
    .dark #workCalendar .fc-col-header-cell { background: rgba(255,255,255,0.03); }
    #workCalendar .fc-col-header-cell-cushion,
    #workCalendar .fc-daygrid-day-number { color: var(--color-slate-500); font-size: 0.75rem; text-decoration: none; }
    .dark #workCalendar .fc-col-header-cell-cushion,
    .dark #workCalendar .fc-daygrid-day-number { color: var(--color-slate-400); }
    #workCalendar .fc-day-today { background: var(--color-pcrm-50) !important; }
    .dark #workCalendar .fc-day-today { background: rgba(99,102,241,0.08) !important; }
    #workCalendar .fc-event { border-radius: 0.375rem; padding: 1px 4px; font-size: 0.72rem; font-weight: 500; border-left-width: 3px !important; cursor: default; }
    #workCalendar .fc-daygrid-day-frame { min-height: 116px; }

    /* Nội dung event tuỳ biến (eventContent) — hiển thị trạng thái chấm công ngay trong ô lịch */
    .wc-event-chip { line-height: 1.15; }
    .wc-event-title { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .wc-event-status { font-size: 0.66rem; font-weight: 500; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; opacity: 0.95; }
    .wc-status-ok { color: #15803d; }
    .dark .wc-status-ok { color: #4ade80; }
    .wc-status-warn { color: #b45309; }
    .dark .wc-status-warn { color: #fbbf24; }
    .wc-status-progress { color: #0369a1; }
    .dark .wc-status-progress { color: #38bdf8; }
    .wc-status-missed { color: #be123c; }
    .dark .wc-status-missed { color: #fb7185; }
</style>
@endpush

@section('content')
    <div class="page-header">
        <div>
            <p class="page-subtitle">Lịch ca làm việc của {{ $employee->name }}</p>
        </div>
        @can('export-own-schedule')
        <button onclick="openModal('exportMyScheduleModal')" class="btn-secondary">
            <i class="bi bi-file-earmark-excel"></i>
            <span>Xuất Excel</span>
        </button>
        @endcan
    </div>

    <div class="card mx-auto overflow-hidden">
        <div class="px-4 sm:px-5 py-2.5 border-b border-slate-100 dark:border-slate-700 flex flex-wrap items-center gap-4 text-xs text-slate-500 dark:text-slate-400 bg-slate-50/50 dark:bg-slate-800/40">
            <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-sky-400"></span> Ca làm việc</span>
            <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-slate-400"></span> Nghỉ phép</span>
            <span class="inline-flex items-center gap-1.5">🏠 WFH</span>
            <span class="w-px h-3.5 bg-slate-200 dark:bg-slate-700"></span>
            <span class="inline-flex items-center gap-1 wc-status-ok">✓ Đã chấm công đủ</span>
            <span class="inline-flex items-center gap-1 wc-status-warn">⏰ Trễ/sớm</span>
            <span class="inline-flex items-center gap-1 wc-status-progress">🟡 Đang trong ca</span>
            <span class="inline-flex items-center gap-1 wc-status-missed">⚠ Chưa chấm công</span>
        </div>

        <div class="p-3 sm:p-5">
            <div id="workCalendar"></div>
        </div>
    </div>
@endsection

@push('modals')
    <x-export-range-modal id="exportMyScheduleModal" title="Xuất Excel — Lịch làm việc"
        :export-url="route('my-schedule.export')" />
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6/locales-all.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var calendarEl = document.getElementById('workCalendar');

    var calendar = new FullCalendar.Calendar(calendarEl, {
        locale: 'vi',
        timeZone: 'local',
        initialView: 'dayGridMonth',
        height: 'auto',
        firstDay: 1, // Tuần bắt đầu từ Thứ 2
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: '',
        },
        buttonText: { today: 'Hôm nay' },
        events: '{{ route('my-schedule.events') }}',
        eventContent: function (arg) {
            var props = arg.event.extendedProps;

            var wrapper = document.createElement('div');
            wrapper.className = 'wc-event-chip';

            var titleEl = document.createElement('div');
            titleEl.className = 'wc-event-title';
            titleEl.textContent = arg.event.title;
            wrapper.appendChild(titleEl);

            if (props.type === 'shift') {
                var statusEl = document.createElement('div');
                statusEl.className = 'wc-event-status';

                if (props.attendanceStatus === 'completed') {
                    var okText = '✓ ' + props.checkInAt + '–' + props.checkOutAt;
                    var isLate = props.lateMinutes > 0 || props.earlyMinutes > 0;
                    if (props.lateMinutes > 0) okText += ' · Trễ ' + props.lateMinutes + 'p';
                    if (props.earlyMinutes > 0) okText += ' · Sớm ' + props.earlyMinutes + 'p';
                    statusEl.textContent = okText;
                    statusEl.classList.add(isLate ? 'wc-status-warn' : 'wc-status-ok');
                    wrapper.appendChild(statusEl);
                } else if (props.attendanceStatus === 'in_progress') {
                    var inText = '🟡 Vào ca ' + props.checkInAt;
                    if (props.lateMinutes > 0) inText += ' · Trễ ' + props.lateMinutes + 'p';
                    statusEl.textContent = inText;
                    statusEl.classList.add(props.lateMinutes > 0 ? 'wc-status-warn' : 'wc-status-progress');
                    wrapper.appendChild(statusEl);
                } else if (props.attendanceStatus === 'missed') {
                    statusEl.textContent = '⚠ Chưa chấm công';
                    statusEl.classList.add('wc-status-missed');
                    wrapper.appendChild(statusEl);
                }
            }

            return { domNodes: [wrapper] };
        },
        eventDidMount: function (info) {
            var props = info.event.extendedProps;
            var lines = [info.event.title];

            if (props.type === 'leave' && props.reason) {
                lines.push(props.reason);
            }
            if (props.type === 'shift') {
                if (props.checkInAt) {
                    lines.push('Check-in: ' + props.checkInAt + (props.lateMinutes > 0 ? ' (trễ ' + props.lateMinutes + ' phút)' : ''));
                }
                if (props.checkOutAt) {
                    lines.push('Check-out: ' + props.checkOutAt + (props.earlyMinutes > 0 ? ' (sớm ' + props.earlyMinutes + ' phút)' : ''));
                }
                if (props.attendanceStatus === 'missed') {
                    lines.push('Chưa có dữ liệu chấm công cho ngày này');
                }
            }

            info.el.setAttribute('title', lines.join('\n'));
        },
    });

    calendar.render();
});
</script>
@endpush
