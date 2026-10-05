@extends('layouts.admin')

@php
    $code = $penalty->code ?? '#' . $penalty->id;
    $statusMeta = [
        'pending'  => ['Chờ duyệt', 'badge-warning', 'bi-clock'],
        'approved' => ['Đã duyệt', 'badge-success', 'bi-check-circle-fill'],
        'rejected' => ['Từ chối', 'badge-danger', 'bi-x-circle-fill'],
        'revoked'  => ['Đã thu hồi', 'badge-neutral', 'bi-arrow-counterclockwise'],
    ];
    [$statusLabel, $statusBadge, $statusIcon] = $statusMeta[$penalty->status] ?? [$penalty->status, 'badge-neutral', 'bi-info-circle'];

    $user = auth()->user();
    $canApprove = $penalty->status === 'pending' && $user->can('approve-penalties');
    $canRevoke = $penalty->status === 'approved' && $user->can('revoke-penalties');
    $hasPendingAppeal = $penalty->appeals->contains('status', 'pending');
    $canAppeal = $penalty->status === 'approved' && $user->can('create-appeals')
        && $penalty->employee?->user_id === $user->id && !$hasPendingAppeal;

    $membersPoints = $penalty->members->sum('points_deducted');
    $totalPoints = $penalty->total_points_deducted + $membersPoints;
    $peopleCount = 1 + $penalty->members->count();
@endphp

@section('title', 'Phiếu phạt ' . $code)
@section('page-title', 'Phiếu phạt ' . $code)
@section('breadcrumb', 'Kỷ luật / Phiếu phạt')

@section('page-subtitle')
    <span class="{{ $statusBadge }} align-middle"><i class="bi {{ $statusIcon }}" aria-hidden="true"></i> {{ $statusLabel }}</span>
    <span class="ml-1.5 align-middle">{{ $penalty->employee->name ?? 'N/A' }} · {{ $penalty->violation->name ?? 'Vi phạm nội bộ' }}</span>
@endsection

@section('page-actions')
    <a href="{{ route('penalties.index') }}" class="btn-secondary">
        <i class="bi bi-arrow-left"></i> <span>Danh sách</span>
    </a>
    @if ($canApprove)
        <button type="button" class="btn-outline-danger" onclick="openModal('rejectPenaltyModal')">
            <i class="bi bi-x-circle"></i> <span>Từ chối</span>
        </button>
        <button type="button" class="btn-primary" onclick="openModal('approvePenaltyModal')">
            <i class="bi bi-check2-circle"></i> <span>Duyệt phiếu</span>
        </button>
    @endif
    @if ($canRevoke)
        <button type="button" class="btn-outline-danger" onclick="openModal('revokePenaltyModal')">
            <i class="bi bi-arrow-counterclockwise"></i> <span>Thu hồi</span>
        </button>
    @endif
    @if ($canAppeal)
        <button type="button" class="btn-primary" onclick="openAppealPenaltyModal({{ $penalty->id }}, {{ Illuminate\Support\Js::from($code) }})">
            <i class="bi bi-chat-left-text"></i> <span>Khiếu nại</span>
        </button>
    @endif
@endsection

