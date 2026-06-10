@extends('layouts.admin')

@section('title', 'Quản lý Nhân viên')
@section('page-title', 'Nhân viên')
@section('breadcrumb', 'Quản lý nhân sự')

@section('content')
    <div class="page-header">
        <div>
            <p class="page-subtitle">Danh sách tất cả nhân viên trong hệ thống</p>
        </div>
        @can('create-employees')
        <button onclick="openModal('createEmployeeModal')" class="btn-primary">
            <i class="bi bi-person-plus"></i>
            <span>Thêm nhân viên</span>
        </button>
        @endcan
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-container border-0 rounded-none">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th class="table-th">Mã NV</th>
                            <th class="table-th">Họ và tên</th>
                            <th class="table-th">Email</th>
                            <th class="table-th">Chi nhánh</th>
                            <th class="table-th">Đội nhóm</th>
                            <th class="table-th">Chức vụ</th>
                            <th class="table-th text-right">Điểm</th>
                            <th class="table-th text-center">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employees as $emp)
                        <tr class="table-tr-hover">
                            <td class="table-td font-mono text-xs">{{ $emp->code ?? '—' }}</td>
                            <td class="table-td font-medium">
                                <a href="{{ route('employees.show', $emp) }}" class="text-pcrm-600 dark:text-pcrm-400 hover:underline">
                                    {{ $emp->name }}
                                </a>
                            </td>
                            <td class="table-td text-slate-500">{{ $emp->email ?? '—' }}</td>
                            <td class="table-td">{{ $emp->branch->name ?? '—' }}</td>
                            <td class="table-td">{{ $emp->team->name ?? '—' }}</td>
                            <td class="table-td">{{ $emp->position ?? '—' }}</td>
                            <td class="table-td text-right font-semibold">{{ number_format($emp->total_score) }}</td>
                            <td class="table-td text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <a href="{{ route('employees.show', $emp) }}" class="btn-ghost btn-sm" title="Xem chi tiết">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('employees.penalties', $emp) }}" class="btn-ghost btn-sm" title="Lịch sử xử phạt">
                                        <i class="bi bi-file-earmark-text"></i>
                                    </a>
                                    @can('edit-employees')
                                    <button onclick='openEditEmployeeModal({{ json_encode(["id"=>$emp->id,"code"=>$emp->code,"name"=>$emp->name,"position"=>$emp->position,"email"=>$emp->email,"phone"=>$emp->phone,"branch_id"=>$emp->branch_id,"team_id"=>$emp->team_id,"joined_at"=>optional($emp->joined_at)->format("Y-m-d"),"is_active"=>$emp->is_active]) }})'
                                            class="btn-ghost btn-sm text-amber-600 dark:text-amber-400" title="Sửa">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    @endcan
                                    @can('delete-employees')
                                    <button onclick="openDeleteEmployeeModal({{ $emp->id }}, '{{ addslashes($emp->name) }}')"
                                            class="btn-ghost btn-sm text-red-600 dark:text-red-400" title="Vô hiệu hóa">
                                        <i class="bi bi-slash-circle"></i>
                                    </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="table-td text-center py-8 text-slate-400">
                                <i class="bi bi-person-x text-3xl mb-2 block opacity-40"></i>
                                <p>Chưa có nhân viên nào</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($employees->hasPages())
        <div class="card-footer">
            {{ $employees->links() }}
        </div>
        @endif
    </div>

    @include('employees.partials.create-modal')
    @include('employees.partials.edit-modal')
    @include('employees.partials.delete-modal')
@endsection

@push('scripts')
<script>
function openEditEmployeeModal(data) {
    document.getElementById('editEmpId').value       = data.id;
    document.getElementById('editEmpCode').value     = data.code       ?? '';
    document.getElementById('editEmpPosition').value = data.position   ?? '';
    document.getElementById('editEmpName').value     = data.name       ?? '';
    document.getElementById('editEmpEmail').value    = data.email      ?? '';
    document.getElementById('editEmpPhone').value    = data.phone      ?? '';
    document.getElementById('editEmpBranch').value   = data.branch_id  ?? '';
    document.getElementById('editEmpTeam').value     = data.team_id    ?? '';
    document.getElementById('editEmpJoined').value   = data.joined_at  ?? '';
    document.getElementById('editEmpActive').checked = !!data.is_active;
    document.getElementById('editEmployeeForm').action = '/employees/' + data.id;
    openModal('editEmployeeModal');
}
function openDeleteEmployeeModal(id, name) {
    document.getElementById('deleteEmployeeName').textContent = name;
    document.getElementById('deleteEmployeeForm').action = '/employees/' + id;
    openModal('deleteEmployeeModal');
}

@if($errors->any() && old('_modal'))
document.addEventListener('DOMContentLoaded', function() {
    @if(old('_modal') === 'editEmployeeModal')
    openEditEmployeeModal({
        id: '{{ old("_edit_id") }}',
        code: '{{ old("code") }}',
        name: '{{ old("name") }}',
        position: '{{ old("position") }}',
        email: '{{ old("email") }}',
        phone: '{{ old("phone") }}',
        branch_id: '{{ old("branch_id") }}',
        team_id: '{{ old("team_id") }}',
        joined_at: '{{ old("joined_at") }}',
        is_active: {{ old('is_active') ? 'true' : 'false' }}
    });
    @else
    openModal('{{ old("_modal") }}');
    @endif
});
@endif
</script>
@endpush
