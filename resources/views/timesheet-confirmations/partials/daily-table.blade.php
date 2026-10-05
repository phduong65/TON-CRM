@php
    // Nhân viên part_time xem "Giờ làm" theo ngày thay vì "Công" (quy đổi theo giờ chuẩn ca
    // fulltime, không áp dụng cho part_time) — xem giải thích ở overview.blade.php.
    $isPartTime = ($employee->employment_type ?? null) === 'part_time';
@endphp
<section class="overflow-hidden rounded-2xl border border-pcrm-100 bg-pcrm-50/70 dark:border-pcrm-900/60 dark:bg-pcrm-900/20">
    <div class="flex items-center justify-between border-b border-pcrm-100 bg-white/70 px-4 py-4 sm:px-5 dark:border-pcrm-900/60 dark:bg-pcrm-900/15">
        <div>
            <h3 class="font-semibold text-pcrm-900 dark:text-white">Chi tiết theo ngày</h3>
            <p class="mt-0.5 text-xs text-pcrm-700/70 dark:text-pcrm-400">
                Giờ vào/ra, tình trạng bất thường và công được ghi nhận.
            </p>
        </div>
        <span class="hidden text-xs font-medium tabular-nums text-pcrm-600/70 sm:block">{{ count($days) }} ngày</span>
    </div>

    <div class="space-y-2.5 p-3 md:hidden">
        @foreach($days as $i => $day)
            @php
                $mobileDayLogs = $logs_by_date->get($day->toDateString(), collect());
                $mobileLate = (int) $mobileDayLogs->sum('late_minutes');
                $mobileEarly = (int) $mobileDayLogs->sum('early_minutes');
                $mobileHasException = $mobileLate > 0 || $mobileEarly > 0;
                $mobileDayCell = $isPartTime ? ($day_cells_hours[$i] ?? '') : ($day_cells[$i] ?? '');
                $mobileOvertime = (float) $mobileDayLogs->sum('overtime_hours');
                $mobileShiftNames = $mobileDayLogs
                    ->map(fn ($log) => $log->shiftSchedule?->shift?->name)
                    ->filter()
                    ->unique();
            @endphp
            <article class="rounded-xl bg-white p-3.5 shadow-sm ring-1 ring-pcrm-100 dark:bg-slate-800 dark:ring-pcrm-900/60">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-11 w-11 shrink-0 flex-col items-center justify-center rounded-xl {{ $day->isWeekend() ? 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' : 'bg-pcrm-100 text-pcrm-800 dark:bg-pcrm-900/45 dark:text-pcrm-200' }}">
                            <span class="text-base font-bold tabular-nums leading-none">{{ $day->format('d') }}</span>
                            <span class="mt-1 text-[9px] font-semibold uppercase">{{ $day->translatedFormat('D') }}</span>
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-pcrm-900 dark:text-white">
                                {{ $mobileShiftNames->isNotEmpty() ? $mobileShiftNames->join(' · ') : ($day->isWeekend() ? 'Cuối tuần' : 'Chưa có ca') }}
                            </p>
                            <p class="mt-0.5 text-xs text-pcrm-600/70 dark:text-pcrm-400">{{ $day->translatedFormat('d/m/Y') }}</p>
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        <span class="inline-flex min-w-10 justify-center rounded-lg bg-pcrm-50 px-2 py-1.5 text-xs font-bold tabular-nums text-pcrm-900 ring-1 ring-pcrm-100 dark:bg-pcrm-900/30 dark:text-pcrm-200 dark:ring-pcrm-800/60">
                            {{ $mobileDayCell !== '' ? $mobileDayCell : '—' }}
                        </span>
                        @if($mobileOvertime > 0)
                            <span class="inline-flex justify-center rounded-md bg-rose-50 px-2 py-1 text-[10px] font-semibold tabular-nums text-rose-700 dark:bg-rose-900/30 dark:text-rose-300"
                                  title="Giờ tăng ca đã duyệt (đã cộng vào công)">
                                +{{ rtrim(rtrim(number_format($mobileOvertime, 2, '.', ''), '0'), '.') }}h TC
                            </span>
                        @endif
                    </div>
                </div>

                <div class="mt-3 grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 border-t border-pcrm-50 pt-3 dark:border-pcrm-900/40">
                    <div>
                        @forelse($mobileDayLogs as $log)
                            <div class="mb-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-sm tabular-nums last:mb-0">
                                <span class="text-xs text-pcrm-600/70 dark:text-pcrm-400">Vào</span>
                                <strong class="text-pcrm-900 dark:text-white">{{ $log->check_in_at?->format('H:i') ?? '—' }}</strong>
                                <i class="bi bi-arrow-right text-[10px] text-pcrm-300"></i>
                                <span class="text-xs text-pcrm-600/70 dark:text-pcrm-400">Ra</span>
                                <strong class="text-pcrm-900 dark:text-white">{{ $log->check_out_at?->format('H:i') ?? '—' }}</strong>
                                @if($log->shift_start_time && $log->shift_end_time)
                                    <span class="text-[10px] font-medium text-slate-400 dark:text-slate-500">
                                        (Ca: {{ substr($log->shift_start_time, 0, 5) }}–{{ substr($log->shift_end_time, 0, 5) }})
                                    </span>
                                @endif
                            </div>
                        @empty
                            <span class="text-xs text-slate-400">Chưa ghi nhận giờ vào/ra</span>
                        @endforelse
                    </div>
                    <div class="flex flex-wrap justify-end gap-1">
                        @if($mobileHasException)
                            @if($mobileLate > 0)
                                <span class="rounded-md bg-amber-50 px-2 py-1 text-[10px] font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">Trễ {{ $mobileLate }}p</span>
                            @endif
                            @if($mobileEarly > 0)
                                <span class="rounded-md bg-rose-50 px-2 py-1 text-[10px] font-semibold text-rose-700 dark:bg-rose-900/30 dark:text-rose-300">Sớm {{ $mobileEarly }}p</span>
                            @endif
                        @elseif($mobileDayLogs->isNotEmpty())
                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                                <i class="bi bi-check-circle-fill"></i> Đúng giờ
                            </span>
                        @endif
                    </div>
                </div>
            </article>
        @endforeach
    </div>

    <div class="table-container hidden rounded-none border-0 md:block">
        <table class="table-base">
            <thead>
                <tr>
                    <th class="table-th">Ngày</th>
                    <th class="table-th">Giờ vào / ra</th>
                    <th class="table-th text-center">Bất thường</th>
                    <th class="table-th text-center">{{ $isPartTime ? 'Giờ làm' : 'Công' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($days as $i => $day)
                    @php
                        $dayLogs = $logs_by_date->get($day->toDateString(), collect());
                        $late = (int) $dayLogs->sum('late_minutes');
                        $early = (int) $dayLogs->sum('early_minutes');
                        $hasException = $late > 0 || $early > 0;
                        $dayCell = $isPartTime ? ($day_cells_hours[$i] ?? '') : ($day_cells[$i] ?? '');
                        $dayOvertime = (float) $dayLogs->sum('overtime_hours');
                    @endphp
                    <tr class="table-tr-hover {{ $day->isWeekend() ? 'bg-slate-50/70 dark:bg-slate-900/20' : '' }}">
                        <td class="table-td">
                            <div class="flex items-center gap-2">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-bold tabular-nums {{ $day->isWeekend() ? 'bg-slate-200 text-slate-500 dark:bg-slate-700 dark:text-slate-300' : 'bg-pcrm-50 text-pcrm-700 dark:bg-pcrm-900/30 dark:text-pcrm-300' }}">
                                    {{ $day->format('d') }}
                                </span>
                                <span>
                                    <span class="block text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $day->translatedFormat('D') }}</span>
                                    <span class="block text-[11px] text-slate-400">{{ $day->format('m/Y') }}</span>
                                </span>
                            </div>
                        </td>
                        <td class="table-td">
                            @forelse($dayLogs as $log)
                                <div class="mb-1 flex items-center gap-1.5 text-sm tabular-nums last:mb-0">
                                    <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $log->check_in_at?->format('H:i') ?? '—' }}</span>
                                    <span class="h-px w-3 bg-slate-300 dark:bg-slate-600"></span>
                                    <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $log->check_out_at?->format('H:i') ?? '—' }}</span>
                                    @if($log->shift_start_time && $log->shift_end_time)
                                        <span class="text-[11px] font-medium text-slate-400 dark:text-slate-500">
                                            (Ca: {{ substr($log->shift_start_time, 0, 5) }}–{{ substr($log->shift_end_time, 0, 5) }})
                                        </span>
                                    @endif
                                </div>
                            @empty
                                <span class="text-slate-300 dark:text-slate-600">Chưa ghi nhận</span>
                            @endforelse
                        </td>
                        <td class="table-td text-center">
                            @if($hasException)
                                <div class="inline-flex flex-wrap justify-center gap-1">
                                    @if($late > 0)
                                        <span class="rounded-md bg-amber-50 px-2 py-1 text-[11px] font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">Trễ {{ $late }}p</span>
                                    @endif
                                    @if($early > 0)
                                        <span class="rounded-md bg-rose-50 px-2 py-1 text-[11px] font-semibold text-rose-700 dark:bg-rose-900/30 dark:text-rose-300">Sớm {{ $early }}p</span>
                                    @endif
                                </div>
                            @else
                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-50 text-xs text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400">
                                    <i class="bi bi-check2"></i>
                                </span>
                            @endif
                        </td>
                        <td class="table-td text-center">
                            <span class="inline-flex min-w-8 justify-center rounded-md bg-slate-100 px-2 py-1 text-xs font-bold tabular-nums text-slate-700 dark:bg-slate-700 dark:text-slate-200">
                                {{ $dayCell !== '' ? $dayCell : '—' }}
                            </span>
                            @if($dayOvertime > 0)
                                <span class="mt-1 inline-flex justify-center rounded-md bg-rose-50 px-2 py-1 text-[11px] font-semibold tabular-nums text-rose-700 dark:bg-rose-900/30 dark:text-rose-300"
                                      title="Giờ tăng ca đã duyệt (đã cộng vào công)">
                                    +{{ rtrim(rtrim(number_format($dayOvertime, 2, '.', ''), '0'), '.') }}h TC
                                </span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
