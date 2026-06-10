@extends('layouts.admin')

@section('title', 'Lịch sử xử phạt — ' . $employee->name)
@section('page-title', 'Xử phạt: ' . $employee->name)
@section('breadcrumb', 'Nhân viên / Lịch sử xử phạt')

@section('content')
    <div class="card">
        <div class="card-body p-0">
            <div class="table-container border-0 rounded-none">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th class="table-th">Mã</th>
                            <th class="table-th">Lỗi vi phạm</th>
                            <th class="table-th">Mô tả</th>
                            <th class="table-th text-right">Điểm trừ</th>
                            <th class="table-th">Trạng thái</th>
                            <th class="table-th">Ngày tạo</th>
                            <th class="table-th text-center">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($penalties as $penalty)
                        <tr class="table-tr-hover">
                            <td class="table-td font-mono text-xs">{{ $penalty->code ?? '—' }}</td>
                            <td class="table-td font-medium">{{ $penalty->violation->name ?? 'N/A' }}</td>
                            <td class="table-td text-slate-500 max-w-xs truncate">{{ $penalty->description ?? '—' }}</td>
                            <td class="table-td text-right font-semibold text-red-600 dark:text-red-400">
                                -{{ number_format($penalty->total_points_deducted) }}
                            </td>
                            <td class="table-td">
                                @php
                                    $m = ['pending' => ['badge-warning', 'Chờ duyệt'], 'approved' => ['badge-success', 'Đã duyệt'], 'rejected' => ['badge-danger', 'Từ chối']];
                                    [$cls, $lbl] = $m[$penalty->status] ?? ['badge-neutral', $penalty->status];
                                @endphp
                                <span class="{{ $cls }}">{{ $lbl }}</span>
                            </td>
                            <td class="table-td text-sm text-slate-500">{{ $penalty->created_at->format('d/m/Y') }}</td>
                            <td class="table-td text-center">
                                <a href="{{ route('penalties.show', $penalty) }}" class="btn-ghost btn-sm">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="table-td text-center py-8 text-slate-400">
                                <i class="ph-check-circle text-3xl mb-2 block"></i>
                                <p>Nhân viên này chưa có vi phạm nào</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($penalties->hasPages())
        <div class="card-footer">
            {{ $penalties->links() }}
        </div>
        @endif
    </div>
@endsection
