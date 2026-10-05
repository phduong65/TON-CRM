@extends('layouts.admin')

@section('title', 'Khiếu nại')
@section('page-title', 'Khiếu nại')
@section('breadcrumb', 'Kỷ luật / Khiếu nại')
@section('page-subtitle', 'Khiếu nại của nhân viên về phiếu phạt đã duyệt — xem xét để giữ nguyên hoặc thu hồi phiếu')

@section('content')
    @php
        $statusMeta = [
            'pending'  => ['Chờ xét', 'badge-warning', 'bi-clock'],
            'accepted' => ['Đã chấp nhận', 'badge-success', 'bi-check-circle-fill'],
            'rejected' => ['Đã từ chối', 'badge-danger', 'bi-x-circle-fill'],
        ];
        $currentStatus = request('status');
        $tabQuery = fn($status) => array_filter(
            array_merge(request()->except(['status', 'page']), ['status' => $status]),
            fn($v) => $v !== null && $v !== ''
        );
        $canReview = auth()->user()->can('review-appeals');
    @endphp

    <div class="card">
        <nav class="status-tabs" aria-label="Lọc theo trạng thái">
            <a href="{{ route('appeals.index', $tabQuery(null)) }}"
               class="status-tab {{ !$currentStatus ? 'is-active' : '' }}" @if (!$currentStatus) aria-current="page" @endif>
                Tất cả <span class="status-tab-count">{{ number_format($statusCounts->sum()) }}</span>
            </a>
            @foreach ($statusMeta as $key => [$label])
                <a href="{{ route('appeals.index', $tabQuery($key)) }}"
                   class="status-tab {{ $currentStatus === $key ? 'is-active' : '' }} {{ $key === 'pending' && ($statusCounts[$key] ?? 0) > 0 ? 'has-attention' : '' }}"
                   @if ($currentStatus === $key) aria-current="page" @endif>
                    {{ $label }} <span class="status-tab-count">{{ number_format($statusCounts[$key] ?? 0) }}</span>
                </a>
            @endforeach
        </nav>

        <x-table-toolbar :paginator="$appeals" label="khiếu nại">
            <x-slot:info>
                @if (request('search'))
                    <a href="{{ route('appeals.index', request()->except(['search', 'page'])) }}" class="filter-chip" title="Bỏ bộ lọc này">
                        Tìm: "{{ request('search') }}" <i class="bi bi-x" aria-hidden="true"></i><span class="sr-only">Bỏ bộ lọc</span>
                    </a>
                @endif
            </x-slot:info>
            <form action="{{ route('appeals.index') }}" method="GET" class="relative" role="search">
                @if ($currentStatus) <input type="hidden" name="status" value="{{ $currentStatus }}"> @endif
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none" aria-hidden="true"></i>
                <input type="search" name="search" value="{{ request('search') }}" class="form-input h-9 w-56 pl-8 text-sm"
                       placeholder="Mã phiếu hoặc tên nhân viên…" aria-label="Tìm khiếu nại">
            </form>
        </x-table-toolbar>

        @if ($appeals->isEmpty())
            <div class="px-6 py-16 text-center text-slate-400 dark:text-slate-500">
                <i class="bi bi-chat-left-text mb-3 block text-4xl" aria-hidden="true"></i>
                @if ($currentStatus || request('search'))
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-300">Không có khiếu nại nào khớp bộ lọc</p>
                    <a href="{{ route('appeals.index') }}" class="mt-3 inline-flex items-center gap-1.5 text-sm text-pcrm-600 dark:text-pcrm-400 hover:underline">
                        <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Xoá bộ lọc
                    </a>
                @else
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-300">Chưa có khiếu nại nào</p>
                    <p class="mt-1 text-xs">Nhân viên gửi khiếu nại từ trang chi tiết phiếu phạt đã duyệt.</p>
                @endif
            </div>
        @else
            <ul class="divide-y divide-slate-100 dark:divide-slate-700/60">
                @foreach ($appeals as $appeal)
                    @php
                        [$sLabel, $sBadge, $sIcon] = $statusMeta[$appeal->status] ?? [$appeal->statusLabel(), $appeal->statusBadgeClass(), 'bi-info-circle'];
                        $penalty = $appeal->penalty;
                        $emp = $penalty?->employee;
                        $penaltyCode = $penalty?->code ?? '#' . $appeal->penalty_id;
                    @endphp
                    <li class="appeal-item {{ $appeal->status === 'pending' ? 'is-pending' : '' }}">
                        <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
                            <div class="flex min-w-0 items-center gap-3">
                                <x-employee-avatar :employee="$emp" :name="$emp?->name ?? '?'" size="w-10 h-10" text="text-sm"
                                    fallback="bg-[#E9EEFF] text-[#2F55E7] dark:bg-[#2F55E7]/20 dark:text-[#809ff9]" />
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-900 dark:text-white">{{ $emp->name ?? '—' }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        <span class="font-mono">{{ $emp?->code }}</span>
                                        @if ($emp?->team) · {{ $emp->team->name }} @endif
                                        @if ($emp?->branch) · {{ $emp->branch->name }} @endif
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                <span class="{{ $sBadge }}"><i class="bi {{ $sIcon }}" aria-hidden="true"></i> {{ $sLabel }}</span>
                                <time datetime="{{ $appeal->created_at->toIso8601String() }}" title="{{ $appeal->created_at->format('d/m/Y H:i') }}">
                                    {{ $appeal->status === 'pending' ? 'chờ ' . $appeal->created_at->diffForHumans(null, true) : $appeal->created_at->format('d/m/Y H:i') }}
                                </time>
                            </div>
                        </div>

                        {{-- Phiếu phạt bị khiếu nại --}}
                        <div class="appeal-context">
                            @if ($penalty)
                                <a href="{{ route('penalties.show', $penalty) }}" class="font-mono text-xs font-medium text-pcrm-600 dark:text-pcrm-400 hover:underline">{{ $penaltyCode }}</a>
                            @endif
                            <span class="text-slate-700 dark:text-slate-300">{{ $penalty?->violation?->name ?? 'Vi phạm nội bộ' }}</span>
                            <span class="points-chip">-{{ number_format($penalty?->total_points_deducted ?? 0) }}</span>
                        </div>

                        <blockquote class="appeal-reason">
                            <p class="mb-1 text-xs font-medium text-slate-500 dark:text-slate-400">Lý do khiếu nại · {{ $appeal->appellant?->name ?? '—' }}</p>
                            <p class="whitespace-pre-line">{{ $appeal->reason }}</p>
                        </blockquote>

                        @if ($appeal->reviewer_note || $appeal->reviewer)
                            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                <i class="bi bi-reply" aria-hidden="true"></i>
                                <span class="font-medium">Phản hồi{{ $appeal->reviewer ? ' của ' . $appeal->reviewer->name : '' }}:</span>
                                {{ $appeal->reviewer_note ?: ($appeal->status === 'accepted' ? 'Chấp nhận — phiếu phạt đã được thu hồi.' : '—') }}
                                @if ($appeal->reviewed_at) · {{ $appeal->reviewed_at->format('d/m/Y H:i') }} @endif
                            </p>
                        @endif

                        @if ($appeal->status === 'pending' && $canReview)
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <button type="button" class="btn-primary btn-sm"
                                        onclick="openAcceptAppealModal({{ $appeal->id }}, {{ Illuminate\Support\Js::from($penaltyCode) }}, {{ (int) ($penalty?->total_points_deducted ?? 0) }}, {{ Illuminate\Support\Js::from($emp->name ?? '') }})">
                                    <i class="bi bi-check2-circle" aria-hidden="true"></i> Chấp nhận &amp; thu hồi phiếu
                                </button>
                                <button type="button" class="btn-outline-danger btn-sm" onclick="openRejectAppealModal({{ $appeal->id }}, {{ Illuminate\Support\Js::from($penaltyCode) }})">
                                    <i class="bi bi-x-circle" aria-hidden="true"></i> Từ chối
                                </button>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($appeals->hasPages())
            <div class="card-footer">{{ $appeals->links() }}</div>
        @endif
    </div>
