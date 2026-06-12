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
        <form action="{{ route('reports.index') }}" method="GET"
              class="px-4 py-2.5 border-b border-slate-100 dark:border-slate-700 flex items-center gap-2 flex-wrap">
            <div class="relative">
                <i class="bi bi-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Tìm mã, tên nhân viên..."
                       class="form-input pl-7 h-8 text-sm py-0 w-48">
            </div>
            <select name="status" class="form-input w-auto h-8 text-sm py-0" onchange="this.form.submit()">
                <option value="">Tất cả trạng thái</option>
                <option value="pending"  @selected(request('status') === 'pending')>Chờ duyệt</option>
                <option value="approved" @selected(request('status') === 'approved')>Đã duyệt</option>
                <option value="rejected" @selected(request('status') === 'rejected')>Từ chối</option>
            </select>
            <button type="submit" class="btn-secondary h-8 text-sm px-3">Lọc</button>
            @if(request()->anyFilled(['search', 'status']))
                <a href="{{ route('reports.index') }}"
                   class="inline-flex items-center gap-1 h-8 px-2.5 rounded-lg text-xs text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors border border-slate-200 dark:border-slate-600">
                    <i class="bi bi-x text-sm"></i> Xóa lọc
                </a>
            @endif
            <span class="ml-auto text-xs text-slate-400 dark:text-slate-500">{{ $reports->total() }} báo cáo</span>
        </form>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="table-auto w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 dark:border-slate-700 text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                        <th class="px-4 py-2.5 text-left font-medium">Mã báo cáo</th>
                        <th class="px-4 py-2.5 text-left font-medium">Người báo cáo</th>
                        <th class="px-4 py-2.5 text-left font-medium">Nhân viên bị báo cáo</th>
                        <th class="px-4 py-2.5 text-left font-medium">Vi phạm</th>
                        <th class="px-4 py-2.5 text-left font-medium">Điểm thưởng</th>
                        <th class="px-4 py-2.5 text-left font-medium">Trạng thái</th>
                        <th class="px-4 py-2.5 text-left font-medium">Ngày tạo</th>
                        <th class="px-4 py-2.5 text-left font-medium">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($reports as $report)
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/30 transition-colors">
                            <td class="px-4 py-3">
                                <span class="font-mono text-xs font-medium text-pcrm-700 dark:text-pcrm-400">{{ $report->code }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-800 dark:text-slate-200">{{ $report->reporter?->name ?? '—' }}</div>
                                <div class="text-xs text-slate-400">{{ $report->reporter?->code }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-800 dark:text-slate-200">{{ $report->reported?->name ?? '—' }}</div>
                                <div class="text-xs text-slate-400">{{ $report->reported?->branch?->name }}</div>
                            </td>
                            <td class="px-4 py-3">
                                @if($report->violation)
                                    <span class="text-xs text-slate-600 dark:text-slate-400">{{ $report->violation->name }}</span>
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
