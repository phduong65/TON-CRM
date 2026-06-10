@extends('layouts.admin')

@section('title', 'Nhật ký hoạt động')
@section('page-title', 'Nhật ký hoạt động')
@section('breadcrumb', 'Hệ thống / Nhật ký')

@section('content')
    <div class="card">
        <div class="card-header">
            <p class="text-sm text-slate-500 dark:text-slate-400">Tất cả các thay đổi và hành động trong hệ thống</p>
        </div>
        <div class="card-body p-0">
            <div class="table-container border-0 rounded-none">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th class="table-th">Thời gian</th>
                            <th class="table-th">Người thực hiện</th>
                            <th class="table-th">Hành động</th>
                            <th class="table-th">Mô tả</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($activities as $log)
                        <tr class="table-tr-hover">
                            <td class="table-td text-sm text-slate-500 whitespace-nowrap">
                                {{ $log->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="table-td">
                                {{ $log->causer->name ?? 'Hệ thống' }}
                            </td>
                            <td class="table-td">
                                <span class="badge-info">{{ $log->log_name }}</span>
                            </td>
                            <td class="table-td text-slate-500 text-sm max-w-md truncate">
                                {{ $log->description }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="table-td text-center py-8 text-slate-400">
                                <i class="ph-clipboard-text text-3xl mb-2 block"></i>
                                <p>Chưa có hoạt động nào được ghi lại</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($activities->hasPages())
        <div class="card-footer">
            {{ $activities->links() }}
        </div>
        @endif
    </div>
@endsection
