@extends('layouts.admin')

@section('title', 'Điểm chấm công')
@section('page-title', 'Điểm chấm công')
@section('breadcrumb', 'Ca làm việc & Chấm công')

@section('content')
    <div class="page-header">
        <div>
            <p class="page-subtitle">Toạ độ GPS + danh sách IP WiFi văn phòng dùng để xác thực chấm công</p>
        </div>
        @can('create-attendance-locations')
        <button onclick="openModal('createLocationModal')" class="btn-primary">
            <i class="bi bi-plus-lg"></i>
            <span>Thêm điểm chấm công</span>
        </button>
        @endcan
    </div>

    <div class="card">
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700">
            <form action="{{ route('attendance-locations.index') }}" method="GET" class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Chi nhánh</label>
                    <select name="branch_id" class="form-input h-9 text-sm w-full min-w-[180px]">
                        <option value="">Tất cả</option>
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}" @selected(request('branch_id') == $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn-primary h-9 px-4 text-sm gap-1.5">
                    <i class="bi bi-funnel text-xs"></i> Lọc
                </button>
                @if(request('branch_id'))
                <a href="{{ route('attendance-locations.index') }}" class="btn-secondary h-9 px-3 inline-flex items-center gap-1 text-sm">
                    <i class="bi bi-x text-sm"></i>
                </a>
                @endif
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-container border-0 rounded-none">
                <table class="table-base min-w-[900px]">
                    <thead>
                        <tr>
                            <th class="table-th">Tên điểm</th>
                            <th class="table-th">Chi nhánh</th>
                            <th class="table-th">Toạ độ</th>
                            <th class="table-th text-center">Bán kính</th>
                            <th class="table-th">IP văn phòng</th>
                            <th class="table-th text-center">Trạng thái</th>
                            <th class="table-th text-center">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($locations as $l)
                        <tr class="table-tr-hover">
                            <td class="table-td font-medium">{{ $l->name }}</td>
                            <td class="table-td text-slate-500 text-sm">{{ $l->branch?->name ?? '—' }}</td>
                            <td class="table-td text-xs font-mono">{{ $l->latitude }}, {{ $l->longitude }}</td>
                            <td class="table-td text-center text-sm">{{ $l->radius_meters }}m</td>
                            <td class="table-td text-xs font-mono text-slate-500">{{ implode(', ', $l->allowed_ips ?? []) ?: '—' }}</td>
                            <td class="table-td text-center">
                                @if($l->is_active)
                                    <span class="badge badge-success">Hoạt động</span>
                                @else
                                    <span class="badge badge-neutral">Ngừng</span>
                                @endif
                            </td>
                            <td class="table-td text-center">
                                <div class="flex items-center justify-center gap-1">
                                    @can('edit-attendance-locations')
                                    <button onclick='openEditLocationModal({{ json_encode([
                                        "id"=>$l->id,"branch_id"=>$l->branch_id,"name"=>$l->name,
                                        "latitude"=>$l->latitude,"longitude"=>$l->longitude,"radius_meters"=>$l->radius_meters,
                                        "allowed_ips"=>implode("\n", $l->allowed_ips ?? []),"is_active"=>$l->is_active,
                                    ]) }})'
                                            class="btn-ghost btn-sm text-amber-600 dark:text-amber-400" title="Sửa">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    @endcan
                                    @can('delete-attendance-locations')
                                    <button onclick="openDeleteLocationModal({{ $l->id }}, '{{ addslashes($l->name) }}')"
                                            class="btn-ghost btn-sm text-red-600 dark:text-red-400" title="Vô hiệu hóa">
                                        <i class="bi bi-slash-circle"></i>
                                    </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="table-td text-center py-8 text-slate-400">
                                <i class="bi bi-geo-alt text-3xl mb-2 block opacity-40"></i>
                                <p>Chưa có điểm chấm công nào</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($locations->hasPages())
        <div class="card-footer">
            {{ $locations->links() }}
        </div>
        @endif
    </div>
@endsection

@push('modals')
    @include('attendance-locations.partials.create-modal')
    @include('attendance-locations.partials.edit-modal')
    @include('attendance-locations.partials.delete-modal')
@endpush

@push('scripts')
<script>
function openEditLocationModal(data) {
    document.getElementById('editLocationBranch').value = data.branch_id ?? '';
    document.getElementById('editLocationName').value = data.name ?? '';
    document.getElementById('editLocationLat').value = data.latitude ?? '';
    document.getElementById('editLocationLng').value = data.longitude ?? '';
    document.getElementById('editLocationRadius').value = data.radius_meters ?? 100;
    document.getElementById('editLocationIps').value = data.allowed_ips ?? '';
    document.getElementById('editLocationActive').checked = !!data.is_active;
    document.getElementById('editLocationForm').action = '/attendance-locations/' + data.id;
    openModal('editLocationModal');
}
function openDeleteLocationModal(id, name) {
    document.getElementById('deleteLocationName').textContent = name;
    document.getElementById('deleteLocationForm').action = '/attendance-locations/' + id;
    openModal('deleteLocationModal');
}
</script>
@endpush
