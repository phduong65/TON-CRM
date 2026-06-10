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
        {{-- Filter bar --}}
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700">
            <form action="{{ route('violations.index') }}" method="GET" class="flex flex-wrap gap-2 items-end">
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Tìm kiếm</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                           class="form-input h-9 text-sm w-44" placeholder="Tên, mã vi phạm...">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Quy chế</label>
                    <select name="regulation_id" class="form-input h-9 text-sm">
                        <option value="">Tất cả quy chế</option>
                        @foreach($regulations as $reg)
                            <option value="{{ $reg->id }}" @selected(request('regulation_id') == $reg->id)>
                                {{ $reg->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Mức độ</label>
                    <select name="severity" class="form-input h-9 text-sm">
                        <option value="">Tất cả mức độ</option>
                        <option value="low"      @selected(request('severity') === 'low')>Nhẹ</option>
                        <option value="medium"   @selected(request('severity') === 'medium')>Trung bình</option>
                        <option value="high"     @selected(request('severity') === 'high')>Nặng</option>
                        <option value="critical" @selected(request('severity') === 'critical')>Nghiêm trọng</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Trạng thái</label>
                    <select name="status" class="form-input h-9 text-sm">
                        <option value="">Tất cả</option>
                        <option value="1" @selected(request('status') === '1')>Hoạt động</option>
                        <option value="0" @selected(request('status') === '0')>Ngừng hoạt động</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="btn-primary h-9 px-4 text-sm">
                        <i class="bi bi-funnel text-xs"></i> Lọc
                    </button>
                    @if(request()->anyFilled(['search', 'regulation_id', 'severity', 'status']))
                    <a href="{{ route('violations.index') }}" class="btn-secondary h-9 px-4 text-sm inline-flex items-center gap-1">
                        <i class="bi bi-x-circle text-xs"></i> Xóa lọc
                    </a>
                    @endif
                </div>
                <div class="ml-auto flex items-end">
                    <p class="text-xs text-slate-400 dark:text-slate-500">{{ $violations->total() }} kết quả</p>
                </div>
            </form>
        </div>

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
