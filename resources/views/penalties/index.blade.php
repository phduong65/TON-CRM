@extends('layouts.admin')

@section('title', 'Xử phạt')
@section('page-title', 'Xử phạt')
@section('breadcrumb', 'Kỷ luật')

@section('content')
    <div class="page-header">
        <div>
            <p class="page-subtitle">Nhấn vào phiếu để xem chi tiết và thực hiện duyệt</p>
        </div>
        @can('create-penalties')
            <button onclick="openModal('createPenaltyModal')" class="btn-primary">
                <i class="bi bi-plus-circle"></i>
                <span>Tạo xử phạt</span>
            </button>
        @endcan
    </div>

    {{-- Filter bar --}}
    <div class="card mb-4">
        <div class="px-4 py-3">
            <form action="{{ route('penalties.index') }}" method="GET" class="flex flex-wrap gap-2 items-end">
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Tìm kiếm</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-input h-9 text-sm w-48"
                        placeholder="Tên NV, mã phiếu...">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Trạng thái</label>
                    <select name="status" class="form-input h-9 text-sm">
                        <option value="">Tất cả</option>
                        <option value="pending" @selected(request('status') === 'pending')>Chờ duyệt</option>
                        <option value="approved" @selected(request('status') === 'approved')>Đã duyệt</option>
                        <option value="rejected" @selected(request('status') === 'rejected')>Từ chối</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Từ ngày</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}"
                        class="form-input h-9 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Đến ngày</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-input h-9 text-sm">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="btn-primary h-9 px-4 text-sm">
                        <i class="bi bi-funnel text-xs"></i> Lọc
                    </button>
                    @if (request()->anyFilled(['search', 'status', 'date_from', 'date_to']))
                        <a href="{{ route('penalties.index') }}"
                            class="btn-secondary h-9 px-4 text-sm inline-flex items-center gap-1">
                            <i class="bi bi-x-circle text-xs"></i> Xóa lọc
                        </a>
                    @endif
                </div>
                <div class="ml-auto flex items-end">
                    <p class="text-xs text-slate-400 dark:text-slate-500">{{ $penalties->total() }} kết quả</p>
                </div>
            </form>
        </div>
    </div>

    {{-- Penalty Cards --}}
    @if ($penalties->isEmpty())
        <div class="card">
            <div class="py-16 text-center text-slate-400 dark:text-slate-500">
                <i class="bi bi-gear text-4xl mb-3 block"></i>
                <p class="text-sm font-medium">Chưa có phiếu xử phạt nào</p>
                @can('create-penalties')
                    <button onclick="openModal('createPenaltyModal')"
                        class="mt-4 inline-flex items-center gap-1.5 text-sm text-pcrm-600 dark:text-pcrm-400 hover:underline">
                        <i class="bi bi-plus-circle"></i> Tạo phiếu đầu tiên
                    </button>
                @endcan
            </div>
        </div>
    @else
        <div class="space-y-2">
            @foreach ($penalties as $penalty)
                @php
                    $borderColor = match ($penalty->status) {
                        'pending' => 'border-l-amber-400 dark:border-l-amber-500',
                        'approved' => 'border-l-emerald-500 dark:border-l-emerald-400',
                        'rejected' => 'border-l-red-500 dark:border-l-red-400',
                        default => 'border-l-slate-300',
                    };
                    $dotColor = match ($penalty->status) {
                        'pending' => 'bg-amber-400',
                        'approved' => 'bg-emerald-500',
                        'rejected' => 'bg-red-500',
                        default => 'bg-slate-300',
                    };
                    $statusMap = [
                        'pending' => ['badge-warning', 'Chờ duyệt'],
                        'approved' => ['badge-success', 'Đã duyệt'],
                        'rejected' => ['badge-danger', 'Từ chối'],
                    ];
                    [$badgeCls, $badgeLbl] = $statusMap[$penalty->status] ?? ['badge-neutral', $penalty->status];
                    $penaltyMembers = $penalty->members
                        ->map(
                            fn($m) => [
                                'employee_id' => $m->employee_id,
                                'points_deducted' => $m->points_deducted,
                            ],
                        )
                        ->values()
                        ->toArray();
                    $penaltyAttachments = $penalty->attachments
                        ->map(
                            fn($a) => [
                                'id' => $a->id,
                                'filename' => $a->filename,
                                'type' => $a->type,
                                'url' => $a->url,
                            ],
                        )
                        ->values()
                        ->toArray();
                @endphp

                <div class="card border-l-4 {{ $borderColor }} cursor-pointer
                    hover:shadow-md hover:-translate-y-px transition-all duration-150 group"
                    onclick="openPenaltyDetail({{ $penalty->id }})">
                    <div class="px-5 py-4 flex items-start gap-4">

                        {{-- Status dot --}}
                        <div class="shrink-0 mt-1">
                            <div
                                class="w-2.5 h-2.5 rounded-full {{ $dotColor }}
                                @if ($penalty->status === 'pending') animate-pulse @endif">
                            </div>
                        </div>

                        {{-- Main content --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                <span class="font-semibold text-slate-900 dark:text-white text-sm">
                                    {{ $penalty->employee->name ?? 'N/A' }}
                                </span>
                                @if ($penalty->employee?->code)
                                    <span class="text-xs text-slate-400 dark:text-slate-500 font-mono">
                                        {{ $penalty->employee->code }}
                                    </span>
                                @endif
                                <span
                                    class="{{ $badgeCls }} text-xs bg-white text-[#6ee7b7] dark:text-[#6ee7b7] px-2.5 rounded-full font-medium sm:ml-0">{{ $badgeLbl }}</span>
                            </div>

                            <p class="text-sm text-slate-600 dark:text-slate-300 mb-0.5">
                                <i class="ph-warning-circle text-xs text-slate-400 mr-0.5"></i>
                                {{ $penalty->violation->name ?? 'N/A' }}
                            </p>

                            @if ($penalty->description)
                                <p class="text-xs text-slate-400 dark:text-slate-500 truncate mt-0.5">
                                    {{ $penalty->description }}
                                </p>
                            @endif
                        </div>

                        {{-- Right: points + date + actions --}}
                        <div class="shrink-0 text-right flex flex-col items-end gap-1.5">
                            <div class="flex items-center gap-1.5">
                                <span class="text-base font-bold text-red-600 dark:text-red-400">
                                    -{{ number_format($penalty->total_points_deducted) }}đ
                                </span>
                                @if ($penalty->total_money_deducted > 0)
                                    <span class="text-xs text-red-500 dark:text-red-400">
                                        / {{ number_format($penalty->total_money_deducted, 0, ',', '.') }}₫
                                    </span>
                                @endif
                            </div>

                            <span class="text-xs text-slate-400 dark:text-slate-500">
                                {{ $penalty->created_at->format('d/m/Y') }}
                            </span>

                            {{-- Quick actions (pending only, stop propagation) --}}
                            @if ($penalty->status === 'pending')
                                <div class="flex items-center gap-1  transition-opacity" onclick="event.stopPropagation()">
                                    @can('create-penalties')
                                        <button type="button" title="Sửa" data-ep-id="{{ $penalty->id }}"
                                            data-ep-violation="{{ $penalty->violation_id }}"
                                            data-ep-regulation="{{ $penalty->violation?->regulation_id ?? 0 }}"
                                            data-ep-employee="{{ $penalty->employee_id }}"
                                            data-ep-points="{{ $penalty->total_points_deducted }}"
                                            data-ep-money="{{ $penalty->total_money_deducted }}"
                                            data-ep-desc="{{ $penalty->description ?? '' }}"
                                            data-ep-members="{{ json_encode($penaltyMembers) }}"
                                            data-ep-attachments="{{ json_encode($penaltyAttachments) }}"
                                            onclick="epOpenFromBtn(this)"
                                            class="w-7 h-7 flex items-center justify-center rounded-md text-slate-400 hover:text-amber-500 hover:bg-amber-50 dark:hover:bg-amber-900/20 transition-colors">
                                            <i class="bi bi-pencil text-sm"></i>
                                        </button>
                                        <button type="button" title="Xóa"
                                            onclick="openDeletePenaltyModal({{ $penalty->id }}, '{{ $penalty->code ?? '#' . $penalty->id }}')"
                                            class="w-7 h-7 flex items-center justify-center rounded-md text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                                            <i class="bi bi bi-trash-fill text-sm"></i>
                                        </button>
                                    @endcan
                                </div>
                            @endif
                        </div>

                    </div>
                </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if ($penalties->hasPages())
            <div class="mt-4">
                {{ $penalties->links() }}
            </div>
        @endif
    @endif

@endsection

@push('modals')
    @include('penalties.partials.detail-modal')
    @include('penalties.partials.create-modal')
    @include('penalties.partials.edit-modal')
    @include('penalties.partials.delete-modal')
@endpush

@if ($errors->any())
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    openModal('{{ old('_modal', 'createPenaltyModal') }}');
});
</script>
@endpush
@endif
