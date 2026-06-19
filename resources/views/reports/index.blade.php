@extends('layouts.admin')

@section('title', 'Báo cáo nhân viên')
@section('page-title', 'Báo cáo nhân viên')
@section('breadcrumb', 'Báo cáo')

@section('content')
    {{-- Page header --}}
    <div class="page-header">
        <div>
            @if(!$canApprove)
                <p class="page-subtitle">Các báo cáo bạn đã gửi</p>
            @endif
        </div>
        <div class="flex items-center gap-2">
            @can('create-reports')
                @if($currentEmployee)
                    <button onclick="openModal('createReportModal')" class="btn-primary">
                        <i class="bi bi-flag-fill"></i>
                        <span class="hidden sm:inline">Tạo báo cáo</span>
                    </button>
                @else
                    <span class="text-xs text-slate-400 italic">Cần liên kết tài khoản với nhân viên để tạo báo cáo</span>
                @endif
            @endcan
        </div>
    </div>

    <div class="card">
        {{-- Filter bar --}}
        @php $rptFilterActive = request()->anyFilled(['search', 'status']); @endphp
        <form action="{{ route('reports.index') }}" method="GET"
              class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 flex flex-col gap-2 sm:flex-row sm:items-center sm:flex-wrap">
            <div class="relative flex-1 min-w-0 sm:max-w-xs">
                <i class="bi bi-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Tìm mã, tên nhân viên..."
                       class="form-input pl-7 h-9 text-sm w-full">
            </div>
            <div class="grid grid-cols-2 gap-2 sm:contents">
                <div>
                    <select name="status" class="form-input h-9 text-sm w-full">
                        <option value="">Tất cả TT</option>
                        <option value="pending"  @selected(request('status') === 'pending')>Chờ duyệt</option>
                        <option value="approved" @selected(request('status') === 'approved')>Đã duyệt</option>
                        <option value="rejected" @selected(request('status') === 'rejected')>Từ chối</option>
                    </select>
                </div>
                <div class="flex gap-2 items-center sm:hidden">
                    <button type="submit" class="btn-primary h-9 px-4 text-sm flex-1 gap-1">
                        <i class="bi bi-funnel text-xs"></i> Lọc
                    </button>
                    @if($rptFilterActive)
                    <a href="{{ route('reports.index') }}" class="btn-secondary h-9 w-9 inline-flex items-center justify-center shrink-0">
                        <i class="bi bi-x text-sm"></i>
                    </a>
                    @endif
                </div>
            </div>
            <div class="hidden sm:flex gap-2 items-center">
                <button type="submit" class="btn-primary h-9 px-4 text-sm gap-1.5">
                    <i class="bi bi-funnel text-xs"></i> Lọc
                </button>
                @if($rptFilterActive)
                <a href="{{ route('reports.index') }}" class="btn-secondary h-9 px-3 inline-flex items-center gap-1 text-sm">
                    <i class="bi bi-x text-sm"></i>
                </a>
                @endif
                <span class="ml-2 text-xs text-slate-400 dark:text-slate-500">{{ $reports->total() }} báo cáo</span>
            </div>
        </form>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="table-base">
                <thead>
                    <tr class="border-b border-slate-100 dark:border-slate-700 text-slate-500 dark:text-slate-400 tracking-wider">
                        <th class="px-4 py-2.5 text-left table-th">Mã báo cáo</th>
                        <th class="px-4 py-2.5 text-left table-th">Người báo cáo</th>
                        <th class="px-4 py-2.5 text-left table-th">Nhân viên bị báo cáo</th>
                        <th class="px-4 py-2.5 text-left table-th">Vi phạm</th>
                        <th class="px-4 py-2.5 text-left table-th">Điểm thưởng</th>
                        <th class="px-4 py-2.5 text-left table-th">Trạng thái</th>
                        <th class="px-4 py-2.5 text-left table-th">Ngày tạo</th>
                        <th class="px-4 py-2.5 text-left table-th">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($reports as $report)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/30 transition-colors">
                            <td class="px-4 py-3">
                                <span class="font-mono text-xs font-medium text-pcrm-700 dark:text-pcrm-400">{{ $report->code }}</span>
                            </td>
                            <td class="px-4 py-3">
                                @if(auth()->user()->can('approve-reports'))
                                    <div class="font-medium text-slate-800 dark:text-slate-200">{{ $report->reporter?->name ?? '—' }}</div>
                                    <div class="text-xs text-slate-400 mt-1">{{ $report->reporter?->code }}</div>
                                @else
                                    <div class="font-medium text-slate-400 dark:text-slate-500 italic text-sm flex items-center gap-1">
                                        <i class="bi bi-incognito text-xs"></i> Ẩn danh
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-800 dark:text-slate-200">{{ $report->reported?->name ?? '—' }}</div>
                                <div class="text-xs text-slate-400 mt-1">{{ $report->reported?->branch?->name }}</div>
                            </td>
                            <td class="px-4 py-3">
                                @if($report->violation)
                                    <span class="text-xs text-red-600 dark:text-slate-400 font-semibold">{{ $report->violation->name }}</span>
                                @else
                                    <span class="text-xs text-slate-400 italic">Không chọn</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">+{{ $report->reward_points }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="{{ $report->statusBadgeClass() }} text-xs">{{ $report->statusLabel() }}</span>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-400 whitespace-nowrap">
                                {{ $report->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('reports.show', $report) }}"
                                   class="btn-ghost btn-sm text-xs">
                                    <i class="bi bi-eye"></i>
                                    <span>Xem</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-16 text-center">
                                <div class="w-14 h-14 rounded-full bg-slate-100 dark:bg-slate-700/60 flex items-center justify-center mx-auto mb-3">
                                    <i class="bi bi-flag text-xl text-slate-400 dark:text-slate-500"></i>
                                </div>
                                <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Không có báo cáo nào</p>
                                @if(request()->anyFilled(['search', 'status']))
                                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Thử xóa bộ lọc</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($reports->hasPages())
            <div class="card-footer">
                {{ $reports->links() }}
            </div>
        @endif
    </div>
@endsection

@can('create-reports')
    @if($currentEmployee)
        @push('modals')
            @include('reports.partials.create-modal')
        @endpush
    @endif
@endcan

@if($errors->any())
<script>
    document.addEventListener('DOMContentLoaded', function () {
        openModal('{{ old('_modal', 'createReportModal') }}');
    });
</script>
@endif
