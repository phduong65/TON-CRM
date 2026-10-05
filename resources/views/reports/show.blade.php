@extends('layouts.admin')

@php
    $statusMeta = [
        'pending'  => ['Chờ duyệt', 'badge-warning', 'bi-clock'],
        'approved' => ['Đã duyệt', 'badge-success', 'bi-check-circle-fill'],
        'rejected' => ['Từ chối', 'badge-danger', 'bi-x-circle-fill'],
    ];
    [$statusLabel, $statusBadge, $statusIcon] = $statusMeta[$report->status] ?? [$report->statusLabel(), $report->statusBadgeClass(), 'bi-info-circle'];

    $canApprove = auth()->user()->can('approve-reports');
    $canDecide = $canApprove && $report->status === 'pending';
    $canCancel = $report->status === 'pending' && $report->created_by === auth()->id();

    $targets = $report->targetEmployees();
    $chargeable = $report->chargeableTargetEmployees();
    $deductEach = (int) ($report->violation?->points_deducted ?? 0);
    $reporterName = $canApprove ? ($report->reporter?->name ?? 'người báo cáo') : 'người báo cáo';
    $evidenceFiles = $report->evidence_files ?? [];
@endphp

@section('title', 'Báo cáo ' . $report->code)
@section('page-title', 'Báo cáo ' . $report->code)
@section('breadcrumb', 'Kỷ luật / Báo cáo')

@section('page-subtitle')
    <span class="{{ $statusBadge }} align-middle"><i class="bi {{ $statusIcon }}" aria-hidden="true"></i> {{ $statusLabel }}</span>
    <span class="ml-1.5 align-middle">{{ $report->typeLabel() }} · {{ $report->violation?->name ?? 'Không chọn vi phạm' }}</span>
@endsection

@section('page-actions')
    <a href="{{ route('reports.index') }}" class="btn-secondary">
        <i class="bi bi-arrow-left"></i> <span>Danh sách</span>
    </a>
    @if ($canCancel)
        <button type="button" class="btn-outline-danger"
                onclick="openCancelReportModal({{ Illuminate\Support\Js::from(route('reports.destroy', $report)) }}, {{ Illuminate\Support\Js::from($report->code) }})">
            <i class="bi bi-trash"></i> <span>Huỷ báo cáo</span>
        </button>
    @endif
    @if ($canDecide)
        <button type="button" class="btn-outline-danger" onclick="openModal('rejectReportModal')">
            <i class="bi bi-x-circle"></i> <span>Từ chối</span>
        </button>
        <button type="button" class="btn-primary" onclick="openModal('approveReportModal')">
            <i class="bi bi-check2-circle"></i> <span>Duyệt báo cáo</span>
        </button>
    @endif
@endsection

