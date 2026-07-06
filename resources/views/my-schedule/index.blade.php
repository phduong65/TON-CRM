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

    #workCalendar .fc-toolbar { margin-bottom: 1.125rem !important; }
    #workCalendar .fc-toolbar-title { font-size: 1.25rem; font-weight: 700; color: var(--color-slate-900); letter-spacing: -0.01em; }
    .dark #workCalendar .fc-toolbar-title { color: var(--color-white); }
    #workCalendar .fc-button {
        background: var(--color-white); border: 1px solid var(--color-slate-200); color: var(--color-slate-600);
        box-shadow: none !important; text-transform: none; font-weight: 500; padding: 0.45rem 0.9rem;
        border-radius: 0.625rem !important; transition: background-color .15s ease, border-color .15s ease, color .15s ease;
    }
    .dark #workCalendar .fc-button { background: var(--color-slate-800); border-color: var(--color-slate-700); color: var(--color-slate-300); }
    #workCalendar .fc-button:hover { background: var(--color-slate-50); border-color: var(--color-slate-300); }
    .dark #workCalendar .fc-button:hover { background: var(--color-slate-700); border-color: var(--color-slate-600); }
    #workCalendar .fc-button:focus { box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-pcrm-500) 20%, transparent) !important; }
    #workCalendar .fc-button-active { background: var(--color-pcrm-600) !important; border-color: var(--color-pcrm-600) !important; color: var(--color-white) !important; }
    #workCalendar .fc-today-button:disabled { opacity: 0.5; }
    #workCalendar .fc-button-group { gap: 0.375rem; }
    #workCalendar .fc-button-group .fc-button { border-radius: 0.625rem !important; margin: 0 !important; }

    #workCalendar .fc-scrollgrid { border-radius: 0.75rem; overflow: hidden; border-color: var(--fc-border-color) !important; }
    #workCalendar .fc-col-header-cell { background: var(--color-slate-50); padding: 0.5rem 0; }
    .dark #workCalendar .fc-col-header-cell { background: rgba(255,255,255,0.03); }
    #workCalendar .fc-col-header-cell.fc-day-sat,
    #workCalendar .fc-col-header-cell.fc-day-sun { background: color-mix(in srgb, var(--color-pcrm-50) 55%, var(--color-slate-50)); }
    .dark #workCalendar .fc-col-header-cell.fc-day-sat,
    .dark #workCalendar .fc-col-header-cell.fc-day-sun { background: rgba(99,102,241,0.05); }
    #workCalendar .fc-col-header-cell-cushion,
    #workCalendar .fc-daygrid-day-number { color: var(--color-slate-500); font-size: 0.75rem; text-decoration: none; }
    .dark #workCalendar .fc-col-header-cell-cushion,
    .dark #workCalendar .fc-daygrid-day-number { color: var(--color-slate-400); }
    #workCalendar .fc-daygrid-day-number { padding: 0.375rem 0.5rem; font-weight: 600; }
    #workCalendar .fc-day-sat .fc-daygrid-day-frame,
    #workCalendar .fc-day-sun .fc-daygrid-day-frame { background: color-mix(in srgb, var(--color-slate-50) 60%, transparent); }
    .dark #workCalendar .fc-day-sat .fc-daygrid-day-frame,
    .dark #workCalendar .fc-day-sun .fc-daygrid-day-frame { background: rgba(255,255,255,0.015); }
    #workCalendar .fc-day-today { background: var(--color-pcrm-50) !important; }
    .dark #workCalendar .fc-day-today { background: rgba(99,102,241,0.08) !important; }
    #workCalendar .fc-day-today .fc-daygrid-day-number {
        background: var(--color-pcrm-600); color: var(--color-white); border-radius: 9999px;
        width: 1.5rem; height: 1.5rem; display: inline-flex; align-items: center; justify-content: center;
        padding: 0; margin: 0.25rem 0.375rem 0 0; font-size: 0.7rem;
    }
    #workCalendar .fc-daygrid-day-frame { min-height: 118px; padding: 2px; }
    #workCalendar .fc-daygrid-day-events { margin-top: 3px; }
    #workCalendar .fc-daygrid-event-harness + .fc-daygrid-event-harness { margin-top: 4px; }

    /* Event chips — .fc-event áp dụng cho mọi loại (shift block-event, leave h-event nhiều ngày).
       Màu border/nền/chữ do JS gán inline theo từng sự kiện (palette theo ca) — ở đây chỉ chỉnh
       hình khối/khoảng cách/hiệu ứng, không đụng tới màu để giữ đúng accent màu của từng loại ca. */
    #workCalendar .fc-daygrid-event {
        border-left-width: 3px !important;
        border-radius: 0.6rem;
        padding: 4px 8px;
        margin: 0 3px;
        font-size: 0.72rem;
        font-weight: 500;
        letter-spacing: -0.005em;
        cursor: default;
        box-shadow: 0 1px 2px rgb(15 23 42 / 0.06), inset 0 1px 0 rgb(255 255 255 / 0.35);
        transition: transform .15s ease, box-shadow .15s ease, opacity .15s ease;
    }
    .dark #workCalendar .fc-daygrid-event { box-shadow: 0 1px 2px rgb(0 0 0 / 0.25); }
    #workCalendar .fc-daygrid-event:hover {
        transform: translateY(-1.5px) scale(1.012);
        box-shadow: 0 6px 14px -3px rgb(15 23 42 / 0.16), inset 0 1px 0 rgb(255 255 255 / 0.35);
        z-index: 5;
    }
    .dark #workCalendar .fc-daygrid-event:hover { box-shadow: 0 6px 14px -3px rgb(0 0 0 / 0.45); }
    /* Sự kiện đã qua ngày — làm mờ nhẹ để tháng nhìn "nổi" đúng vào hôm nay & tương lai */
    #workCalendar .fc-event-past { opacity: 0.72; }
    #workCalendar .fc-event-past:hover { opacity: 1; }
    #workCalendar .fc-event-today {
        box-shadow: 0 0 0 1px color-mix(in srgb, var(--color-pcrm-500) 40%, transparent), 0 1px 2px rgb(15 23 42 / 0.06), inset 0 1px 0 rgb(255 255 255 / 0.35);
    }

    /* Sự kiện nhiều ngày (nghỉ phép, fc-h-event) — chỉ bo góc ở điểm bắt đầu/kết thúc thực sự,
       giữ đúng hành vi mặc định của FullCalendar cho một dải liền mạch. */
    #workCalendar .fc-h-event:not(.fc-event-start) { margin-left: 0; border-top-left-radius: 0; border-bottom-left-radius: 0; }
    #workCalendar .fc-h-event:not(.fc-event-end) { margin-right: 0; border-top-right-radius: 0; border-bottom-right-radius: 0; }

    #workCalendar .fc-daygrid-block-event .fc-event-time { font-weight: 600; }

    /* Nội dung event tuỳ biến (eventContent) — hiển thị trạng thái chấm công ngay trong ô lịch */
    .wc-event-chip { line-height: 1.2; }
    .wc-event-title { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .wc-event-status { font-size: 0.66rem; font-weight: 500; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; opacity: 0.95; }
    .wc-status-ok { color: #15803d; }
    .dark .wc-status-ok { color: #4ade80; }
    .wc-status-warn { color: #b45309; }
    .dark .wc-status-warn { color: #fbbf24; }
    .wc-status-progress { color: #0369a1; }
    .dark .wc-status-progress { color: #38bdf8; }
    .wc-status-missed { color: #be123c; }
    .dark .wc-status-missed { color: #fb7185; }

    /* Legend chips */
    .wc-legend-group { display: flex; align-items: center; flex-wrap: wrap; gap: 0.5rem; }
    .wc-legend-label { font-size: 0.625rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; color: var(--color-slate-400); margin-right: 0.125rem; }
    .wc-legend-chip {
        display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.25rem 0.625rem;
        border-radius: 9999px; font-size: 0.72rem; font-weight: 500; color: var(--color-slate-600);
        background: var(--color-slate-50); border: 1px solid var(--color-slate-200);
    }
    .dark .wc-legend-chip { color: var(--color-slate-300); background: rgba(255,255,255,0.03); border-color: var(--color-slate-700); }
</style>
@endpush

@section('content')
    <div class="page-header">
        <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-full bg-pcrm-50 dark:bg-pcrm-900/30 flex items-center justify-center flex-shrink-0 text-sm font-bold text-pcrm-700 dark:text-pcrm-400 border border-pcrm-100 dark:border-pcrm-800">
                {{ Str::of(Str::of($employee->name)->explode(' ')->last())->substr(0, 1)->upper() }}
            </div>
            <div>
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100 leading-tight">{{ $employee->name }}</p>
                <p class="page-subtitle !mt-0 !text-xs">
                    {{ $employee->code }}@if($employee->position) &middot; {{ $employee->position }} @endif
                </p>
            </div>
        </div>
        @can('export-own-schedule')
        <button onclick="openModal('exportMyScheduleModal')" class="btn-secondary">
            <i class="bi bi-file-earmark-excel"></i>
            <span>Xuất Excel</span>
        </button>
        @endcan
    </div>

    <div class="card mx-auto overflow-hidden">
        <div class="px-4 sm:px-5 py-3 border-b border-slate-100 dark:border-slate-700 flex flex-wrap items-center gap-x-5 gap-y-2 bg-slate-50/50 dark:bg-slate-800/40">
            <div class="wc-legend-group">
                <span class="wc-legend-label">Loại</span>
                <span class="wc-legend-chip"><span class="w-2 h-2 rounded-full bg-sky-400"></span> Ca làm việc</span>
                <span class="wc-legend-chip"><span class="w-2 h-2 rounded-full bg-slate-400"></span> Nghỉ phép</span>
                <span class="wc-legend-chip">🏠 WFH</span>
            </div>
            <span class="hidden sm:block w-px h-5 bg-slate-200 dark:bg-slate-700"></span>
            <div class="wc-legend-group">
                <span class="wc-legend-label">Chấm công</span>
                <span class="wc-legend-chip wc-status-ok">✓ Đủ</span>
                <span class="wc-legend-chip wc-status-warn">⏰ Trễ/sớm</span>
                <span class="wc-legend-chip wc-status-progress">🟡 Đang trong ca</span>
                <span class="wc-legend-chip wc-status-missed">⚠ Chưa chấm công</span>
            </div>
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
