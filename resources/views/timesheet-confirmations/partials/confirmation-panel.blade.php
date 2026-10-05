@php
    $isEmployeeView = ($confirmationMode ?? 'admin') === 'employee';
@endphp

<section class="overflow-hidden rounded-2xl border {{ $isConfirmed ? 'border-emerald-200 bg-emerald-50/60 dark:border-emerald-900/60 dark:bg-emerald-950/20' : 'border-amber-200 bg-amber-50/60 dark:border-amber-900/60 dark:bg-amber-950/20' }}">
    <div class="{{ $isConfirmed ? 'bg-emerald-600' : 'bg-amber-500' }} px-5 py-4 text-white">
        <div class="flex items-center justify-between gap-3">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-white/15">
                <i class="bi {{ $isConfirmed ? 'bi-patch-check-fill' : 'bi-hourglass-split' }}"></i>
            </span>
            <span class="text-xs font-semibold tabular-nums text-white/75">{{ $periodLabel }}</span>
        </div>
        <p class="mt-5 text-xs font-medium text-white/75">Trạng thái kỳ công</p>
        <p class="mt-1 text-xl font-bold">{{ $isConfirmed ? $confirmation->statusLabel() : 'Chưa xác nhận' }}</p>
    </div>

    <div class="p-5">
        <div class="min-w-0">
            <p class="text-sm leading-6 text-slate-600 dark:text-slate-300">
                @if($isConfirmed)
                    {{ $confirmation->is_proxy_confirmed ? 'Xác nhận hộ bởi ' . ($confirmation->confirmedBy->name ?? '—') : 'Nhân viên đã tự xác nhận' }}
                @else
                    {{ $isEmployeeView ? 'Bạn chưa xác nhận bảng công của kỳ này.' : 'Nhân viên chưa xác nhận bảng công của kỳ này.' }}
                @endif
            </p>
            @if($isConfirmed)
                <p class="mt-1 text-xs tabular-nums text-slate-400">{{ $confirmation->confirmed_at?->format('d/m/Y H:i') }}</p>
            @endif
        </div>
    </div>

    @if($isEmployeeView)
        <form method="POST" action="{{ $isConfirmed ? route('timesheet-confirmation.unconfirm') : route('timesheet-confirmation.confirm') }}" class="px-5 pb-5">
            @csrf
            <input type="hidden" name="month" value="{{ $month }}">
            <input type="hidden" name="year" value="{{ $year }}">
            <button type="submit" class="{{ $isConfirmed ? 'btn-secondary' : 'btn-primary' }} w-full">
                <i class="bi {{ $isConfirmed ? 'bi-x-circle' : 'bi-check2-square' }}"></i>
                {{ $isConfirmed ? 'Huỷ xác nhận' : 'Xác nhận bảng công' }}
            </button>
        </form>
    @elseif(!$isConfirmed)
        @can('confirm-timesheet-on-behalf')
            <form method="POST" action="{{ route('timesheet-confirmations.confirm', $employee) }}" class="px-5 pb-5">
                @csrf
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="year" value="{{ $year }}">
                <button type="submit" class="btn-primary w-full">
                    <i class="bi bi-check2-square"></i>
                    Xác nhận hộ
                </button>
            </form>
        @endcan
    @endif
</section>