@section('content')
    {{-- ① Trạng thái --}}
    <div class="record-status is-{{ $report->status }} mb-5" role="status">
        <span class="record-status-icon" aria-hidden="true"><i class="bi {{ $statusIcon }}"></i></span>
        <div class="min-w-0 text-sm text-slate-700 dark:text-slate-300">
            @switch($report->status)
                @case('pending')
                    <p class="font-semibold text-slate-900 dark:text-white">Đang chờ duyệt — chưa cộng/trừ điểm</p>
                    <p class="mt-0.5">Gửi lúc {{ $report->created_at->format('H:i d/m/Y') }} · đã chờ {{ $report->created_at->diffForHumans(null, true) }}.</p>
                    @break
                @case('approved')
                    <p class="font-semibold text-slate-900 dark:text-white">Đã duyệt — cộng {{ $report->reward_points }} điểm cho {{ $reporterName }}{{ $report->deducted_points > 0 ? ', trừ tổng ' . $report->deducted_points . ' điểm người vi phạm' : '' }}</p>
                    <p class="mt-0.5">Duyệt bởi {{ $report->reviewer?->name ?? 'N/A' }}{{ $report->reviewed_at ? ' lúc ' . $report->reviewed_at->format('H:i d/m/Y') : '' }}.</p>
                    @break
                @case('rejected')
                    <p class="font-semibold text-slate-900 dark:text-white">Báo cáo bị từ chối — không cộng/trừ điểm</p>
                    <p class="mt-0.5">
                        {{ $report->reviewer?->name ? 'Bởi ' . $report->reviewer->name : '' }}{{ $report->reviewed_at ? ' lúc ' . $report->reviewed_at->format('H:i d/m/Y') : '' }}.
                        @if ($report->rejection_reason) <span class="text-slate-500">Lý do:</span> {{ $report->rejection_reason }} @endif
                    </p>
                    @break
            @endswitch
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
        <div class="xl:col-span-2 space-y-5">

            {{-- ② Người liên quan --}}
            <section class="card" aria-labelledby="reportPeopleTitle">
                <div class="card-header flex items-center justify-between gap-3">
                    <h2 id="reportPeopleTitle">Người liên quan</h2>
                    <span class="badge-neutral">{{ $report->typeLabel() }}@if ($report->type === 'team' && $report->team) · {{ $report->team->name }}@endif</span>
                </div>
                <div class="card-body space-y-4">
                    <div>
                        <p class="mb-1.5 text-xs font-medium text-slate-500 dark:text-slate-400">Người báo cáo</p>
                        @if ($canApprove)
                            <p class="font-medium text-slate-900 dark:text-white">{{ $report->reporter?->name ?? '—' }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                <span class="font-mono">{{ $report->reporter?->code }}</span>@if ($report->reporter?->branch) · {{ $report->reporter->branch->name }}@endif
                            </p>
                        @else
                            <p class="inline-flex items-center gap-1 text-sm italic text-slate-400"><i class="bi bi-incognito" aria-hidden="true"></i> Ẩn danh — chỉ người duyệt thấy danh tính</p>
                        @endif
                    </div>
                    <div>
                        <p class="mb-1.5 text-xs font-medium text-slate-500 dark:text-slate-400">Người bị báo cáo ({{ $targets->count() }})</p>
                        @if ($targets->isEmpty())
                            <p class="text-sm text-slate-500">—</p>
                        @else
                            <ul class="divide-y divide-slate-100 dark:divide-slate-700/60 rounded-xl border border-slate-100 dark:border-slate-700/60 {{ $targets->count() > 6 ? 'max-h-80 overflow-y-auto' : '' }}">
                                @foreach ($targets as $t)
                                    @php $exempt = $t->isExemptFromScoring(); @endphp
                                    <li class="flex items-center gap-3 px-4 py-2.5">
                                        <span class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 flex items-center justify-center text-xs font-bold shrink-0" aria-hidden="true">
                                            {{ mb_strtoupper(mb_substr($t->name, 0, 1)) }}
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $t->name }}</p>
                                            <p class="font-mono text-xs text-slate-400">{{ $t->code }}</p>
                                        </div>
                                        @if ($exempt)
                                            <span class="badge-neutral" title="Không thuộc diện chấm điểm kỷ luật"><i class="bi bi-shield-check" aria-hidden="true"></i> Miễn trừ điểm</span>
                                        @elseif ($deductEach > 0)
                                            <span class="points-chip">-{{ $deductEach }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </section>

            {{-- ③ Nội dung báo cáo --}}
            <section class="card" aria-labelledby="reportContentTitle">
                <div class="card-header"><h2 id="reportContentTitle">Nội dung báo cáo</h2></div>
                <div class="card-body">
                    <dl class="fact-list">
                        <div>
                            <dt>Vi phạm được báo cáo</dt>
                            <dd class="font-medium">{{ $report->violation?->name ?? 'Không chọn' }}</dd>
                        </div>
                        <div>
                            <dt>Quy chế</dt>
                            <dd>{{ $report->violation?->regulation?->name ?? '—' }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt>Mô tả sự việc</dt>
                            <dd class="whitespace-pre-line text-slate-700 dark:text-slate-300">{{ $report->description }}</dd>
                        </div>
                        @if ($report->evidence_note)
                            <div class="sm:col-span-2">
                                <dt>Ghi chú bằng chứng</dt>
                                <dd class="whitespace-pre-line text-slate-700 dark:text-slate-300">{{ $report->evidence_note }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </section>

            {{-- ④ Bằng chứng --}}
            <section class="card" aria-labelledby="reportEvidenceTitle">
                <div class="card-header"><h2 id="reportEvidenceTitle">Bằng chứng <span class="font-normal text-slate-400">({{ count($evidenceFiles) }})</span></h2></div>
                <div class="card-body">
                    @if (count($evidenceFiles) > 0)
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            @foreach ($evidenceFiles as $filePath)
                                @php
                                    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                                    $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                    $fileUrl = asset('storage/' . $filePath);
                                @endphp
                                <div class="relative aspect-square overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-800">
                                    @if ($isImage)
                                        <a href="{{ $fileUrl }}" target="_blank" rel="noopener" class="group block h-full w-full" aria-label="Mở ảnh bằng chứng {{ $loop->iteration }}">
                                            <img src="{{ $fileUrl }}" alt="Bằng chứng {{ $loop->iteration }}" loading="lazy"
                                                 class="h-full w-full object-cover transition-transform duration-200 group-hover:scale-105">
                                        </a>
                                    @else
                                        <video controls preload="metadata" class="h-full w-full bg-black object-contain" aria-label="Video bằng chứng {{ $loop->iteration }}">
                                            <source src="{{ $fileUrl }}" type="{{ $ext === 'mov' ? 'video/quicktime' : 'video/' . $ext }}">
                                        </video>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-slate-500 dark:text-slate-400"><i class="bi bi-paperclip" aria-hidden="true"></i> Báo cáo không có tệp bằng chứng.</p>
                    @endif
                </div>
            </section>
        </div>

        <div class="space-y-5">
            {{-- Tác động điểm khi duyệt --}}
            <section class="card" aria-labelledby="reportImpactTitle">
                <div class="card-header"><h2 id="reportImpactTitle">{{ $report->status === 'approved' ? 'Điểm đã áp dụng' : 'Tác động khi duyệt' }}</h2></div>
                <div class="card-body">
                    <dl class="space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">Cộng cho người báo cáo</dt>
                            <dd><span class="points-chip points-chip-plus">+{{ $report->reward_points }}</span></dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">Trừ mỗi người vi phạm</dt>
                            <dd>@if ($deductEach > 0)<span class="points-chip">-{{ $deductEach }}</span>@else<span class="text-slate-400">—</span>@endif</dd>
                        </div>
                        @if ($deductEach > 0)
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-slate-500 dark:text-slate-400">Số người bị trừ</dt>
                                <dd class="font-medium">{{ $chargeable->count() }}{{ $chargeable->count() < $targets->count() ? ' / ' . $targets->count() : '' }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-slate-500 dark:text-slate-400">Tổng điểm trừ</dt>
                                <dd><span class="points-chip">-{{ $report->status === 'approved' ? $report->deducted_points : $deductEach * $chargeable->count() }}</span></dd>
                            </div>
                        @endif
                    </dl>
                    @if ($deductEach > 0 && $chargeable->count() < $targets->count())
                        <p class="pcrm-help mt-3">Admin/Giám đốc trong danh sách được miễn trừ điểm.</p>
                    @endif
                </div>
            </section>

            {{-- Lịch sử xử lý --}}
            <section class="card" aria-labelledby="reportHistoryTitle">
                <div class="card-header"><h2 id="reportHistoryTitle">Lịch sử xử lý</h2></div>
                <div class="card-body">
                    @if ($history->isNotEmpty())
                        <ol class="timeline">
                            @foreach ($history as $event)
                                @php
                                    $desc = $event->description;
                                    [$kind, $icon] = match (true) {
                                        str_starts_with($desc, 'Duyệt')    => ['approved', 'bi-check-lg'],
                                        str_starts_with($desc, 'Từ chối')  => ['rejected', 'bi-x-lg'],
                                        str_starts_with($desc, 'Huỷ')      => ['deleted', 'bi-trash'],
                                        str_starts_with($desc, 'Tạo')      => ['created', 'bi-plus-lg'],
                                        default                            => ['updated', 'bi-pencil'],
                                    };
                                    // Người không có quyền duyệt không thấy tên người báo cáo trong log (giữ ẩn danh)
                                    $actor = $canApprove || $event->causer_id === auth()->id() ? ($event->causer?->name ?? 'Hệ thống') : 'Người xử lý';
                                @endphp
                                <li>
                                    <span class="timeline-dot is-{{ $kind }}" aria-hidden="true"><i class="bi {{ $icon }}"></i></span>
                                    <div class="min-w-0 pt-0.5 text-sm">
                                        <p class="text-slate-800 dark:text-slate-200">{{ $canApprove ? $desc : \Illuminate\Support\Str::before($desc, ' — ') }}</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $actor }} · <time datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->format('H:i d/m/Y') }}</time></p>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @else
                        <p class="text-sm text-slate-500 dark:text-slate-400">Chưa có lịch sử được ghi nhận.</p>
                    @endif
                </div>
            </section>
        </div>
    </div>
