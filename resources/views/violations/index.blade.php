@extends('layouts.admin')

@section('title', 'Danh mục vi phạm')
@section('page-title', 'Danh mục vi phạm')
@section('breadcrumb', 'Quy định')

@section('content')
    <div class="page-header">
        <div>
            <p class="page-subtitle">Danh sách các lỗi vi phạm theo quy chế</p>
        </div>
        @can('create-violations')
        <a href="#" class="btn-primary">
            <i class="ph-plus-circle"></i>
            <span>Thêm vi phạm</span>
        </a>
        @endcan
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-container border-0 rounded-none">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th class="table-th">Mã</th>
                            <th class="table-th">Tên vi phạm</th>
                            <th class="table-th">Quy chế</th>
                            <th class="table-th">Mức độ</th>
                            <th class="table-th">Loại</th>
                            <th class="table-th text-center">Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($violations as $v)
                        <tr class="table-tr-hover">
                            <td class="table-td font-mono text-xs">{{ $v->code ?? '—' }}</td>
                            <td class="table-td font-medium">{{ $v->name }}</td>
                            <td class="table-td">{{ $v->regulation->name ?? '—' }}</td>
                            <td class="table-td">
                                @php
                                    $sev = $v->severity ?? 'medium';
                                    $sc = ['low' => 'badge-info', 'medium' => 'badge-warning', 'high' => 'badge-danger', 'critical' => 'badge-danger'];
                                    $sl = ['low' => 'Nhẹ', 'medium' => 'Trung bình', 'high' => 'Nặng', 'critical' => 'Nghiêm trọng'];
                                    $cls = $sc[$sev] ?? 'badge-neutral';
                                    $lbl = $sl[$sev] ?? $sev;
                                @endphp
                                <span class="{{ $cls }}">{{ $lbl }}</span>
                            </td>
                            <td class="table-td">{{ $v->category ?? '—' }}</td>
                            <td class="table-td text-center">
                                @if($v->is_active ?? true)
                                    <span class="badge-success">Hoạt động</span>
                                @else
                                    <span class="badge-neutral">Ngừng</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="table-td text-center py-8 text-slate-400">
                                <i class="ph-book-open text-3xl mb-2 block"></i>
                                <p>Chưa có vi phạm nào</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($violations->hasPages())
        <div class="card-footer">
            {{ $violations->links() }}
        </div>
        @endif
    </div>
@endsection
