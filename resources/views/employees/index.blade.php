@extends('layouts.admin')

@section('title', 'Quản lý Nhân viên')
@section('page-title', 'Nhân viên')
@section('page-subtitle', 'Danh sách nhân viên theo chi nhánh, đội nhóm và trạng thái')
@section('breadcrumb', 'Quản lý nhân sự')

@section('page-actions')
    @can('create-employees')
    <button onclick="openModal('createEmployeeModal')" class="btn-primary h-9 text-xs font-bold gap-1.5">
        <i class="bi bi-person-plus"></i>
        <span>Thêm nhân viên</span>
    </button>
    @endcan
@endsection

@section('content')
    <div class="card">
        <x-table-toolbar :paginator="$employees">
            <button onclick="toggleFilterDrawer(true)" class="btn-secondary h-9 px-3 gap-1.5 text-xs font-black relative">
                <i class="bi bi-funnel"></i>
                <span>Bộ lọc</span>
                @if(request()->anyFilled(['search', 'branch_id', 'team_id', 'status']))
                    <span class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-pcrm-600 rounded-full animate-pulse"></span>
                @endif
            </button>
        </x-table-toolbar>

        <div class="card-body p-0">
            <div class="table-container border-0 rounded-none">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th class="table-th">Mã NV</th>
                            <th class="table-th" data-mcard-title>Họ và tên</th>
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
                                <div class="flex items-center gap-2.5">
                                    <x-employee-avatar :employee="$emp" size="w-8 h-8" />
                                    <a href="{{ route('employees.show', $emp) }}" class="text-pcrm-600 dark:text-pcrm-400 hover:underline">
                                        {{ $emp->name }}
                                    </a>
                                </div>
                            </td>
                            <td class="table-td text-slate-500">{{ $emp->email ?? '—' }}</td>
                            <td class="table-td">{{ $emp->branch->name ?? '—' }}</td>
                            <td class="table-td">{{ $emp->team->name ?? '—' }}</td>
                            <td class="table-td">{{ $emp->position?->name ?? '—' }}</td>
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
                                    <button onclick='openEditEmployeeModal({{ json_encode(["id"=>$emp->id,"code"=>$emp->code,"name"=>$emp->name,"position_id"=>$emp->position_id,"email"=>$emp->email,"phone"=>$emp->phone,"branch_id"=>$emp->branch_id,"team_id"=>$emp->team_id,"joined_at"=>optional($emp->joined_at)->format("Y-m-d"),"is_active"=>$emp->is_active,"employment_type"=>$emp->employment_type,"is_office"=>$emp->is_office]) }})'
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

    <!-- Overlay for filter drawer -->
    <div id="filterDrawerOverlay" class="fixed inset-0 bg-black/40 z-40 hidden opacity-0 transition-opacity duration-300 pointer-events-none" onclick="toggleFilterDrawer(false)"></div>

    <!-- Right-Side Filter Drawer -->
    <aside id="filterDrawer" class="fixed inset-y-0 right-0 z-50 w-full max-w-xs sm:max-w-sm bg-white dark:bg-slate-900 border-l border-slate-200 dark:border-slate-800 shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between flex-shrink-0">
            <h3 class="text-sm font-black text-slate-855 dark:text-slate-100 uppercase tracking-wide flex items-center gap-1.5">
                <i class="bi bi-funnel text-pcrm-600"></i> Bộ lọc tìm kiếm
            </h3>
            <button type="button" onclick="toggleFilterDrawer(false)" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-750 transition-colors">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form action="{{ route('employees.index') }}" method="GET" class="flex-1 flex flex-col overflow-y-auto">
            <div class="p-5 space-y-4 flex-1">
                <div>
                    <label class="block text-xs font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wider mb-2">Tìm kiếm</label>
                    <div class="relative w-full">
                        <i class="bi bi-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                        <input type="text" name="search" value="{{ request('search') }}" class="form-input pl-7 text-sm w-full" placeholder="Tên, mã NV, email...">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wider mb-2">Chi nhánh</label>
                    <select name="branch_id" class="form-input text-sm w-full">
                        <option value="">Tất cả</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(request('branch_id') == $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wider mb-2">Đội nhóm</label>
                    <select name="team_id" class="form-input text-sm w-full">
                        <option value="">Tất cả</option>
                        @foreach($teams as $team)
                            <option value="{{ $team->id }}" @selected(request('team_id') == $team->id)>{{ $team->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-450 dark:text-slate-500 uppercase tracking-wider mb-2">Trạng thái</label>
                    <select name="status" class="form-input text-sm w-full">
                        <option value="">Tất cả</option>
                        <option value="1" @selected(request('status') === '1')>Đang làm</option>
                        <option value="0" @selected(request('status') === '0')>Đã nghỉ</option>
                    </select>
                </div>
            </div>
            <div class="p-5 border-t border-slate-100 dark:border-slate-800/80 flex items-center gap-3 bg-slate-50/50 dark:bg-slate-950/20 flex-shrink-0">
                <button type="button" onclick="resetFilters()" class="btn-secondary flex-1 py-2.5 px-3 text-xs font-bold">Đặt lại</button>
                <button type="submit" class="btn-primary flex-1 py-2.5 px-3 text-xs font-bold">Áp dụng</button>
            </div>
        </form>
    </aside>
@endpush

@push('scripts')
<script>
function openEditEmployeeModal(data) {
    document.getElementById('editEmpId').value       = data.id;
    document.getElementById('editEmpCode').value     = data.code       ?? '';
    document.getElementById('editEmpPosition').value = data.position_id ?? '';
    document.getElementById('editEmpName').value     = data.name       ?? '';
    document.getElementById('editEmpEmail').value    = data.email      ?? '';
    document.getElementById('editEmpPhone').value    = data.phone      ?? '';
    document.getElementById('editEmpBranch').value   = data.branch_id  ?? '';
    document.getElementById('editEmpTeam').value     = data.team_id    ?? '';
    document.getElementById('editEmpJoined').value   = data.joined_at  ?? '';
    document.getElementById('editEmpActive').checked = !!data.is_active;
    document.getElementById('editEmpEmploymentType').value = data.employment_type ?? 'full_time';
    document.getElementById('editEmpOffice').checked = !!data.is_office;
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
        position_id: '{{ old("position_id") }}',
        email: '{{ old("email") }}',
        phone: '{{ old("phone") }}',
        branch_id: '{{ old("branch_id") }}',
        team_id: '{{ old("team_id") }}',
        joined_at: '{{ old("joined_at") }}',
        is_active: {{ old('is_active') ? 'true' : 'false' }},
        employment_type: '{{ old("employment_type", "full_time") }}',
        is_office: {{ old('is_office') ? 'true' : 'false' }}
    });
    @else
    openModal('{{ old("_modal") }}');
    @endif
});
@endif
function toggleFilterDrawer(open) {
    const drawer = document.getElementById('filterDrawer');
    const overlay = document.getElementById('filterDrawerOverlay');
    if (open) {
        overlay.classList.remove('hidden');
        overlay.offsetHeight; // trigger reflow
        overlay.classList.add('opacity-100', 'pointer-events-auto');
        drawer.classList.remove('translate-x-full');
    } else {
        overlay.classList.remove('opacity-100', 'pointer-events-auto');
        drawer.classList.add('translate-x-full');
        setTimeout(() => {
            if (drawer.classList.contains('translate-x-full')) {
                overlay.classList.add('hidden');
            }
        }, 300);
    }
}

function resetFilters() {
    const drawer = document.getElementById('filterDrawer');
    const inputs = drawer.querySelectorAll('input, select');
    inputs.forEach(input => {
        if (input.type === 'text' || input.type === 'date' || input.type === 'hidden') {
            input.value = '';
        } else if (input.tagName === 'SELECT') {
            input.selectedIndex = 0;
        }
    });
    toggleFilterDrawer(false);
    window.location.href = "{{ route('employees.index') }}";
}
</script>
@endpush