@endsection

@push('modals')
    @if ($canDecide)
        <div id="approveReportModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
             onclick="if(event.target===this)closeModal('approveReportModal')">
            <div class="pcrm-dialog max-w-md" role="dialog" aria-modal="true" aria-labelledby="approveReportTitle">
                <div class="pcrm-dialog-head">
                    <span class="pcrm-dialog-icon bg-[#e8f5ef] text-[#168A63] dark:bg-[#168A63]/20 dark:text-[#34d399]" aria-hidden="true"><i class="bi bi-check2-circle"></i></span>
                    <div class="min-w-0 flex-1">
                        <h3 id="approveReportTitle" class="pcrm-dialog-title">Duyệt báo cáo {{ $report->code }}</h3>
                        <p class="pcrm-dialog-sub">Điểm được áp dụng ngay sau khi duyệt</p>
                    </div>
                    <button type="button" onclick="closeModal('approveReportModal')" class="pcrm-dialog-close" aria-label="Đóng"><i class="bi bi-x-lg"></i></button>
                </div>
                <form action="{{ route('reports.approve', $report) }}" method="POST" class="pcrm-dialog-form">
                    @csrf
                    <div class="pcrm-dialog-body">
                        <ul class="space-y-2 text-sm">
                            <li class="flex items-center justify-between gap-3">
                                <span>{{ $report->reporter?->name ?? 'Người báo cáo' }} <span class="text-xs text-slate-400">(người báo cáo)</span></span>
                                <span class="points-chip points-chip-plus">+{{ $report->reward_points }}</span>
                            </li>
                            @if ($deductEach > 0)
                                @foreach ($chargeable as $t)
                                    <li class="flex items-center justify-between gap-3">
                                        <span>{{ $t->name }}</span>
                                        <span class="points-chip">-{{ $deductEach }}</span>
                                    </li>
                                @endforeach
                            @endif
                        </ul>
                    </div>
                    <div class="pcrm-dialog-foot justify-end">
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="closeModal('approveReportModal')" class="btn-secondary">Hủy</button>
                            <button type="submit" class="btn-primary"><i class="bi bi-check2"></i> Xác nhận duyệt</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div id="rejectReportModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
             onclick="if(event.target===this)closeModal('rejectReportModal')">
            <div class="pcrm-dialog max-w-md" role="dialog" aria-modal="true" aria-labelledby="rejectReportTitle">
                <div class="pcrm-dialog-head">
                    <span class="pcrm-dialog-icon bg-[#fde8ea] text-[#C94758] dark:bg-[#C94758]/20 dark:text-[#fb7185]" aria-hidden="true"><i class="bi bi-x-circle"></i></span>
                    <div class="min-w-0 flex-1">
                        <h3 id="rejectReportTitle" class="pcrm-dialog-title">Từ chối báo cáo {{ $report->code }}</h3>
                        <p class="pcrm-dialog-sub">Không cộng/trừ điểm; người báo cáo thấy lý do</p>
                    </div>
                    <button type="button" onclick="closeModal('rejectReportModal')" class="pcrm-dialog-close" aria-label="Đóng"><i class="bi bi-x-lg"></i></button>
                </div>
                <form action="{{ route('reports.reject', $report) }}" method="POST" class="pcrm-dialog-form">
                    @csrf
                    <input type="hidden" name="_modal" value="rejectReportModal">
                    <div class="pcrm-dialog-body">
                        <div>
                            <label for="reportRejectionReason" class="form-label">Lý do từ chối <span class="text-red-500">*</span></label>
                            <textarea id="reportRejectionReason" name="rejection_reason" rows="3" required
                                      class="form-input @error('rejection_reason') border-red-400 @enderror"
                                      placeholder="VD: Không đủ bằng chứng, sai người…">{{ old('rejection_reason') }}</textarea>
                            @error('rejection_reason') <p class="form-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="pcrm-dialog-foot justify-end">
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="closeModal('rejectReportModal')" class="btn-secondary">Hủy</button>
                            <button type="submit" class="btn-danger"><i class="bi bi-x-circle"></i> Xác nhận từ chối</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($canCancel)
        @include('reports.partials.cancel-modal')
    @endif
@endpush

@if ($errors->has('rejection_reason'))
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () { openModal('rejectReportModal'); });
    </script>
    @endpush
@endif
