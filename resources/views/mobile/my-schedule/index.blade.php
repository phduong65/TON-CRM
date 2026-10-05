@extends('layouts.admin')

@section('title', 'Lịch làm việc')

@section('content')
@php
    $dowShort = fn (\Carbon\Carbon $d) => $d->dayOfWeekIso === 7 ? 'CN' : 'Thứ ' . ($d->dayOfWeekIso + 1);
    $dowLong = fn (\Carbon\Carbon $d) => mb_convert_case($d->copy()->locale('vi')->isoFormat('dddd'), MB_CASE_TITLE, 'UTF-8');
    $nowLocal = \Carbon\Carbon::now('Asia/Ho_Chi_Minh');
    $today = $nowLocal->toDateString();
    $selKey = $selectedDate->toDateString();
    $daySchedules = $weekSchedules->get($selKey, collect());
    $prevWeek = $weekStart->copy()->subWeek()->toDateString();
    $nextWeek = $weekStart->copy()->addWeek()->toDateString();
    $canCheckin = auth()->user()->can('checkin-attendance');
    $card = 'bg-white dark:bg-slate-900 rounded-2xl border border-blue-100/70 dark:border-slate-800 shadow-[0_4px_20px_rgba(37,99,235,0.07)]';
@endphp
<div class="space-y-4 pb-6">
    {{-- Lịch tuần --}}
    <section aria-labelledby="schedTitle" class="{{ $card }} p-4">
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2.5">
                <i class="bi bi-calendar-week text-blue-600 text-xl"></i>
                <h1 id="schedTitle" class="text-[13px] font-extrabold uppercase tracking-wide text-[#0B1F5C] dark:text-slate-200">Lịch làm việc</h1>
            </div>
        </div>

        <div class="flex items-center justify-between mt-1">
            <a href="{{ route('my-schedule.index', ['date' => $prevWeek]) }}" aria-label="Tuần trước" class="w-11 h-11 flex items-center justify-center text-blue-600 rounded-full hover:bg-blue-50 active:scale-95"><i class="bi bi-chevron-left text-lg"></i></a>
            <p class="text-[17px] font-extrabold text-[#0B1F5C] dark:text-white tabular-nums">{{ $weekStart->format('d/m') }} — {{ $weekEnd->format('d/m/Y') }}</p>
            <a href="{{ route('my-schedule.index', ['date' => $nextWeek]) }}" aria-label="Tuần sau" class="w-11 h-11 flex items-center justify-center text-blue-600 rounded-full hover:bg-blue-50 active:scale-95"><i class="bi bi-chevron-right text-lg"></i></a>
        </div>

        <nav class="grid grid-cols-7 gap-1 mt-2" aria-label="Chọn ngày trong tuần">
            @for ($i = 0; $i < 7; $i++)
                @php
                    $d = $weekStart->copy()->addDays($i);
                    $key = $d->toDateString();
                    $isSel = $key === $selKey;
                    $isToday = $key === $today;
                    $hasShift = $weekSchedules->has($key);
                @endphp
                <a href="{{ route('my-schedule.index', ['date' => $key]) }}" @if($isSel) aria-current="date" @endif
                   aria-label="{{ $dowShort($d) }} {{ $d->format('d/m') }}{{ $hasShift ? ', có ca' : '' }}"
                   class="min-h-[52px] rounded-lg flex flex-col items-center justify-center text-center transition active:scale-95
                          {{ $isSel ? 'bg-blue-600 text-white shadow-[0_4px_12px_rgba(37,99,235,0.35)]' : ($isToday ? 'text-blue-600 bg-blue-50 border border-blue-100 dark:bg-blue-950/40' : 'text-[#475569] border border-slate-100 dark:border-slate-800 dark:text-slate-300') }}">
                    <span class="text-[11px] leading-tight {{ $isSel ? 'font-semibold' : '' }}">{{ $dowShort($d) }}</span>
                    <span class="text-[11px] leading-tight tabular-nums mt-0.5 {{ $isSel ? 'font-semibold' : '' }}">{{ $d->format('d/m') }}</span>
                </a>
            @endfor
        </nav>
    </section>

    {{-- Ca của ngày đang chọn --}}
    @forelse ($daySchedules as $sched)
        @php
            $shift = $sched->effectiveShift();
            $log = $sched->attendanceLog;
            $isWfh = ($shift->work_mode ?? 'onsite') === 'wfh';
            $isDone = $log?->check_in_at && $log?->check_out_at;
            $start = substr($shift->start_time ?? '00:00', 0, 5);
            $end = substr($shift->end_time ?? '00:00', 0, 5);
            $branchName = ($sched->branch ?? $employee->branch)?->name;

            // Tiến độ ca: hôm nay theo giờ hiện tại, ngày đã qua = 100%, ngày tới = 0%.
            $progress = 0;
            if ($isDone || $selKey < $today) {
                $progress = 100;
            } elseif ($selKey === $today && $shift) {
                $startAt = \Carbon\Carbon::parse($selKey . ' ' . $start, 'Asia/Ho_Chi_Minh');
                $endAt = \Carbon\Carbon::parse($selKey . ' ' . $end, 'Asia/Ho_Chi_Minh');
                if ($endAt->lte($startAt)) {
                    $endAt->addDay();
                }
                $total = max(1, $startAt->diffInMinutes($endAt));
                $progress = (int) round(min(100, max(0, $startAt->diffInMinutes($nowLocal, false) / $total * 100)));
            }

            if ($isDone) {
                [$badge, $badgeClass] = ['Hoàn thành', 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300'];
            } elseif ($log?->check_in_at) {
                [$badge, $badgeClass] = ['Đang trong ca', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400'];
            } elseif ($sched->isMissed()) {
                [$badge, $badgeClass] = ['Vắng mặt', 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-400'];
            } elseif ($selKey === $today) {
                [$badge, $badgeClass] = ['Hôm nay', 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300'];
            } else {
                [$badge, $badgeClass] = ['Đã xếp ca', 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'];
            }
            $showActions = $canCheckin && $selKey === $today && !$isDone && !$sched->isMissed();
        @endphp
        <article class="{{ $card }} p-4" aria-label="{{ $shift->name ?? 'Ca làm việc' }}">
            <div class="flex items-start gap-3">
                <i class="bi bi-briefcase-fill text-[28px] text-blue-600 w-10 text-center shrink-0 leading-none mt-1"></i>
                <div class="min-w-0 flex-1">
                    <h2 class="text-lg font-bold text-[#0B1F5C] dark:text-white truncate">{{ $shift->name ?? ($sched->isFlexible() ? 'Ca linh hoạt' : 'Ca làm việc') }}</h2>
                    <p class="text-[15px] text-[#475569] dark:text-slate-400 truncate">{{ $start }} – {{ $end }} • {{ $isWfh ? 'WFH' : 'Tại chỗ' }}</p>
                </div>
                <span class="shrink-0 px-2.5 py-1 rounded-lg text-xs font-bold {{ $badgeClass }}">{{ $badge }}</span>
            </div>

            <div class="grid grid-cols-2 mt-3 pt-3 border-t border-slate-100 dark:border-slate-800 text-center">
                <div class="border-r border-slate-100 dark:border-slate-800">
                    <p class="text-xs text-[#475569] dark:text-slate-400">Vào ca</p>
                    <p class="text-lg font-bold text-[#0B1F5C] dark:text-white tabular-nums">{{ $log?->check_in_at ? $log->check_in_at->format('H:i') : $start }}</p>
                </div>
                <div>
                    <p class="text-xs text-[#475569] dark:text-slate-400">Ra ca</p>
                    <p class="text-lg font-bold text-[#0B1F5C] dark:text-white tabular-nums">{{ $log?->check_out_at ? $log->check_out_at->format('H:i') : $end }}</p>
                </div>
            </div>
        </article>
    @empty
        <div class="{{ $card }} p-6 text-center">
            <i class="bi bi-calendar-x text-3xl text-blue-300"></i>
            <p class="mt-2 text-[15px] font-semibold text-[#334155] dark:text-slate-300">Bạn chưa được xếp ca vào {{ $dowShort($selectedDate) }}, {{ $selectedDate->format('d/m') }}.</p>
        </div>
    @endforelse

    {{-- Ca làm tiếp theo --}}
    @if ($upcomingSchedules->isNotEmpty())
        <section aria-labelledby="upcomingTitle" class="space-y-2.5">
            <div class="flex items-center justify-between px-1">
                <h2 id="upcomingTitle" class="text-[13px] font-extrabold text-[#475569] dark:text-slate-400 uppercase tracking-wide">Ca làm tiếp theo</h2>
                
            </div>
            @foreach ($upcomingSchedules->take(2) as $up)
                @php $upShift = $up->effectiveShift(); @endphp
                <a href="{{ route('my-schedule.index', ['date' => $up->work_date->toDateString()]) }}" class="{{ $card }} flex items-center gap-3 p-3.5 min-h-[64px] active:scale-[0.99] transition">
                    <i class="bi bi-calendar-week text-2xl text-blue-600 w-9 text-center shrink-0"></i>
                    <span class="min-w-0 flex-1">
                        <span class="block text-[15px] font-bold text-[#0B1F5C] dark:text-white truncate">{{ $upShift->name ?? ($up->isFlexible() ? 'Ca linh hoạt' : 'Ca làm việc') }}</span>
                    </span>
                    <span class="text-right shrink-0">
                        <span class="block text-[13px] text-[#334155] dark:text-slate-300">{{ $dowLong($up->work_date) }}, {{ $up->work_date->format('d/m') }}</span>
                        <span class="block text-[13px] text-[#475569] dark:text-slate-400 tabular-nums">{{ substr($upShift->start_time ?? '00:00', 0, 5) }} – {{ substr($upShift->end_time ?? '00:00', 0, 5) }}</span>
                    </span>
                    <i class="bi bi-chevron-right text-slate-400"></i>
                </a>
            @endforeach
        </section>
    @endif
</div>
@endsection
