@php
    $confirmationMode = $confirmationMode ?? 'admin';
    $isEmployeeView = $confirmationMode === 'employee';
    $isConfirmed = $confirmation && $confirmation->isConfirmed();
    $periodLabel = str_pad($month, 2, '0', STR_PAD_LEFT) . '/' . $year;
    $monthChangeUrl = $isEmployeeView
        ? route('timesheet-confirmation.index')
        : route('timesheet-confirmations.show', $employee);
    // Nhân viên part_time trả lương theo giờ nên chỉ cần theo dõi "Giờ làm" — "Công" (quy đổi
    // theo giờ chuẩn của ca fulltime) không có ý nghĩa với họ. Các loại còn lại (full_time,
    // probation, intern) vẫn theo dõi "Công" như trước.
    $isPartTime = ($employee->employment_type ?? null) === 'part_time';
    $stats = [
        $isPartTime ? [
            'value' => $summary['worked_hours'] ?? 0,
            'label' => 'Giờ làm',
            'note' => 'Tổng thời gian',
            'icon' => 'bi-clock-history',
        ] : [
            'value' => $summary['total_actual_workdays'] ?? 0,
            'label' => 'Ngày làm',
            'note' => 'Công thực tế',
            'icon' => 'bi-calendar2-check',
        ],
        [
            'value' => '+' . ($summary['overtime_hours'] ?? 0) . 'h',
            'label' => 'Tăng ca',
            'note' => ($summary['overtime_shifts'] ?? 0) . ' ca đã duyệt',
            'icon' => 'bi-plus-circle',
        ],
        [
            'value' => $summary['late_count'] ?? 0,
            'label' => 'Lượt đi trễ',
            'note' => $late_minutes_total . ' phút',
            'icon' => 'bi-exclamation-circle',
        ],
        [
            'value' => $summary['paid_leave_days'] ?? 0,
            'label' => 'Nghỉ có lương',
            'note' => 'Đã được duyệt',
            'icon' => 'bi-calendar-heart',
        ],
        [
            'value' => $summary['unpaid_leave_days'] ?? 0,
            'label' => 'Nghỉ không lương',
            'note' => 'Đã được duyệt',
            'icon' => 'bi-calendar-minus',
        ],
    ];
@endphp

<header class="mb-4 sm:mb-6">
    <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
        <div class="flex min-w-0 items-center gap-3 sm:gap-4">
            <x-employee-avatar :employee="$employee" size="h-12 w-12 sm:h-14 sm:w-14" shape="rounded-xl sm:rounded-2xl"
                text="text-lg sm:text-xl" fallback="bg-pcrm-800 text-white dark:bg-pcrm-300 dark:text-pcrm-900"
                class="shadow-[0_10px_24px_rgba(30,64,175,0.18)]" />
            <div class="min-w-0">
                <p class="mb-1 text-xs font-semibold tracking-[0.14em] text-pcrm-600 dark:text-pcrm-400">KỲ CÔNG {{ $periodLabel }}</p>
                <h2 class="truncate text-xl font-bold tracking-tight text-pcrm-900 sm:text-2xl dark:text-white">{{ $employee->name }}</h2>
                <p class="mt-1 truncate text-sm text-slate-500 dark:text-slate-400">
                    <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $employee->code }}</span>
                    <span class="mx-1.5 text-slate-300 dark:text-slate-600">/</span>
                    {{ $employee->branch->name ?? 'Chưa có chi nhánh' }}
                    @if($employee->team)
                        <span class="mx-1.5 text-slate-300 dark:text-slate-600">/</span>{{ $employee->team->name }}
                    @endif
                </p>
            </div>
        </div>

        <div data-testid="timesheet-toolbar"
            class="{{ $isEmployeeView ? 'w-full sm:w-44' : 'grid w-full grid-cols-[minmax(0,1fr)_auto] items-end gap-2 sm:flex sm:w-auto' }}">
            <div class="min-w-0 sm:w-44">
                <label for="timesheet-month" class="mb-1.5 inline-flex items-center gap-1.5 text-xs font-semibold text-pcrm-800 dark:text-pcrm-300">
                    <i class="bi bi-calendar3 text-pcrm-500" aria-hidden="true"></i>
                    <span>Kỳ công</span>
                </label>
                <select id="timesheet-month" class="form-input h-10 w-full px-3 py-0 text-base font-medium sm:text-sm"
                    onchange="const [m,y]=this.value.split('-'); window.location='{{ $monthChangeUrl }}?month='+m+'&year='+y;">
                    @for($i = 0; $i < 12; $i++)
                        @php $optionDate = now()->copy()->subMonths($i); @endphp
                        <option value="{{ $optionDate->month }}-{{ $optionDate->year }}" @selected($optionDate->month == $month && $optionDate->year == $year)>
                            Tháng {{ $optionDate->translatedFormat('m/Y') }}
                        </option>
                    @endfor
                </select>
            </div>
            @if(!$isEmployeeView)
                <a href="{{ route('timesheet-confirmations.index', ['month' => $month, 'year' => $year]) }}"
                    class="btn-secondary h-10 shrink-0 whitespace-nowrap px-3.5 text-sm">
                    <i class="bi bi-arrow-left"></i><span>Danh sách</span>
                </a>
            @endif
        </div>
    </div>
