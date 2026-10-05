@extends('layouts.admin')

@php
    $statusMeta = [
        'pending'  => ['Chờ duyệt', 'badge-warning', 'bi-clock'],
        'approved' => ['Đã duyệt', 'badge-success', 'bi-check-circle-fill'],
        'rejected' => ['Từ chối', 'badge-danger', 'bi-x-circle-fill'],
        'revoked'  => ['Đã thu hồi', 'badge-neutral', 'bi-arrow-counterclockwise'],
    ];
    [$statusLabel, $statusBadge, $statusIcon] = $statusMeta[$reward->status] ?? [$reward->status, 'badge-neutral', 'bi-info-circle'];

    $targetType = $reward->target_type ?? 'individual';
    $isIndividual = $targetType === 'individual';
    $targetLabel = match ($targetType) {
        'all'    => 'Tất cả nhân viên',
        'branch' => 'Chi nhánh ' . ($targetName ?? '#' . $reward->target_id),
        'team'   => 'Đội ' . ($targetName ?? '#' . $reward->target_id),
        default  => $reward->employee?->name ?? 'N/A',
    };
    $peopleCount = $isIndividual ? 1 + $reward->members->count() : $reward->members->count();
    $totalPoints = ($isIndividual ? $reward->total_points_awarded : 0) + $reward->members->sum('points_awarded');

    $user = auth()->user();
    $canApprove = $reward->status === 'pending' && $user->can('approve-rewards');
    $canEdit = $reward->status === 'pending' && $user->can('create-rewards');
    $canRevoke = $reward->status === 'approved' && $user->can('revoke-rewards');
@endphp

@section('title', 'Phiếu thưởng ' . $reward->code)
@section('page-title', 'Phiếu thưởng ' . $reward->code)
@section('breadcrumb', 'Thưởng phạt / Thưởng điểm')

@section('page-subtitle')
    <span class="{{ $statusBadge }} align-middle"><i class="bi {{ $statusIcon }}" aria-hidden="true"></i> {{ $statusLabel }}</span>
    <span class="ml-1.5 align-middle">{{ $targetLabel }} · {{ $reward->rewardType?->name ?? 'Thưởng điểm' }}</span>
@endsection

@section('page-actions')
    <a href="{{ route('rewards.index') }}" class="btn-secondary">
        <i class="bi bi-arrow-left"></i> <span>Danh sách</span>
    </a>
    @if ($canEdit)
        <button type="button" class="btn-secondary" onclick="openModal('editRewardModal')">
            <i class="bi bi-pencil"></i> <span>Sửa</span>
        </button>
    @endif
    @if ($canApprove)
        <button type="button" class="btn-outline-danger" onclick="openModal('rejectRewardModal')">
            <i class="bi bi-x-circle"></i> <span>Từ chối</span>
        </button>
        <button type="button" class="btn-primary" onclick="openModal('approveRewardModal')">
            <i class="bi bi-check2-circle"></i> <span>Duyệt phiếu</span>
        </button>
    @endif
    @if ($canRevoke)
        <button type="button" class="btn-outline-danger" onclick="openModal('revokeRewardModal')">
            <i class="bi bi-arrow-counterclockwise"></i> <span>Thu hồi</span>
        </button>
    @endif
@endsection

