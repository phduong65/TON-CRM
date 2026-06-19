@extends('layouts.admin')

@section('title', 'Khiếu nại')
@section('page-title', 'Quản lý khiếu nại')
@section('breadcrumb', 'Kỷ luật / Khiếu nại')

@section('content')
<div class="page-header">
    <div>
        <p class="page-subtitle">Danh sách khiếu nại từ nhân viên bị xử phạt</p>
    </div>
</div>

{{-- Filter bar --}}
<div class="card mb-4">
    <div class="px-4 py-3">
        <form action="{{ route('appeals.index') }}" method="GET">
            <div class="flex gap-2 items-center">
                <div class="relative flex-1 min-w-0">
                    <i class="bi bi-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                           class="form-input pl-7 h-9 text-sm w-full" placeholder="Mã phiếu, tên nhân viên...">
                </div>
                <select name="status" class="form-input h-9 text-sm w-36 shrink-0">
                    <option value="">Tất cả</option>
                    <option value="pending"  @selected(request('status') === 'pending')>Chờ xét</option>
                    <option value="accepted" @selected(request('status') === 'accepted')>Đã chấp nhận</option>
                    <option value="rejected" @selected(request('status') === 'rejected')>Đã từ chối</option>
                </select>
                <button type="submit" class="btn-primary h-9 px-4 text-sm shrink-0">
                    <i class="bi bi-funnel text-xs"></i> Lọc
                </button>
                @if(request()->anyFilled(['search', 'status']))
                    <a href="{{ route('appeals.index') }}" class="btn-secondary h-9 px-3 inline-flex items-center shrink-0">
                        <i class="bi bi-x text-sm"></i>
                    </a>
                @endif
                <span class="text-xs text-slate-400 dark:text-slate-500 ml-auto shrink-0">{{ $appeals->total() }} khiếu nại</span>
            </div>
        </form>
    </div>
</div>

@if ($appeals->isEmpty())
    <div class="card">
        <div class="py-16 text-center text-slate-400 dark:text-slate-500">
            <i class="bi bi-chat-left-text text-4xl mb-3 block"></i>
            <p class="text-sm font-medium">Không có khiếu nại nào</p>
        </div>
    </div>
@else
    <div class="space-y-3">
        @foreach ($appeals as $appeal)
            @php
                $borderColor = match ($appeal->status) {
                    'pending'  => 'border-l-amber-400 dark:border-l-amber-500',
                    'accepted' => 'border-l-emerald-500 dark:border-l-emerald-400',
                    'rejected' => 'border-l-red-500 dark:border-l-red-400',
                    default    => 'border-l-slate-300',
                };
            @endphp
            <div class="card border-l-4 {{ $borderColor }}">
                <div class="px-5 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap mb-1">
                                <span class="font-semibold text-slate-900 dark:text-white text-sm">
                                    {{ $appeal->penalty->employee?->name ?? '—' }}
                                </span>
                                <span class="text-xs text-slate-400 font-mono">{{ $appeal->penalty->employee?->code }}</span>
                                <span class="{{ $appeal->statusBadgeClass() }} text-xs">{{ $appeal->statusLabel() }}</span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mb-2">
                                Phiếu phạt:
                                <a href="{{ route('penalties.show', $appeal->penalty) }}"
                                   class="font-mono text-pcrm-600 dark:text-pcrm-400 hover:underline" target="_blank">
                                    {{ $appeal->penalty->code }}
                                </a>
                                · Vi phạm: <span class="font-medium">{{ $appeal->penalty->violation?->name ?? '—' }}</span>
                                · <span class="text-red-500">-{{ number_format($appeal->penalty->total_points_deducted) }}đ</span>
                            </p>
                            <div class="bg-slate-50 dark:bg-slate-700/40 rounded-lg px-3 py-2 text-sm text-slate-700 dark:text-slate-300">
                                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Nội dung khiếu nại</p>
                                {{ $appeal->reason }}
                            </div>
                            @if($appeal->reviewer_note)
                                <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                    <span class="font-semibold">Phản hồi:</span> {{ $appeal->reviewer_note }}
                                    @if($appeal->reviewer) · bởi <span class="font-medium">{{ $appeal->reviewer->name }}</span> @endif
                                </div>
                            @endif
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-xs text-slate-400">{{ $appeal->created_at->format('d/m/Y H:i') }}</p>
                            <p class="text-xs text-slate-400 mt-0.5">bởi {{ $appeal->appellant?->name }}</p>
                        </div>
                    </div>

                    @if ($appeal->status === 'pending')
                        @can('review-appeals')
                        <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-700 flex items-center gap-3"
                             onclick="event.stopPropagation()">
                            <form action="{{ route('appeals.accept', $appeal) }}" method="POST"
                                  onsubmit="return confirm('Xác nhận chấp nhận khiếu nại? Phiếu phạt {{ $appeal->penalty->code }} sẽ bị thu hồi và điểm sẽ được hoàn.')">
                                @csrf
                                <button type="submit" class="btn-primary btn-sm">
                                    <i class="bi bi-check-circle"></i> Chấp nhận & Thu hồi
                                </button>
                            </form>
                            <button type="button"
                                    onclick="openRejectAppealModal({{ $appeal->id }})"
                                    class="btn-danger btn-sm">
                                <i class="bi bi-x-circle"></i> Từ chối
                            </button>
                        </div>
                        @endcan
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if ($appeals->hasPages())
        <div class="mt-4">{{ $appeals->links() }}</div>
    @endif
@endif
@endsection

@push('modals')
{{-- Reject Appeal Modal --}}
<div id="rejectAppealModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
     onclick="if(event.target===this)closeModal('rejectAppealModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-x-circle text-red-500"></i> Từ chối khiếu nại
            </h3>
            <button onclick="closeModal('rejectAppealModal')"
                    class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form id="rejectAppealForm" action="" method="POST" class="px-5 py-4 space-y-4">
            @csrf
            <div>
                <label class="form-label">Lý do từ chối <span class="text-red-500">*</span></label>
                <textarea name="reviewer_note" class="form-input" rows="3"
                          placeholder="Nhập lý do từ chối khiếu nại..." required maxlength="500"></textarea>
            </div>
            <div class="flex gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('rejectAppealModal')" class="btn-secondary flex-1">Hủy</button>
                <button type="submit" class="btn-danger flex-1">
                    <i class="bi bi-x-circle"></i> Từ chối
                </button>
            </div>
        </form>
    </div>
</div>
@endpush

@push('scripts')
<script>
function openRejectAppealModal(id) {
    document.getElementById('rejectAppealForm').action = '/appeals/' + id + '/reject';
    openModal('rejectAppealModal');
}
</script>
@endpush
