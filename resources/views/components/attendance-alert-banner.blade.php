@php
    $currentUser = auth()->user();
    $currentEmployee = $currentUser?->employee;
    $employeeAlertsData = null;

    if ($currentEmployee) {
        try {
            $employeeAlertsData = app(\App\Services\AttendanceAlertService::class)->getPendingAlertsForEmployee($currentEmployee->id, true);
        } catch (\Exception $e) {
            $employeeAlertsData = null;
        }
    }
@endphp

@if($employeeAlertsData && $employeeAlertsData['total_count'] > 0)
    <!-- Popup cảnh báo chấm công: tự mở khi vào trang nếu còn cảnh báo CHƯA XEM. Tắt = server đánh dấu 'seen' + seen_at, lần sau không hiện lại -->
    <div id="attendanceAlertDetailsModal" role="dialog" aria-modal="true" aria-labelledby="attendanceAlertTitle"
        class="fixed inset-0 z-[9000] flex items-end justify-center bg-slate-950/55 backdrop-blur-[2px] p-0 sm:items-center sm:p-4">
        <div class="relative flex h-[82dvh] max-h-[90dvh] w-full max-w-lg flex-col overflow-hidden rounded-t-[1.75rem] border border-slate-200 bg-white shadow-2xl animate-fadeIn dark:border-slate-700 dark:bg-slate-800 sm:h-auto sm:max-h-[82vh] sm:rounded-2xl">
            <div aria-hidden="true" class="mx-auto mt-2.5 mb-0 h-1.5 w-12 shrink-0 rounded-full bg-slate-200 dark:bg-slate-600 sm:hidden"></div>

            <div class="flex shrink-0 items-center justify-between gap-3 border-b border-slate-100 px-4 pb-3 pt-3 dark:border-slate-700 sm:px-6 sm:py-4">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400">
                        <i class="bi bi-clock-history text-lg" aria-hidden="true"></i>
                    </div>
                    <div class="min-w-0">
                        <h3 id="attendanceAlertTitle" class="text-sm font-bold leading-snug text-slate-900 dark:text-white sm:text-base">
                            Bạn có {{ $employeeAlertsData['total_count'] }} ca thiếu chấm công
                        </h3>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Vui lòng kiểm tra các ca dưới đây</p>
                    </div>
                </div>
                <button type="button" onclick="dismissAttendanceAlertBanner()" aria-label="Đóng cảnh báo" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 dark:hover:bg-slate-700 dark:hover:text-white">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>

            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain px-4 py-4 sm:p-6">
                <div class="grid grid-cols-2 gap-2.5" aria-label="Tổng hợp loại cảnh báo">
                    <div class="flex min-w-0 items-center gap-2.5 rounded-xl border border-red-100 bg-red-50/70 px-3 py-2.5 dark:border-red-900/40 dark:bg-red-950/20">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-red-600 shadow-sm dark:bg-slate-800 dark:text-red-300">
                            <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
                        </span>
                        <span class="min-w-0">
                            <strong class="block text-lg font-bold leading-none tabular-nums text-slate-900 dark:text-white">{{ $employeeAlertsData['missing_check_in_count'] }}</strong>
                            <span class="mt-1 block text-[11px] leading-tight text-slate-600 dark:text-slate-300">Thiếu Check-in</span>
                        </span>
                    </div>
                    <div class="flex min-w-0 items-center gap-2.5 rounded-xl border border-amber-100 bg-amber-50/70 px-3 py-2.5 dark:border-amber-900/40 dark:bg-amber-950/20">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-amber-600 shadow-sm dark:bg-slate-800 dark:text-amber-300">
                            <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                        </span>
                        <span class="min-w-0">
                            <strong class="block text-lg font-bold leading-none tabular-nums text-slate-900 dark:text-white">{{ $employeeAlertsData['missing_check_out_count'] }}</strong>
                            <span class="mt-1 block text-[11px] leading-tight text-slate-600 dark:text-slate-300">Thiếu Check-out</span>
                        </span>
                    </div>
                </div>

                <div class="flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50 px-3.5 py-3 text-xs leading-relaxed text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200">
                    <i class="bi bi-info-circle-fill mt-0.5 shrink-0 text-sm" aria-hidden="true"></i>
                    <p><strong>Lưu ý:</strong> Ca đã qua giờ kết thúc không thể tự bấm bù trên hệ thống. Cảnh báo này <strong>không chặn</strong> thao tác chấm công cho các ca hiện tại hoặc tương lai.</p>
                </div>

                <div class="space-y-2.5">
                    @foreach($employeeAlertsData['alerts'] as $empAlert)
                        @php
                            $sch = $empAlert->shiftSchedule;
                            $eff = $sch?->effectiveShift();
                            $timeStr = $eff ? (substr($eff->start_time, 0, 5) . ' – ' . substr($eff->end_time, 0, 5)) : '';
                        @endphp
                        <article class="flex flex-col gap-2.5 rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-800 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                    <span class="text-xs font-bold text-slate-900 dark:text-slate-100">
                                        {{ $sch?->work_date?->format('d/m/Y') }}
                                    </span>
                                    <span aria-hidden="true" class="text-slate-300">•</span>
                                    <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                                        {{ $sch?->shift?->name ?? 'Ca linh hoạt' }}
                                    </span>
                                </div>
                                <p class="mt-1 text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">
                                    Giờ ca: <span class="font-mono tabular-nums">{{ $timeStr }}</span>
                                    @if($sch?->branch)
                                        <span class="text-slate-400">· {{ $sch->branch->name }}</span>
                                    @endif
                                </p>
                            </div>

                            <div class="shrink-0">
                                @if($empAlert->alert_type === 'missing_check_in')
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-2.5 py-1 text-[11px] font-bold text-red-700 ring-1 ring-inset ring-red-200 dark:bg-red-900/30 dark:text-red-300 dark:ring-red-800">
                                        <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
                                        Thiếu Check-in
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-bold text-amber-800 ring-1 ring-inset ring-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:ring-amber-800">
                                        <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
                                        Thiếu Check-out
                                    </span>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>

            <div class="flex shrink-0 flex-col gap-2.5 border-t border-slate-100 bg-white px-4 pt-3 dark:border-slate-700 dark:bg-slate-800 sm:flex-row sm:items-center sm:justify-between sm:gap-4 sm:p-4">
                <span class="text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">
                    Liên hệ Quản lý chi nhánh hoặc bộ phận HR
                </span>
                <button type="button" onclick="dismissAttendanceAlertBanner()"
                    class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-blue-900/10 transition hover:bg-blue-700 active:scale-[0.99] focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-800 sm:min-h-10 sm:w-auto sm:text-xs">
                    Đã hiểu & Đóng
                </button>
                <div aria-hidden="true" class="h-[env(safe-area-inset-bottom)] shrink-0 sm:hidden"></div>
            </div>
        </div>
    </div>

    <script>
    function dismissAttendanceAlertBanner() {
        const modal = document.getElementById('attendanceAlertDetailsModal');
        if (modal) modal.remove();

        // Đánh dấu "đã xem" ở server (open -> seen, ghi seen_at) để popup không hiện lại
        fetch('{{ route("attendance-alerts.dismiss-all") }}', {
            method: 'POST',
            keepalive: true,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
        }).catch(err => console.log('Dismiss alerts error', err));
    }
    </script>
@endif