@section('content')
    {{-- ① Trạng thái hiện tại --}}
    <div class="record-status is-{{ $reward->status }} mb-5" role="status">
        <span class="record-status-icon" aria-hidden="true"><i class="bi {{ $statusIcon }}"></i></span>
        <div class="min-w-0 text-sm text-slate-700 dark:text-slate-300">
            @switch($reward->status)
                @case('pending')
                    <p class="font-semibold text-slate-900 dark:text-white">Đang chờ duyệt — chưa cộng điểm</p>
                    <p class="mt-0.5">Tạo bởi {{ $reward->creator?->name ?? 'hệ thống' }} lúc {{ $reward->created_at->format('H:i d/m/Y') }} · đã chờ {{ $reward->created_at->diffForHumans(null, true) }}.</p>
                    @break
                @case('approved')
                    <p class="font-semibold text-slate-900 dark:text-white">Đã duyệt và cộng {{ number_format($totalPoints) }} điểm cho {{ number_format($peopleCount) }} người</p>
                    <p class="mt-0.5">Duyệt bởi {{ $reward->approver?->name ?? 'N/A' }}{{ $reward->approved_at ? ' lúc ' . $reward->approved_at->format('H:i d/m/Y') : '' }}.</p>
                    @break
                @case('rejected')
                    <p class="font-semibold text-slate-900 dark:text-white">Phiếu đã bị từ chối — không cộng điểm</p>
                    @if ($reward->rejected_reason)
                        <p class="mt-0.5"><span class="text-slate-500">Lý do:</span> {{ $reward->rejected_reason }}</p>
                    @endif
                    @break
                @case('revoked')
                    <p class="font-semibold text-slate-900 dark:text-white">Đã thu hồi — điểm thưởng đã bị trừ lại</p>
                    <p class="mt-0.5">Thu hồi bởi {{ $reward->revoker?->name ?? 'N/A' }}{{ $reward->revoked_at ? ' lúc ' . $reward->revoked_at->format('H:i d/m/Y') : '' }}.
                        @if ($reward->revoked_reason) <span class="text-slate-500">Lý do:</span> {{ $reward->revoked_reason }} @endif</p>
                    @break
                @default
                    <p class="font-semibold">{{ $statusLabel }}</p>
            @endswitch
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
        <div class="xl:col-span-2 space-y-5">

            {{-- ② Người nhận --}}
            <section class="card" aria-labelledby="rewardPeopleTitle">
                <div class="card-header flex items-center justify-between gap-3">
                    <h2 id="rewardPeopleTitle">Người nhận <span class="font-normal text-slate-400">({{ number_format($peopleCount) }})</span></h2>
                    @unless ($isIndividual)
                        <span class="badge-success"><i class="bi bi-people" aria-hidden="true"></i> {{ $targetLabel }}</span>
                    @endunless
                </div>
                <ul class="divide-y divide-slate-100 dark:divide-slate-700/60 {{ $peopleCount > 8 ? 'max-h-[28rem] overflow-y-auto' : '' }}">
                    @if ($isIndividual)
                        @php $mainEmp = $reward->employee; @endphp
                        <li class="flex items-center gap-3 px-5 py-3.5">
                            <x-employee-avatar :employee="$mainEmp" size="w-10 h-10" text="text-sm"
                                fallback="bg-[#e8f5ef] text-[#168A63] dark:bg-[#168A63]/20 dark:text-[#34d399]" />
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
                            <span class="points-chip points-chip-plus">+{{ number_format($reward->total_points_awarded) }}</span>
                        </li>
                    @endif
                    @forelse ($reward->members as $member)
                        <li class="flex items-center gap-3 px-5 py-3">
                            <x-employee-avatar :employee="$member->employee" size="w-9 h-9" text="text-sm" />
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
                            <span class="points-chip points-chip-plus">+{{ number_format($member->points_awarded) }}</span>
                        </li>
                    @empty
                        @unless ($isIndividual)
                            <li class="px-5 py-6 text-center text-sm text-slate-500 dark:text-slate-400">Không có nhân viên đang hoạt động trong đối tượng này.</li>
                        @endunless
                    @endforelse
                </ul>
                @if ($peopleCount > 1)
                    <div class="card-footer flex items-center justify-between text-sm">
                        <span class="text-slate-500 dark:text-slate-400">Tổng điểm thưởng cho {{ number_format($peopleCount) }} người</span>
                        <span class="points-chip points-chip-plus">+{{ number_format($totalPoints) }}</span>
                    </div>
                @endif
            </section>

            {{-- ③ Nội dung thưởng --}}
            <section class="card" aria-labelledby="rewardInfoTitle">
                <div class="card-header"><h2 id="rewardInfoTitle">Nội dung thưởng</h2></div>
                <div class="card-body">
                    <dl class="fact-list">
                        <div>
                            <dt>Loại thưởng</dt>
                            <dd class="font-medium">{{ $reward->rewardType?->name ?? 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt>Danh mục</dt>
                            <dd>{{ $reward->rewardType?->category?->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt>Đối tượng</dt>
                            <dd>{{ $isIndividual ? 'Cá nhân' . ($reward->members->isNotEmpty() ? ' (+ ' . $reward->members->count() . ' người liên đới)' : '') : $targetLabel }}</dd>
                        </div>
                        <div>
                            <dt>Điểm thưởng {{ $isIndividual ? '' : '(mỗi người)' }}</dt>
                            <dd><span class="points-chip points-chip-plus">+{{ number_format($reward->total_points_awarded) }}</span></dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt>Lý do / mô tả</dt>
                            <dd class="whitespace-pre-line text-slate-700 dark:text-slate-300">{{ $reward->description ?: 'Không có mô tả.' }}</dd>
                        </div>
                    </dl>
                </div>
            </section>
        </div>

        <div class="space-y-5">
            <section class="card" aria-labelledby="rewardSummaryTitle">
                <div class="card-header"><h2 id="rewardSummaryTitle">Tóm tắt</h2></div>
                <div class="card-body">
                    <dl class="space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">Mã phiếu</dt>
                            <dd class="font-mono font-medium">{{ $reward->code }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">Tổng điểm thưởng</dt>
                            <dd><span class="points-chip points-chip-plus">+{{ number_format($totalPoints) }}</span></dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">Số người nhận</dt>
                            <dd class="font-medium">{{ number_format($peopleCount) }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">Ngày tạo</dt>
                            <dd>{{ $reward->created_at->format('d/m/Y H:i') }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">Người tạo</dt>
                            <dd class="truncate">{{ $reward->creator?->name ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section class="card" aria-labelledby="rewardHistoryTitle">
                <div class="card-header"><h2 id="rewardHistoryTitle">Lịch sử xử lý</h2></div>
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
                                        str_starts_with($desc, 'Tạo')      => ['created', 'bi-plus-lg'],
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
        <div id="approveRewardModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
             onclick="if(event.target===this)closeModal('approveRewardModal')">
            <div class="pcrm-dialog max-w-md" role="dialog" aria-modal="true" aria-labelledby="approveRewardTitle">
                <div class="pcrm-dialog-head">
                    <span class="pcrm-dialog-icon bg-[#e8f5ef] text-[#168A63] dark:bg-[#168A63]/20 dark:text-[#34d399]" aria-hidden="true"><i class="bi bi-check2-circle"></i></span>
                    <div class="min-w-0 flex-1">
                        <h3 id="approveRewardTitle" class="pcrm-dialog-title">Duyệt phiếu {{ $reward->code }}</h3>
                        <p class="pcrm-dialog-sub">Điểm sẽ được cộng ngay sau khi duyệt</p>
                    </div>
                    <button type="button" onclick="closeModal('approveRewardModal')" class="pcrm-dialog-close" aria-label="Đóng"><i class="bi bi-x-lg"></i></button>
                </div>
                <form action="{{ route('rewards.approve', $reward) }}" method="POST" class="pcrm-dialog-form">
                    @csrf
                    <div class="pcrm-dialog-body">
                        <dl class="space-y-2 text-sm">
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-slate-500 dark:text-slate-400">Đối tượng</dt>
                                <dd class="font-medium text-right">{{ $targetLabel }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-slate-500 dark:text-slate-400">Số người nhận</dt>
                                <dd class="font-medium">{{ number_format($peopleCount) }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-slate-500 dark:text-slate-400">Loại thưởng</dt>
                                <dd class="font-medium text-right">{{ $reward->rewardType?->name ?? 'N/A' }}</dd>
                            </div>
                        </dl>
                    </div>
                    <div class="pcrm-dialog-foot">
                        <span class="text-sm text-slate-500 dark:text-slate-400">Tổng <strong class="text-[#168A63] dark:text-[#34d399]">+{{ number_format($totalPoints) }}</strong> điểm</span>
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="closeModal('approveRewardModal')" class="btn-secondary">Hủy</button>
                            <button type="submit" class="btn-primary"><i class="bi bi-check2"></i> Xác nhận duyệt</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div id="rejectRewardModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
             onclick="if(event.target===this)closeModal('rejectRewardModal')">
            <div class="pcrm-dialog max-w-md" role="dialog" aria-modal="true" aria-labelledby="rejectRewardTitle">
                <div class="pcrm-dialog-head">
                    <span class="pcrm-dialog-icon bg-[#fde8ea] text-[#C94758] dark:bg-[#C94758]/20 dark:text-[#fb7185]" aria-hidden="true"><i class="bi bi-x-circle"></i></span>
                    <div class="min-w-0 flex-1">
                        <h3 id="rejectRewardTitle" class="pcrm-dialog-title">Từ chối phiếu {{ $reward->code }}</h3>
                        <p class="pcrm-dialog-sub">Phiếu sẽ không cộng điểm cho người nhận</p>
                    </div>
                    <button type="button" onclick="closeModal('rejectRewardModal')" class="pcrm-dialog-close" aria-label="Đóng"><i class="bi bi-x-lg"></i></button>
                </div>
                <form action="{{ route('rewards.reject', $reward) }}" method="POST" class="pcrm-dialog-form">
                    @csrf
                    <div class="pcrm-dialog-body">
                        <div>
                            <label for="rewardRejectedReason" class="form-label">Lý do từ chối <span class="text-red-500">*</span></label>
                            <textarea id="rewardRejectedReason" name="rejected_reason" class="form-input" rows="3"
                                      placeholder="Nhập lý do từ chối…" required maxlength="500"></textarea>
                            <p class="pcrm-help">Tối đa 500 ký tự</p>
                        </div>
                    </div>
                    <div class="pcrm-dialog-foot justify-end">
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="closeModal('rejectRewardModal')" class="btn-secondary">Hủy</button>
                            <button type="submit" class="btn-danger"><i class="bi bi-x-circle"></i> Xác nhận từ chối</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($canRevoke)
        <div id="revokeRewardModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
             onclick="if(event.target===this)closeModal('revokeRewardModal')">
            <div class="pcrm-dialog max-w-md" role="dialog" aria-modal="true" aria-labelledby="revokeRewardTitle">
                <div class="pcrm-dialog-head">
                    <span class="pcrm-dialog-icon bg-[#fef3dc] text-[#C98219] dark:bg-[#C98219]/20 dark:text-[#fbbf24]" aria-hidden="true"><i class="bi bi-arrow-counterclockwise"></i></span>
                    <div class="min-w-0 flex-1">
                        <h3 id="revokeRewardTitle" class="pcrm-dialog-title">Thu hồi phiếu {{ $reward->code }}</h3>
                        <p class="pcrm-dialog-sub">Trừ lại điểm thưởng đã cộng cho người nhận</p>
                    </div>
                    <button type="button" onclick="closeModal('revokeRewardModal')" class="pcrm-dialog-close" aria-label="Đóng"><i class="bi bi-x-lg"></i></button>
                </div>
                <form action="{{ route('rewards.revoke', $reward) }}" method="POST" class="pcrm-dialog-form">
                    @csrf
                    <div class="pcrm-dialog-body">
                        <p class="pcrm-callout pcrm-callout-warning">
                            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                            <span>Thu hồi sẽ <strong>trừ lại tổng {{ number_format($totalPoints) }} điểm</strong> đã cộng cho {{ number_format($peopleCount) }} người. Hành động này không thể hoàn tác.</span>
                        </p>
                        <div>
                            <label for="rewardRevokedReason" class="form-label">Lý do thu hồi <span class="text-red-500">*</span></label>
                            <textarea id="rewardRevokedReason" name="revoked_reason" class="form-input" rows="3"
                                      placeholder="Nhập lý do thu hồi…" required maxlength="500"></textarea>
                        </div>
                    </div>
                    <div class="pcrm-dialog-foot justify-end">
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="closeModal('revokeRewardModal')" class="btn-secondary">Hủy</button>
                            <button type="submit" class="btn-danger"><i class="bi bi-arrow-counterclockwise"></i> Xác nhận thu hồi</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($canEdit)
        @php $isEditErr = old('_modal') === 'editRewardModal'; @endphp
        <div id="editRewardModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
             onclick="if(event.target===this)closeModal('editRewardModal')">
            <div class="pcrm-dialog max-w-xl" role="dialog" aria-modal="true" aria-labelledby="editRewardTitle">
                <div class="pcrm-dialog-head">
                    <span class="pcrm-dialog-icon bg-[#fef3dc] text-[#C98219] dark:bg-[#C98219]/20 dark:text-[#fbbf24]" aria-hidden="true"><i class="bi bi-pencil-square"></i></span>
                    <div class="min-w-0 flex-1">
                        <h3 id="editRewardTitle" class="pcrm-dialog-title">Sửa phiếu {{ $reward->code }}</h3>
                        <p class="pcrm-dialog-sub">{{ $isIndividual ? 'Chỉ sửa được khi phiếu còn chờ duyệt' : 'Phiếu tập thể — mức điểm mới áp dụng cho cả ' . number_format($peopleCount) . ' người nhận' }}</p>
                    </div>
                    <button type="button" onclick="closeModal('editRewardModal')" class="pcrm-dialog-close" aria-label="Đóng"><i class="bi bi-x-lg"></i></button>
                </div>
                <form action="{{ route('rewards.update', $reward) }}" method="POST" class="pcrm-dialog-form">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_modal" value="editRewardModal">
                    <div class="pcrm-dialog-body">
                        @if ($isEditErr && $errors->any())
                            <div class="pcrm-callout pcrm-callout-warning" role="alert">
                                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                                <ul class="space-y-0.5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                            </div>
                        @endif
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="editRewardType" class="form-label">Loại thưởng <span class="text-red-500">*</span></label>
                                <select id="editRewardType" name="reward_type_id" class="form-input" required>
                                    @foreach ($rewardTypes as $rt)
                                        <option value="{{ $rt->id }}" @selected(($isEditErr ? old('reward_type_id') : $reward->reward_type_id) == $rt->id)>{{ $rt->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="editRewardPoints" class="form-label">Điểm thưởng{{ $isIndividual ? '' : ' (mỗi người)' }} <span class="text-red-500">*</span></label>
                                <input id="editRewardPoints" type="number" name="total_points_awarded" class="form-input"
                                       value="{{ $isEditErr ? old('total_points_awarded') : $reward->total_points_awarded }}" min="1" max="9999" required>
                            </div>
                        </div>
                        @if ($isIndividual)
                            <div>
                                <label for="editRewardEmployee" class="form-label">Nhân viên được thưởng <span class="text-red-500">*</span></label>
                                <select id="editRewardEmployee" name="employee_id" class="form-input" required>
                                    @foreach ($employees as $emp)
                                        <option value="{{ $emp->id }}" @selected(($isEditErr ? old('employee_id') : $reward->employee_id) == $emp->id)>
                                            {{ $emp->name }} ({{ $emp->code }}){{ $emp->branch ? ' — ' . $emp->branch->name : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                @if ($reward->members->isNotEmpty())
                                    <p class="pcrm-help">Danh sách {{ $reward->members->count() }} người liên đới được giữ nguyên.</p>
                                @endif
                            </div>
                        @endif
                        <div>
                            <label for="editRewardDesc" class="form-label">Lý do / mô tả</label>
                            <textarea id="editRewardDesc" name="description" class="form-input" rows="3" maxlength="2000">{{ $isEditErr ? old('description') : $reward->description }}</textarea>
                        </div>
                    </div>
                    <div class="pcrm-dialog-foot justify-end">
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="closeModal('editRewardModal')" class="btn-secondary">Hủy</button>
                            <button type="submit" class="btn-primary"><i class="bi bi-check2"></i> Lưu thay đổi</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endpush

@push('scripts')
<script>
@if ($errors->any() && old('_modal'))
document.addEventListener('DOMContentLoaded', function () {
    openModal({{ Illuminate\Support\Js::from(old('_modal')) }});
});
@endif
</script>
@endpush
