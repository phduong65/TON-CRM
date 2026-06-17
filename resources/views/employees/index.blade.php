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
        {{-- Filter bar --}}
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700">
            @php
                $empExtraKeys   = ['branch_id', 'team_id', 'status'];
                $empFilterActive = request()->anyFilled(array_merge(['search'], $empExtraKeys));
                $empExtraCount  = collect($empExtraKeys)->filter(fn($k) => request($k))->count();
            @endphp
            <form action="{{ route('employees.index') }}" method="GET">
                {{-- Top row: search + mobile toggle + desktop actions --}}
                <div class="flex gap-2 items-center">
                    <div class="relative flex-1 min-w-0">
                        <i class="bi bi-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                        <input type="text" name="search" value="{{ request('search') }}"
                               class="form-input pl-7 h-9 text-sm w-full" placeholder="Tên, mã NV, email...">
                    </div>
                    <button type="button" onclick="toggleEl('filterPanelEmployees')"
                            class="sm:hidden relative h-9 w-9 flex items-center justify-center rounded-lg border shrink-0 transition-colors
                                   {{ $empExtraCount > 0 ? 'border-pcrm-400 bg-pcrm-50 text-pcrm-700 dark:border-pcrm-600 dark:bg-pcrm-900/30 dark:text-pcrm-400' : 'border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-500 dark:text-slate-400' }}">
                        <i class="bi bi-funnel text-sm"></i>
                        @if($empExtraCount > 0)
                            <span class="absolute -top-1.5 -right-1.5 w-4 h-4 flex items-center justify-center rounded-full bg-pcrm-600 text-white text-[9px] font-bold">{{ $empExtraCount }}</span>
                        @endif
                    </button>
                    <button type="submit" class="hidden sm:inline-flex btn-primary h-9 px-4 text-sm gap-1.5 shrink-0">
                        <i class="bi bi-funnel text-xs"></i> Lọc
                    </button>
                    @if($empFilterActive)
                    <a href="{{ route('employees.index') }}" class="hidden sm:inline-flex btn-secondary h-9 px-3 text-sm items-center gap-1 shrink-0">
                        <i class="bi bi-x text-sm"></i>
                    </a>
                    @endif
                    <span class="hidden sm:block text-xs text-slate-400 dark:text-slate-500 ml-auto shrink-0">{{ $employees->total() }} kết quả</span>
                </div>
                {{-- Collapsible extra filters --}}
                <div id="filterPanelEmployees" class="filter-panel {{ $empExtraCount > 0 ? 'is-active' : '' }}">
                    <div class="grid grid-cols-2 gap-2 sm:contents">
                        <div>
                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Chi nhánh</label>
                            <select name="branch_id" class="form-input h-9 text-sm w-full">
                                <option value="">Tất cả CN</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected(request('branch_id') == $branch->id)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Đội nhóm</label>
                            <select name="team_id" class="form-input h-9 text-sm w-full">
                                <option value="">Tất cả đội</option>
                                @foreach($teams as $team)
                                    <option value="{{ $team->id }}" @selected(request('team_id') == $team->id)>{{ $team->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Trạng thái</label>
                            <select name="status" class="form-input h-9 text-sm w-full">
                                <option value="">Tất cả</option>
                                <option value="1" @selected(request('status') === '1')>Đang làm</option>
                                <option value="0" @selected(request('status') === '0')>Đã nghỉ</option>
                            </select>
                        </div>
                    </div>
                    <div class="filter-mobile-actions">
                        <button type="submit" class="btn-primary h-9 px-4 text-sm flex-1 gap-1">
                            <i class="bi bi-funnel text-xs"></i> Áp dụng
                        </button>
                        @if($empFilterActive)
                        <a href="{{ route('employees.index') }}" class="btn-secondary h-9 px-3 inline-flex items-center gap-1 text-sm shrink-0">
                            <i class="bi bi-x text-sm"></i> Xóa
                        </a>
                        @endif
                        <span class="ml-auto text-xs text-slate-400 dark:text-slate-500 shrink-0">{{ $employees->total() }}</span>
                    </div>
                </div>
            </form>
        </div>

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
                            <th class="table-th text-center">Trạng thái</th>
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
                            <td class="table-td text-center">
                                @if($emp->is_active)
                                    <span class="badge badge-success">Đang làm</span>
                                @else
                                    <span class="badge badge-neutral">Đã nghỉ</span>
                                @endif
                            </td>
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
                            <td colspan="9" class="table-td text-center py-8 text-slate-400">
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

@endsection

@push('modals')
    @include('employees.partials.create-modal')
    @include('employees.partials.edit-modal')
    @include('employees.partials.delete-modal')
@endpush

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
    const url = '/employees/' + id;
    document.getElementById('resignEmployeeForm').action = url;
    document.getElementById('deleteEmployeeForm').action = url;
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