</header>

@if(!$summary)
    <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
        <div class="flex flex-col items-center px-6 py-16 text-center">
            <span class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-2xl text-slate-400 dark:bg-slate-700">
                <i class="bi bi-calendar-x"></i>
            </span>
            <h3 class="text-base font-semibold text-slate-800 dark:text-slate-100">Chưa có dữ liệu công</h3>
            <p class="mt-1 max-w-md text-sm leading-6 text-slate-500 dark:text-slate-400">
                Không tìm thấy dữ liệu chấm công của {{ $employee->name }} trong tháng {{ $periodLabel }}.
            </p>
        </div>
    </section>
@else
    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_19rem] xl:gap-6">
        <main class="min-w-0">
            <section class="mb-4 overflow-hidden rounded-2xl border border-pcrm-100 bg-pcrm-50 px-4 pb-4 pt-4 shadow-[0_14px_36px_rgba(30,64,175,0.08)] sm:mb-6 sm:px-6 sm:pb-5 dark:border-pcrm-900/60 dark:bg-pcrm-900/25">
                <div class="mb-4 flex items-center justify-between sm:mb-5">
                    <div>
                        <p class="text-sm font-semibold text-pcrm-900 dark:text-pcrm-200">Tổng quan chấm công</p>
                        <p class="mt-1 text-xs text-pcrm-700/70 dark:text-pcrm-400">Số liệu đã gồm đơn nghỉ được duyệt.</p>
                    </div>
                    <span class="rounded-lg bg-white px-2.5 py-1 text-xs font-semibold tabular-nums text-pcrm-800 shadow-sm ring-1 ring-pcrm-100 dark:bg-pcrm-900/40 dark:text-pcrm-200 dark:ring-pcrm-800">{{ $periodLabel }}</span>
                </div>
                <div class="grid grid-cols-2 gap-2.5 sm:gap-3 lg:grid-cols-5">
                    @foreach($stats as $stat)
                        <article class="rounded-xl bg-white/90 p-3.5 shadow-sm ring-1 ring-pcrm-100/80 last:col-span-2 lg:last:col-span-1 dark:bg-pcrm-900/30 dark:ring-pcrm-900/70">
                            <span class="mb-3 flex h-8 w-8 items-center justify-center rounded-lg bg-pcrm-100 text-sm text-pcrm-700 dark:bg-pcrm-900/50 dark:text-pcrm-300">
                                <i class="bi {{ $stat['icon'] }}"></i>
                            </span>
                            <p class="text-2xl font-bold tabular-nums tracking-tight text-pcrm-900 dark:text-white">{{ $stat['value'] }}</p>
                            <p class="mt-1 text-xs font-semibold text-pcrm-800 dark:text-pcrm-200">{{ $stat['label'] }}</p>
                            <p class="mt-0.5 text-[11px] text-pcrm-600/70 dark:text-pcrm-400">{{ $stat['note'] }}</p>
                        </article>
                    @endforeach
                </div>
            </section>

            <div class="mb-4 xl:hidden">
                @include('timesheet-confirmations.partials.confirmation-panel', ['confirmationMode' => $confirmationMode])
            </div>

            @include('timesheet-confirmations.partials.daily-table')

            @if($leave_detail->isNotEmpty())
                <div class="mt-6">
                    @include('timesheet-confirmations.partials.leave-table')
                </div>
            @endif
        </main>

        <aside class="hidden xl:sticky xl:top-5 xl:block xl:self-start">
            @include('timesheet-confirmations.partials.confirmation-panel', ['confirmationMode' => $confirmationMode])
        </aside>
    </div>
@endif
