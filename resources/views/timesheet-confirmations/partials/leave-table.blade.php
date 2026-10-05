<section class="overflow-hidden rounded-2xl border border-pcrm-100 bg-pcrm-50/70 dark:border-pcrm-900/60 dark:bg-pcrm-900/20">
    <div class="flex items-center justify-between border-b border-pcrm-100 bg-white/70 px-4 py-4 sm:px-5 dark:border-pcrm-900/60 dark:bg-pcrm-900/15">
        <div>
            <h3 class="font-semibold text-pcrm-900 dark:text-white">Ngày nghỉ đã ghi nhận</h3>
            <p class="mt-0.5 text-xs text-pcrm-700/70 dark:text-pcrm-400">{{ $leave_detail->count() }} đơn nghỉ được duyệt trong kỳ</p>
        </div>
        <i class="bi bi-calendar2-week text-lg text-pcrm-500"></i>
    </div>
    <div class="space-y-2.5 p-3 sm:hidden">
        @foreach($leave_detail as $leave)
            <article class="rounded-xl bg-white p-3.5 shadow-sm ring-1 ring-pcrm-100 dark:bg-slate-800 dark:ring-pcrm-900/60">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-pcrm-900 dark:text-white">{{ $leave['type_label'] }}</p>
                        <p class="mt-1 text-xs tabular-nums text-pcrm-700/75 dark:text-pcrm-400">
                            {{ $leave['date_from']->format('d/m/Y') }} → {{ $leave['date_to']->format('d/m/Y') }}
                        </p>
                        @if($leave['partial_label'])
                            <p class="mt-1 text-xs text-slate-400">{{ $leave['partial_label'] }}</p>
                        @endif
                    </div>
                    <div class="text-right">
                        <span class="block text-lg font-bold tabular-nums text-pcrm-900 dark:text-pcrm-200">{{ $leave['days'] }}</span>
                        <span class="text-[10px] font-medium text-pcrm-600/70">ngày</span>
                    </div>
                </div>
                <span class="mt-3 inline-flex rounded-md px-2 py-1 text-[10px] font-semibold {{ $leave['is_paid'] ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' }}">
                    {{ $leave['is_paid'] ? 'Có lương' : 'Không lương' }}
                </span>
            </article>
        @endforeach
    </div>
    <div class="table-container hidden rounded-none border-0 sm:block">
        <table class="table-base">
            <thead>
                <tr>
                    <th class="table-th">Khoảng thời gian</th>
                    <th class="table-th">Loại nghỉ</th>
                    <th class="table-th text-center">Số ngày</th>
                </tr>
            </thead>
            <tbody>
                @foreach($leave_detail as $leave)
                    <tr class="table-tr-hover">
                        <td class="table-td">
                            <span class="font-medium text-slate-700 dark:text-slate-200">{{ $leave['date_from']->format('d/m/Y') }}</span>
                            <span class="mx-1 text-slate-300 dark:text-slate-600">→</span>
                            {{ $leave['date_to']->format('d/m/Y') }}
                        </td>
                        <td class="table-td">
                            <span class="font-medium">{{ $leave['type_label'] }}</span>
                            <span class="badge {{ $leave['is_paid'] ? 'badge-success' : 'badge-neutral' }} ml-1">
                                {{ $leave['is_paid'] ? 'Có lương' : 'Không lương' }}
                            </span>
                            @if($leave['partial_label'])
                                <span class="block pt-1 text-xs text-slate-400">{{ $leave['partial_label'] }}</span>
                            @endif
                        </td>
                        <td class="table-td text-center font-semibold tabular-nums">{{ $leave['days'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