@section('content')
    {{-- ① Trạng thái hiện tại — điều đầu tiên người xem cần biết --}}
    <div class="record-status is-{{ $penalty->status }} mb-5" role="status">
        <span class="record-status-icon" aria-hidden="true"><i class="bi {{ $statusIcon }}"></i></span>
        <div class="min-w-0 text-sm text-slate-700 dark:text-slate-300">
            @switch($penalty->status)
                @case('pending')
                    <p class="font-semibold text-slate-900 dark:text-white">Đang chờ duyệt — chưa trừ điểm</p>
                    <p class="mt-0.5">Tạo bởi {{ $penalty->creator?->name ?? 'hệ thống' }} lúc {{ $penalty->created_at->format('H:i d/m/Y') }} · đã chờ {{ $penalty->created_at->diffForHumans(null, true) }}.
                        @if ($canApprove) Kiểm tra thông tin và bằng chứng bên dưới trước khi duyệt hoặc từ chối. @endif</p>
                    @break
                @case('approved')
                    <p class="font-semibold text-slate-900 dark:text-white">Đã duyệt và trừ {{ number_format($totalPoints) }} điểm</p>
                    <p class="mt-0.5">Duyệt bởi {{ $penalty->approver->name ?? 'N/A' }}{{ $penalty->approved_at ? ' lúc ' . $penalty->approved_at->format('H:i d/m/Y') : '' }}.
                        @if ($hasPendingAppeal) <span class="font-medium text-[#1686B8]">Phiếu đang có khiếu nại chờ xem xét.</span> @endif</p>
                    @break
                @case('rejected')
                    <p class="font-semibold text-slate-900 dark:text-white">Phiếu đã bị từ chối — không trừ điểm</p>
                    @if ($penalty->rejected_reason)
                        <p class="mt-0.5"><span class="text-slate-500">Lý do:</span> {{ $penalty->rejected_reason }}</p>
                    @endif
                    @break
                @case('revoked')
                    <p class="font-semibold text-slate-900 dark:text-white">Đã thu hồi — điểm đã được hoàn lại</p>
                    <p class="mt-0.5">Thu hồi bởi {{ $penalty->revoker?->name ?? 'N/A' }}{{ $penalty->revoked_at ? ' lúc ' . $penalty->revoked_at->format('H:i d/m/Y') : '' }}.
                        @if ($penalty->revoked_reason) <span class="text-slate-500">Lý do:</span> {{ $penalty->revoked_reason }} @endif</p>
                    @break
                @default
                    <p class="font-semibold">{{ $statusLabel }}</p>
            @endswitch
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
        <div class="xl:col-span-2 space-y-5">

            {{-- ② Người liên quan --}}
            <section class="card" aria-labelledby="penaltyPeopleTitle">
                <div class="card-header flex items-center justify-between gap-3">
                    <h2 id="penaltyPeopleTitle">Người liên quan <span class="font-normal text-slate-400">({{ $peopleCount }})</span></h2>
                </div>
                <ul class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @php $mainEmp = $penalty->employee; @endphp
                    <li class="flex items-center gap-3 px-5 py-3.5">
                        <x-employee-avatar :employee="$mainEmp" size="w-10 h-10" text="text-sm"
                            fallback="bg-[#E9EEFF] text-[#2F55E7] dark:bg-[#2F55E7]/20 dark:text-[#809ff9]" />
                        <div class="min-w-0 flex-1">
                            @if ($mainEmp)
                                <a href="{{ route('employees.show', $mainEmp) }}" class="font-semibold text-slate-900 dark:text-white hover:text-pcrm-600 dark:hover:text-pcrm-400">{{ $mainEmp->name }}</a>
                            @else
                                <span class="font-semibold">N/A</span>
                            @endif
                            <p class="text-xs text-slate-500 dark:text-slate-400 truncate">
                                <span class="font-mono">{{ $mainEmp?->code }}</span>
                                @if ($mainEmp?->team) · {{ $mainEmp->team->name }} @endif
                                @if ($mainEmp?->branch) · {{ $mainEmp->branch->name }} @endif
                            </p>
                        </div>
                        <span class="badge-neutral hidden sm:inline-flex">Người vi phạm</span>
                        <span class="points-chip">-{{ number_format($penalty->total_points_deducted) }}</span>
                    </li>
                    @foreach ($penalty->members as $member)
                        <li class="flex items-center gap-3 px-5 py-3">
                            <x-employee-avatar :employee="$member->employee" size="w-10 h-10" text="text-sm" />
                            <div class="min-w-0 flex-1">
                                @if ($member->employee)
                                    <a href="{{ route('employees.show', $member->employee) }}" class="font-medium text-slate-900 dark:text-white hover:text-pcrm-600 dark:hover:text-pcrm-400">{{ $member->employee->name }}</a>
                                @else
                                    <span class="font-medium">N/A</span>
                                @endif
                                <p class="text-xs text-slate-500 dark:text-slate-400 truncate">
                                    <span class="font-mono">{{ $member->employee?->code }}</span>
                                    @if ($member->employee?->team) · {{ $member->employee->team->name }} @endif
                                    @if ($member->note) · {{ $member->note }} @endif
                                </p>
                            </div>
                            <span class="badge-neutral hidden sm:inline-flex">Liên đới</span>
                            <span class="points-chip">-{{ number_format($member->points_deducted) }}</span>
                        </li>
                    @endforeach
                </ul>
                @if ($penalty->members->isNotEmpty())
                    <div class="card-footer flex items-center justify-between text-sm">
                        <span class="text-slate-500 dark:text-slate-400">Tổng điểm trừ của {{ $peopleCount }} người</span>
                        <span class="points-chip">-{{ number_format($totalPoints) }}</span>
                    </div>
                @endif
            </section>

            {{-- ③ Vi phạm & quy chế --}}
            <section class="card" aria-labelledby="penaltyViolationTitle">
                <div class="card-header">
                    <h2 id="penaltyViolationTitle">Vi phạm &amp; quy chế áp dụng</h2>
                </div>
                <div class="card-body">
                    <dl class="fact-list">
                        <div>
                            <dt>Lỗi vi phạm</dt>
                            <dd class="font-medium">{{ $penalty->violation->name ?? 'Vi phạm nội bộ' }}</dd>
                        </div>
                        <div>
                            <dt>Quy chế</dt>
                            <dd>{{ $penalty->violation?->regulation?->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt>Điểm trừ (người vi phạm)</dt>
                            <dd><span class="points-chip">-{{ number_format($penalty->total_points_deducted) }}</span></dd>
                        </div>
                        <div>
                            <dt>Tiền phạt</dt>
                            <dd class="font-semibold tabular-nums">{{ $penalty->total_money_deducted > 0 ? number_format($penalty->total_money_deducted, 0, ',', '.') . '₫' : 'Không phạt tiền' }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt>Mô tả / ghi chú</dt>
                            <dd class="whitespace-pre-line text-slate-700 dark:text-slate-300">{{ $penalty->description ?: 'Không có mô tả.' }}</dd>
                        </div>
                    </dl>
                </div>
            </section>

            {{-- ④ Bằng chứng --}}
            <section class="card" aria-labelledby="penaltyEvidenceTitle">
                <div class="card-header">
                    <h2 id="penaltyEvidenceTitle">Bằng chứng <span class="font-normal text-slate-400">({{ $penalty->attachments->count() }})</span></h2>
                </div>
                <div class="card-body">
                    @if ($penalty->attachments->isNotEmpty())
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                            @foreach ($penalty->attachments as $att)
                                @if ($att->type === 'image')
                                    <a href="{{ $att->url }}" target="_blank" rel="noopener"
                                       class="group relative block aspect-square overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-800"
                                       aria-label="Mở ảnh {{ $att->filename }}">
                                        <img src="{{ $att->url }}" alt="{{ $att->filename }}" loading="lazy"
                                             class="h-full w-full object-cover transition-transform duration-200 group-hover:scale-105">
                                        <span class="absolute inset-x-0 bottom-0 truncate bg-black/55 px-2 py-1 text-[11px] text-white">{{ $att->filename }}</span>
                                    </a>
                                @else
                                    <a href="{{ $att->url }}" target="_blank" rel="noopener"
                                       class="flex aspect-square flex-col items-center justify-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-3 hover:border-pcrm-300">
                                        <i class="bi bi-play-circle text-3xl text-slate-400" aria-hidden="true"></i>
                                        <span class="line-clamp-2 break-all text-center text-[11px] leading-tight text-slate-600 dark:text-slate-300">{{ $att->filename }}</span>
                                        <span class="text-[11px] text-slate-400">{{ $att->formatted_size }}</span>
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-slate-500 dark:text-slate-400"><i class="bi bi-paperclip" aria-hidden="true"></i> Phiếu không có tệp đính kèm.</p>
                    @endif
                </div>
            </section>
        </div>

        <div class="space-y-5">
            {{-- Tóm tắt --}}
            <section class="card" aria-labelledby="penaltySummaryTitle">
                <div class="card-header"><h2 id="penaltySummaryTitle">Tóm tắt</h2></div>
                <div class="card-body">
                    <dl class="space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">Mã phiếu</dt>
                            <dd class="font-mono font-medium">{{ $code }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">Tổng điểm trừ</dt>
                            <dd><span class="points-chip">-{{ number_format($totalPoints) }}</span></dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">Tiền phạt</dt>
                            <dd class="font-medium tabular-nums">{{ $penalty->total_money_deducted > 0 ? number_format($penalty->total_money_deducted, 0, ',', '.') . '₫' : '—' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">Số người</dt>
                            <dd class="font-medium">{{ $peopleCount }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">Ngày tạo</dt>
                            <dd>{{ $penalty->created_at->format('d/m/Y H:i') }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">Người tạo</dt>
                            <dd class="truncate">{{ $penalty->creator?->name ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </section>

            {{-- ⑤ Khiếu nại --}}
            @if ($penalty->appeals->isNotEmpty())
                <section class="card" aria-labelledby="penaltyAppealsTitle">
                    <div class="card-header flex items-center justify-between gap-3">
                        <h2 id="penaltyAppealsTitle">Khiếu nại <span class="font-normal text-slate-400">({{ $penalty->appeals->count() }})</span></h2>
                        @can('view-appeals')
                            <a href="{{ route('appeals.index') }}" class="text-xs font-semibold text-pcrm-600 dark:text-pcrm-400 hover:underline">Xem tất cả</a>
                        @endcan
                    </div>
                    <ul class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        @foreach ($penalty->appeals as $appeal)
                            <li class="px-5 py-3 text-sm">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-medium text-slate-900 dark:text-white">{{ $appeal->appellant?->name ?? 'N/A' }}</span>
                                    <span class="{{ $appeal->statusBadgeClass() }} text-xs">{{ $appeal->statusLabel() }}</span>
                                </div>
                                <p class="mt-1 text-slate-600 dark:text-slate-300">{{ $appeal->reason }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ $appeal->created_at->format('d/m/Y H:i') }}
                                    @if ($appeal->reviewer) · xem xét bởi {{ $appeal->reviewer->name }} @endif</p>
                                @if ($appeal->reviewer_note)
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400"><span class="font-medium">Phản hồi:</span> {{ $appeal->reviewer_note }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- ⑥ Lịch sử xử lý --}}
            <section class="card" aria-labelledby="penaltyHistoryTitle">
                <div class="card-header"><h2 id="penaltyHistoryTitle">Lịch sử xử lý</h2></div>
                <div class="card-body">
                    @if ($history->isNotEmpty())
                        <ol class="timeline">
                            @foreach ($history as $event)
                                @php
                                    $desc = $event->description;
                                    [$kind, $icon] = match (true) {
                                        str_starts_with($desc, 'Duyệt')    => ['approved', 'bi-check-lg'],
                                        str_starts_with($desc, 'Từ chối')  => ['rejected', 'bi-x-lg'],
                                        str_starts_with($desc, 'Thu hồi')  => ['revoked', 'bi-arrow-counterclockwise'],
                                        str_starts_with($desc, 'Xóa')      => ['deleted', 'bi-trash'],
                                        str_starts_with($desc, 'Tạo'), str_starts_with($desc, 'Tự động tạo') => ['created', 'bi-plus-lg'],
                                        default                            => ['updated', 'bi-pencil'],
                                    };
                                @endphp
                                <li>
                                    <span class="timeline-dot is-{{ $kind }}" aria-hidden="true"><i class="bi {{ $icon }}"></i></span>
                                    <div class="min-w-0 pt-0.5 text-sm">
                                        <p class="text-slate-800 dark:text-slate-200">{{ $desc }}</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">
                                            {{ $event->causer?->name ?? 'Hệ thống' }} · <time datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->format('H:i d/m/Y') }}</time>
                                        </p>
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
    @if ($canApprove)
        {{-- Xác nhận duyệt: tóm tắt điểm sẽ trừ trước khi áp dụng --}}
        <div id="approvePenaltyModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
             onclick="if(event.target===this)closeModal('approvePenaltyModal')">
            <div class="pcrm-dialog max-w-md" role="dialog" aria-modal="true" aria-labelledby="approvePenaltyTitle">
                <div class="pcrm-dialog-head">
                    <span class="pcrm-dialog-icon bg-[#e8f5ef] text-[#168A63] dark:bg-[#168A63]/20 dark:text-[#34d399]" aria-hidden="true"><i class="bi bi-check2-circle"></i></span>
                    <div class="min-w-0 flex-1">
                        <h3 id="approvePenaltyTitle" class="pcrm-dialog-title">Duyệt phiếu {{ $code }}</h3>
                        <p class="pcrm-dialog-sub">Điểm sẽ bị trừ ngay sau khi duyệt</p>
                    </div>
                    <button type="button" onclick="closeModal('approvePenaltyModal')" class="pcrm-dialog-close" aria-label="Đóng"><i class="bi bi-x-lg"></i></button>
                </div>
                <form action="{{ route('penalties.approve', $penalty) }}" method="POST" class="pcrm-dialog-form">
                    @csrf
                    <div class="pcrm-dialog-body">
                        <ul class="space-y-2 text-sm">
                            <li class="flex items-center justify-between gap-3">
                                <span>{{ $penalty->employee->name ?? 'N/A' }}</span>
                                <span class="points-chip">-{{ number_format($penalty->total_points_deducted) }}</span>
                            </li>
                            @foreach ($penalty->members as $member)
                                <li class="flex items-center justify-between gap-3">
                                    <span>{{ $member->employee?->name ?? 'N/A' }} <span class="text-xs text-slate-400">(liên đới)</span></span>
                                    <span class="points-chip">-{{ number_format($member->points_deducted) }}</span>
                                </li>
                            @endforeach
                        </ul>
                        @if ($penalty->total_money_deducted > 0)
                            <p class="text-sm text-slate-600 dark:text-slate-300">Kèm phạt tiền <strong>{{ number_format($penalty->total_money_deducted, 0, ',', '.') }}₫</strong>.</p>
                        @endif
                    </div>
                    <div class="pcrm-dialog-foot">
                        <span class="text-sm text-slate-500 dark:text-slate-400">Tổng <strong class="text-[#d92d20]">-{{ number_format($totalPoints) }}</strong> điểm</span>
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="closeModal('approvePenaltyModal')" class="btn-secondary">Hủy</button>
                            <button type="submit" class="btn-primary"><i class="bi bi-check2"></i> Xác nhận duyệt</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div id="rejectPenaltyModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
             onclick="if(event.target===this)closeModal('rejectPenaltyModal')">
            <div class="pcrm-dialog max-w-md" role="dialog" aria-modal="true" aria-labelledby="rejectPenaltyTitle">
                <div class="pcrm-dialog-head">
                    <span class="pcrm-dialog-icon bg-[#fde8ea] text-[#C94758] dark:bg-[#C94758]/20 dark:text-[#fb7185]" aria-hidden="true"><i class="bi bi-x-circle"></i></span>
                    <div class="min-w-0 flex-1">
                        <h3 id="rejectPenaltyTitle" class="pcrm-dialog-title">Từ chối phiếu {{ $code }}</h3>
                        <p class="pcrm-dialog-sub">Phiếu sẽ không trừ điểm; người tạo nhận được lý do</p>
                    </div>
                    <button type="button" onclick="closeModal('rejectPenaltyModal')" class="pcrm-dialog-close" aria-label="Đóng"><i class="bi bi-x-lg"></i></button>
                </div>
                <form action="{{ route('penalties.reject', $penalty) }}" method="POST" class="pcrm-dialog-form">
                    @csrf
                    <div class="pcrm-dialog-body">
                        <div>
                            <label for="rejectedReason" class="form-label">Lý do từ chối <span class="text-red-500">*</span></label>
                            <textarea id="rejectedReason" name="rejected_reason" class="form-input" rows="3"
                                      placeholder="VD: Bằng chứng chưa đủ, sai người vi phạm…" required maxlength="500"></textarea>
                            <p class="pcrm-help">Tối đa 500 ký tự</p>
                        </div>
                    </div>
                    <div class="pcrm-dialog-foot justify-end">
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="closeModal('rejectPenaltyModal')" class="btn-secondary">Hủy</button>
                            <button type="submit" class="btn-danger"><i class="bi bi-x-circle"></i> Xác nhận từ chối</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($canRevoke)
        <div id="revokePenaltyModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
             onclick="if(event.target===this)closeModal('revokePenaltyModal')">
            <div class="pcrm-dialog max-w-md" role="dialog" aria-modal="true" aria-labelledby="revokePenaltyTitle">
                <div class="pcrm-dialog-head">
                    <span class="pcrm-dialog-icon bg-[#fef3dc] text-[#C98219] dark:bg-[#C98219]/20 dark:text-[#fbbf24]" aria-hidden="true"><i class="bi bi-arrow-counterclockwise"></i></span>
                    <div class="min-w-0 flex-1">
                        <h3 id="revokePenaltyTitle" class="pcrm-dialog-title">Thu hồi phiếu {{ $code }}</h3>
                        <p class="pcrm-dialog-sub">Hoàn lại điểm đã trừ cho nhân viên liên quan</p>
                    </div>
                    <button type="button" onclick="closeModal('revokePenaltyModal')" class="pcrm-dialog-close" aria-label="Đóng"><i class="bi bi-x-lg"></i></button>
                </div>
                <form action="{{ route('penalties.revoke', $penalty) }}" method="POST" class="pcrm-dialog-form">
                    @csrf
                    <div class="pcrm-dialog-body">
                        <p class="pcrm-callout pcrm-callout-warning">
                            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                            <span>Thu hồi sẽ hoàn lại <strong>{{ number_format($penalty->total_points_deducted) }} điểm</strong> cho nhân viên liên quan. Hành động này không thể hoàn tác.</span>
                        </p>
                        <div>
                            <label for="revokedReason" class="form-label">Lý do thu hồi <span class="text-red-500">*</span></label>
                            <textarea id="revokedReason" name="revoked_reason" class="form-input" rows="3"
                                      placeholder="Nhập lý do thu hồi…" required maxlength="500"></textarea>
                        </div>
                    </div>
                    <div class="pcrm-dialog-foot justify-end">
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="closeModal('revokePenaltyModal')" class="btn-secondary">Hủy</button>
                            <button type="submit" class="btn-danger"><i class="bi bi-arrow-counterclockwise"></i> Xác nhận thu hồi</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($canAppeal)
        @include('penalties.partials.appeal-modal')
    @endif
@endpush
