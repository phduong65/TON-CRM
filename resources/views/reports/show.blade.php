@extends('layouts.admin')

@section('title', 'Báo cáo ' . $report->code)
@section('page-title', 'Báo cáo ' . $report->code)
@section('breadcrumb', 'Báo cáo / Chi tiết')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Left: Report details --}}
    <div class="lg:col-span-2 space-y-6">
        <div class="card">
            <div class="card-header">
                <h3 class="font-semibold text-slate-900 dark:text-white">Thông tin báo cáo</h3>
                <span class="{{ $report->statusBadgeClass() }}">{{ $report->statusLabel() }}</span>
            </div>
            <div class="card-body space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-slate-400 uppercase tracking-wider">Mã báo cáo</p>
                        <p class="font-mono text-sm font-medium mt-0.5">{{ $report->code }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 uppercase tracking-wider">Ngày tạo</p>
                        <p class="text-sm mt-0.5">{{ $report->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 uppercase tracking-wider">Người báo cáo</p>
                        <p class="text-sm font-medium mt-0.5">{{ $report->reporter?->name ?? '—' }}</p>
                        @if($report->reporter?->code)
                            <p class="text-xs text-slate-400">{{ $report->reporter->code }} · {{ $report->reporter->branch?->name }}</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 uppercase tracking-wider">Nhân viên bị báo cáo</p>
                        <p class="text-sm font-medium mt-0.5">{{ $report->reported?->name ?? '—' }}</p>
                        @if($report->reported?->code)
                            <p class="text-xs text-slate-400">{{ $report->reported->code }} · {{ $report->reported->branch?->name }}</p>
                        @endif
                    </div>
                    @if($report->violation)
                    <div class="col-span-2">
                        <p class="text-xs text-slate-400 uppercase tracking-wider">Vi phạm được báo cáo</p>
                        <p class="text-sm font-medium mt-0.5">{{ $report->violation->name }}</p>
                        @if($report->violation->points_deducted > 0)
                            <p class="text-xs text-red-500 dark:text-red-400 mt-0.5">Trừ {{ $report->violation->points_deducted }} điểm khi duyệt</p>
                        @endif
                    </div>
                    @endif
                    <div class="col-span-2">
                        <p class="text-xs text-slate-400 uppercase tracking-wider">Mô tả sự việc</p>
                        <p class="text-sm text-slate-700 dark:text-slate-300 mt-0.5 whitespace-pre-line">{{ $report->description }}</p>
                    </div>
                    @if($report->evidence_note)
                    <div class="col-span-2">
                        <p class="text-xs text-slate-400 uppercase tracking-wider">Bằng chứng / Ghi chú</p>
                        <p class="text-sm text-slate-700 dark:text-slate-300 mt-0.5 whitespace-pre-line">{{ $report->evidence_note }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        @if($report->status !== 'pending')
        <div class="card">
            <div class="card-header">
                <h3 class="font-semibold text-slate-900 dark:text-white">Kết quả xét duyệt</h3>
            </div>
            <div class="card-body space-y-3">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-slate-400 uppercase tracking-wider">Người xét duyệt</p>
                        <p class="text-sm font-medium mt-0.5">{{ $report->reviewer?->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 uppercase tracking-wider">Ngày xét duyệt</p>
                        <p class="text-sm mt-0.5">{{ $report->reviewed_at?->format('d/m/Y H:i') ?? '—' }}</p>
                    </div>
                    @if($report->status === 'approved')
                    <div class="col-span-2 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-100 dark:border-emerald-900/40">
                        <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-400">
                            <i class="bi bi-check-circle-fill mr-1"></i>
                            Báo cáo đã được duyệt
                        </p>
                        <p class="text-xs text-emerald-600 dark:text-emerald-500 mt-1">
                            Cộng <strong>+{{ $report->reward_points }} điểm</strong> cho {{ $report->reporter?->name ?? 'người báo cáo' }}
                            @if($report->violation?->points_deducted > 0)
                                · Trừ <strong>{{ $report->violation->points_deducted }} điểm</strong> của {{ $report->reported?->name ?? 'người bị báo cáo' }}
                            @endif
                        </p>
                    </div>
                    @elseif($report->status === 'rejected')
                    <div class="col-span-2 p-3 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-900/40">
                        <p class="text-sm font-semibold text-red-700 dark:text-red-400">
                            <i class="bi bi-x-circle-fill mr-1"></i>
                            Báo cáo bị từ chối
                        </p>
                        <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $report->rejection_reason }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- Right: Actions --}}
    <div class="space-y-4">
        {{-- Points info --}}
        <div class="card">
            <div class="card-body space-y-3">
                <div class="flex items-center gap-3 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-900/20">
                    <div class="w-9 h-9 rounded-lg bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center">
                        <i class="bi bi-star-fill text-emerald-600 dark:text-emerald-400"></i>
                    </div>
                    <div>
                        <p class="text-xs text-emerald-600 dark:text-emerald-500">Điểm thưởng khi duyệt</p>
                        <p class="text-lg font-bold text-emerald-700 dark:text-emerald-300">+{{ $report->reward_points }} điểm</p>
                    </div>
                </div>
                @if($report->violation?->points_deducted > 0)
                <div class="flex items-center gap-3 p-3 rounded-xl bg-red-50 dark:bg-red-900/20">
                    <div class="w-9 h-9 rounded-lg bg-red-100 dark:bg-red-900/40 flex items-center justify-center">
                        <i class="bi bi-dash-circle-fill text-red-600 dark:text-red-400"></i>
                    </div>
                    <div>
                        <p class="text-xs text-red-600 dark:text-red-500">Điểm trừ người bị báo cáo</p>
                        <p class="text-lg font-bold text-red-700 dark:text-red-300">-{{ $report->violation->points_deducted }} điểm</p>
                    </div>
                </div>
                @endif
            </div>
        </div>

        @can('approve-reports')
            @if($report->status === 'pending')
            {{-- Approve --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="font-semibold text-slate-900 dark:text-white text-sm">Duyệt báo cáo</h3>
                </div>
                <div class="card-body">
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">
                        Khi duyệt, hệ thống sẽ tự động cộng <strong class="text-emerald-600">+{{ $report->reward_points }} điểm</strong> cho <strong>{{ $report->reporter?->name }}</strong>
                        @if($report->violation?->points_deducted > 0)
                            và trừ <strong class="text-red-600">{{ $report->violation->points_deducted }} điểm</strong> của <strong>{{ $report->reported?->name }}</strong>
                        @endif.
                    </p>
                    <form action="{{ route('reports.approve', $report) }}" method="POST">
                        @csrf
                        <button type="submit"
                                onclick="return confirm('Xác nhận duyệt báo cáo {{ $report->code }}?')"
                                class="btn-primary w-full">
                            <i class="bi bi-check2-circle"></i>
                            Duyệt báo cáo
                        </button>
                    </form>
                </div>
            </div>

            {{-- Reject --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="font-semibold text-slate-900 dark:text-white text-sm">Từ chối</h3>
                </div>
                <div class="card-body">
                    <form action="{{ route('reports.reject', $report) }}" method="POST" class="space-y-3">
                        @csrf
                        <div>
                            <label class="form-label text-xs">Lý do từ chối <span class="text-red-500">*</span></label>
                            <textarea name="rejection_reason" rows="3"
                                      class="form-input @error('rejection_reason') border-red-400 @enderror"
                                      placeholder="Nhập lý do từ chối báo cáo...">{{ old('rejection_reason') }}</textarea>
                            @error('rejection_reason')
                                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit" class="btn-danger w-full text-sm">
                            <i class="bi bi-x-circle"></i>
                            Từ chối báo cáo
                        </button>
                    </form>
                </div>
            </div>
            @endif
        @endcan

        {{-- Back link --}}
        <a href="{{ route('reports.index') }}" class="btn-secondary w-full">
            <i class="bi bi-arrow-left"></i>
            Quay lại danh sách
        </a>
    </div>

</div>
@endsection
