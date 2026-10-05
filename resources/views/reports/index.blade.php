@extends('layouts.admin')

@section('title', 'Báo cáo nhân viên')
@section('page-title', 'Báo cáo nhân viên')
@section('page-subtitle', 'Báo cáo vi phạm do nhân viên gửi — duyệt để cộng điểm cho người báo cáo và trừ điểm người vi phạm')
@section('breadcrumb', 'Kỷ luật / Báo cáo')

@section('page-actions')
    @can('create-reports')
        @if ($currentEmployee)
            <button onclick="openModal('createReportModal')" class="btn-primary">
                <i class="bi bi-flag"></i>
                <span>Tạo báo cáo</span>
            </button>
        @else
            <span class="text-xs italic text-slate-400">Cần liên kết tài khoản với nhân viên để tạo báo cáo</span>
        @endif
    @endcan
@endsection

@section('content')
    @php
        $statusMeta = [
            'pending'  => ['Chờ duyệt', 'badge-warning', 'bi-clock'],
            'approved' => ['Đã duyệt', 'badge-success', 'bi-check-circle-fill'],
            'rejected' => ['Từ chối', 'badge-danger', 'bi-x-circle-fill'],
        ];
        $currentStatus = request('status');
        $tabQuery = fn($status) => array_filter(
            array_merge(request()->except(['status', 'page']), ['status' => $status]),
            fn($v) => $v !== null && $v !== ''
        );
    @endphp

    <div class="card">
        <nav class="status-tabs" aria-label="Lọc theo trạng thái">
            <a href="{{ route('reports.index', $tabQuery(null)) }}"
               class="status-tab {{ !$currentStatus ? 'is-active' : '' }}" @if (!$currentStatus) aria-current="page" @endif>
                Tất cả <span class="status-tab-count">{{ number_format($statusCounts->sum()) }}</span>
            </a>
            @foreach ($statusMeta as $key => [$label])
                <a href="{{ route('reports.index', $tabQuery($key)) }}"
                   class="status-tab {{ $currentStatus === $key ? 'is-active' : '' }} {{ $key === 'pending' && $canApprove && ($statusCounts[$key] ?? 0) > 0 ? 'has-attention' : '' }}"
                   @if ($currentStatus === $key) aria-current="page" @endif>
                    {{ $label }} <span class="status-tab-count">{{ number_format($statusCounts[$key] ?? 0) }}</span>
                </a>
            @endforeach
        </nav>

        <x-table-toolbar :paginator="$reports" label="báo cáo">
            <x-slot:info>
                @if (request('search'))
                    <a href="{{ route('reports.index', request()->except(['search', 'page'])) }}" class="filter-chip" title="Bỏ bộ lọc này">
                        Tìm: "{{ request('search') }}" <i class="bi bi-x" aria-hidden="true"></i><span class="sr-only">Bỏ bộ lọc</span>
                    </a>
                @endif
            </x-slot:info>
            <form action="{{ route('reports.index') }}" method="GET" class="relative" role="search">
                @if ($currentStatus) <input type="hidden" name="status" value="{{ $currentStatus }}"> @endif
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none" aria-hidden="true"></i>
                <input type="search" name="search" value="{{ request('search') }}" class="form-input h-9 w-56 pl-8 text-sm"
                       placeholder="Mã báo cáo hoặc tên nhân viên…" aria-label="Tìm báo cáo">
            </form>
        </x-table-toolbar>

        <div class="card-body p-0">
            <div class="table-container border-0 rounded-none">
                <table class="table-base">
                    <caption class="sr-only">Danh sách báo cáo nhân viên</caption>
                    <thead>
                        <tr>
                            <th class="table-th lg:max-xl:hidden" scope="col">Mã báo cáo</th>
                            <th class="table-th min-w-[180px]" scope="col" data-mcard-title>Người bị báo cáo</th>
                            <th class="table-th" scope="col">Người báo cáo</th>
                            <th class="table-th" scope="col">Vi phạm</th>
                            <th class="table-th text-right" scope="col">Điểm</th>
                            <th class="table-th lg:max-xl:hidden" scope="col">Thời gian</th>
                            <th class="table-th" scope="col">Trạng thái</th>
                            <th class="table-th text-right" scope="col">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reports as $report)
                            @php
                                [$sLabel, $sBadge, $sIcon] = $statusMeta[$report->status] ?? [$report->statusLabel(), $report->statusBadgeClass(), 'bi-info-circle'];
                                $memberCount = $report->members->count();
                            @endphp
                            <tr class="table-tr-hover">
                                <td class="table-td whitespace-nowrap lg:max-xl:hidden">
                                    <a href="{{ route('reports.show', $report) }}" class="font-mono text-xs font-medium text-pcrm-600 dark:text-pcrm-400 hover:underline">{{ $report->code }}</a>
                                </td>
                                <td class="table-td">
                                    @if ($report->type === 'team')
                                        <div class="flex items-center gap-1.5 font-medium text-slate-900 dark:text-white">
                                            <i class="bi bi-people text-pcrm-500" aria-hidden="true"></i> {{ $report->team?->name ?? '—' }}
                                        </div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">Cả đội · {{ $memberCount }} nhân viên</div>
                                    @else
                                        <div class="font-medium text-slate-900 dark:text-white">{{ $report->reported?->name ?? '—' }}</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">
                                            {{ $report->reported?->branch?->name ?? '—' }}
                                            @if ($report->type === 'joint' && $memberCount > 0) · +{{ $memberCount }} người liên đới @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="table-td">
                                    @if ($canApprove)
                                        <div class="text-slate-800 dark:text-slate-200">{{ $report->reporter?->name ?? '—' }}</div>
                                        <div class="font-mono text-xs text-slate-400">{{ $report->reporter?->code }}</div>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-sm italic text-slate-400" title="Danh tính người báo cáo chỉ người duyệt thấy">
                                            <i class="bi bi-incognito" aria-hidden="true"></i> Ẩn danh
                                        </span>
                                    @endif
                                </td>
                                <td class="table-td text-sm">
                                    @if ($report->violation)
                                        <div class="max-w-xs lg:max-xl:max-w-[10rem] xl:max-2xl:max-w-[11rem] truncate text-slate-800 dark:text-slate-200" title="{{ $report->violation->name }}">{{ $report->violation->name }}</div>
                                    @else
                                        <span class="text-xs italic text-slate-400">Không chọn vi phạm</span>
                                    @endif
                                </td>
                                <td class="table-td text-right whitespace-nowrap">
                                    <span class="points-chip points-chip-plus" title="Điểm thưởng cho người báo cáo khi duyệt">+{{ $report->reward_points }}</span>
                                    @if (($report->violation?->points_deducted ?? 0) > 0)
                                        <span class="mt-1 block"><span class="points-chip" title="Điểm trừ mỗi người vi phạm khi duyệt">-{{ $report->violation->points_deducted }}</span></span>
                                    @endif
                                </td>
                                <td class="table-td whitespace-nowrap text-xs text-slate-500 dark:text-slate-400 lg:max-xl:hidden">
                                    <span class="block text-slate-700 dark:text-slate-300">{{ $report->created_at->format('d/m/Y') }}</span>
                                    @if ($report->status === 'pending')
                                        <span class="text-[#C98219] dark:text-amber-400">chờ {{ $report->created_at->diffForHumans(null, true) }}</span>
                                    @else
                                        {{ $report->created_at->format('H:i') }}
                                    @endif
                                </td>
                                <td class="table-td whitespace-nowrap">
                                    <span class="{{ $sBadge }}"><i class="bi {{ $sIcon }}" aria-hidden="true"></i> {{ $sLabel }}</span>
                                </td>
                                <td class="table-td text-right whitespace-nowrap">
                                    <div class="inline-flex items-center justify-end gap-0.5">
                                        <a href="{{ route('reports.show', $report) }}" class="row-action" title="Xem chi tiết" aria-label="Xem chi tiết báo cáo {{ $report->code }}">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @if ($report->status === 'pending' && $report->created_by === auth()->id())
                                            <button type="button" class="row-action row-action-danger" title="Huỷ báo cáo" aria-label="Huỷ báo cáo {{ $report->code }}"
                                                    onclick="openCancelReportModal({{ Illuminate\Support\Js::from(route('reports.destroy', $report)) }}, {{ Illuminate\Support\Js::from($report->code) }})">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="table-td py-16 text-center text-slate-400 dark:text-slate-500">
                                    <i class="bi bi-flag mb-3 block text-4xl" aria-hidden="true"></i>
                                    @if ($currentStatus || request('search'))
                                        <p class="text-sm font-medium text-slate-600 dark:text-slate-300">Không có báo cáo nào khớp bộ lọc</p>
                                        <a href="{{ route('reports.index') }}" class="mt-3 inline-flex items-center gap-1.5 text-sm text-pcrm-600 dark:text-pcrm-400 hover:underline">
                                            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Xoá bộ lọc
                                        </a>
                                    @else
                                        <p class="text-sm font-medium text-slate-600 dark:text-slate-300">Chưa có báo cáo nào</p>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($reports->hasPages())
            <div class="card-footer">{{ $reports->links() }}</div>
        @endif
    </div>
@endsection

@push('modals')
    @can('create-reports')
        @if ($currentEmployee)
            @include('reports.partials.create-modal')
        @endif
    @endcan
    @include('reports.partials.cancel-modal')
@endpush

@if ($errors->any())
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            openModal({{ Illuminate\Support\Js::from(old('_modal', 'createReportModal')) }});
        });
    </script>
    @endpush
@endif