@endsection

@push('modals')
    @can('review-appeals')
        <div id="acceptAppealModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
             onclick="if(event.target===this)closeModal('acceptAppealModal')">
            <div class="pcrm-dialog max-w-md" role="dialog" aria-modal="true" aria-labelledby="acceptAppealTitle">
                <div class="pcrm-dialog-head">
                    <span class="pcrm-dialog-icon bg-[#e8f5ef] text-[#168A63] dark:bg-[#168A63]/20 dark:text-[#34d399]" aria-hidden="true"><i class="bi bi-check2-circle"></i></span>
                    <div class="min-w-0 flex-1">
                        <h3 id="acceptAppealTitle" class="pcrm-dialog-title">Chấp nhận khiếu nại</h3>
                        <p class="pcrm-dialog-sub">Phiếu phạt <span id="acceptAppealCode" class="font-mono"></span> sẽ bị thu hồi</p>
                    </div>
                    <button type="button" onclick="closeModal('acceptAppealModal')" class="pcrm-dialog-close" aria-label="Đóng"><i class="bi bi-x-lg"></i></button>
                </div>
                <form id="acceptAppealForm" method="POST" class="pcrm-dialog-form">
                    @csrf
                    <div class="pcrm-dialog-body">
                        <p class="pcrm-callout pcrm-callout-warning">
                            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                            <span>Hoàn lại <strong id="acceptAppealPoints"></strong> điểm cho <strong id="acceptAppealEmployee"></strong>. Hành động này không thể hoàn tác.</span>
                        </p>
                    </div>
                    <div class="pcrm-dialog-foot justify-end">
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="closeModal('acceptAppealModal')" class="btn-secondary">Hủy</button>
                            <button type="submit" class="btn-primary"><i class="bi bi-check2"></i> Chấp nhận &amp; thu hồi</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div id="rejectAppealModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-2 sm:p-4"
             onclick="if(event.target===this)closeModal('rejectAppealModal')">
            <div class="pcrm-dialog max-w-md" role="dialog" aria-modal="true" aria-labelledby="rejectAppealTitle">
                <div class="pcrm-dialog-head">
                    <span class="pcrm-dialog-icon bg-[#fde8ea] text-[#C94758] dark:bg-[#C94758]/20 dark:text-[#fb7185]" aria-hidden="true"><i class="bi bi-x-circle"></i></span>
                    <div class="min-w-0 flex-1">
                        <h3 id="rejectAppealTitle" class="pcrm-dialog-title">Từ chối khiếu nại</h3>
                        <p class="pcrm-dialog-sub">Giữ nguyên phiếu phạt <span id="rejectAppealCode" class="font-mono"></span></p>
                    </div>
                    <button type="button" onclick="closeModal('rejectAppealModal')" class="pcrm-dialog-close" aria-label="Đóng"><i class="bi bi-x-lg"></i></button>
                </div>
                <form id="rejectAppealForm" method="POST" class="pcrm-dialog-form">
                    @csrf
                    <div class="pcrm-dialog-body">
                        <div>
                            <label for="rejectAppealNote" class="form-label">Lý do từ chối <span class="text-red-500">*</span></label>
                            <textarea id="rejectAppealNote" name="reviewer_note" class="form-input" rows="3"
                                      placeholder="Giải thích cho nhân viên vì sao giữ nguyên phiếu…" required maxlength="500"></textarea>
                            <p class="pcrm-help">Nhân viên sẽ thấy phản hồi này. Tối đa 500 ký tự.</p>
                        </div>
                    </div>
                    <div class="pcrm-dialog-foot justify-end">
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="closeModal('rejectAppealModal')" class="btn-secondary">Hủy</button>
                            <button type="submit" class="btn-danger"><i class="bi bi-x-circle"></i> Từ chối khiếu nại</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endcan
@endpush

@can('review-appeals')
@push('scripts')
<script>
function openAcceptAppealModal(id, code, points, employee) {
    document.getElementById('acceptAppealForm').action = '/appeals/' + id + '/accept';
    document.getElementById('acceptAppealCode').textContent = code;
    document.getElementById('acceptAppealPoints').textContent = points;
    document.getElementById('acceptAppealEmployee').textContent = employee || 'nhân viên';
    openModal('acceptAppealModal');
}

function openRejectAppealModal(id, code) {
    const form = document.getElementById('rejectAppealForm');
    form.action = '/appeals/' + id + '/reject';
    form.querySelector('textarea').value = '';
    document.getElementById('rejectAppealCode').textContent = code;
    openModal('rejectAppealModal');
}
</script>
@endpush
@endcan
