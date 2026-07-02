@extends('layouts.admin')

@section('title', 'Ca làm việc')
@section('page-title', 'Ca làm việc')
@section('breadcrumb', 'Ca làm việc & Chấm công')

@section('content')
    <div class="page-header">
        <div>
            <p class="page-subtitle">Ca làm việc dùng để xếp ca cho nhân viên</p>
        </div>
        @can('create-shifts')
        <button onclick="openModal('createShiftModal')" class="btn-primary">
            <i class="bi bi-plus-lg"></i>
            <span>Thêm ca làm việc</span>
        </button>
        @endcan
    </div>

    <div class="card">
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700">
            @php $shiftFilterActive = request()->anyFilled(['search', 'work_mode', 'shift_type', 'branch_id']); @endphp
            <form action="{{ route('shifts.index') }}" method="GET"
                  class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-end">
                <div class="relative min-w-0 sm:flex-1 sm:max-w-xs">
                    <i class="bi bi-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                           class="form-input pl-7 h-9 text-sm w-full" placeholder="Tên, mã ca...">
                </div>
                <div class="grid grid-cols-2 gap-2 sm:contents">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Chi nhánh</label>
                        <select name="branch_id" class="form-input h-9 text-sm w-full">
                            <option value="">Tất cả</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->id }}" @selected(request('branch_id') == $b->id)>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Chế độ</label>
                        <select name="work_mode" class="form-input h-9 text-sm w-full">
                            <option value="">Tất cả</option>
                            <option value="onsite" @selected(request('work_mode') === 'onsite')>Tại chỗ</option>
                            <option value="wfh" @selected(request('work_mode') === 'wfh')>WFH</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Loại ca</label>
                        <select name="shift_type" class="form-input h-9 text-sm w-full">
                            <option value="">Tất cả</option>
                            <option value="fulltime" @selected(request('shift_type') === 'fulltime')>Full-time / Văn phòng</option>
                            <option value="parttime" @selected(request('shift_type') === 'parttime')>Part-time</option>
                        </select>
                    </div>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="btn-primary h-9 px-4 text-sm gap-1.5">
                        <i class="bi bi-funnel text-xs"></i> Lọc
                    </button>
                    @if($shiftFilterActive)
                    <a href="{{ route('shifts.index') }}" class="btn-secondary h-9 px-3 inline-flex items-center gap-1 text-sm">
                        <i class="bi bi-x text-sm"></i>
                    </a>
                    @endif
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-container border-0 rounded-none">
                <table class="table-base min-w-[1000px]">
                    <thead>
                        <tr>
                            <th class="table-th">Mã ca</th>
                            <th class="table-th">Tên ca</th>
                            <th class="table-th">Chi nhánh</th>
                            <th class="table-th">Giờ làm</th>
                            <th class="table-th text-center">Giờ công chuẩn</th>
                            <th class="table-th text-center">Cho phép trễ/sớm</th>
                            <th class="table-th text-center">Chế độ</th>
                            <th class="table-th text-center">Trạng thái</th>
                            <th class="table-th text-center">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($shifts as $s)
                        <tr class="table-tr-hover">
                            <td class="table-td font-mono text-xs">{{ $s->code }}</td>
                            <td class="table-td font-medium">
                                {{ $s->name }}
                                @if($s->is_overnight)<span class="badge badge-neutral ml-1" title="Ca qua đêm"><i class="bi bi-moon-stars"></i></span>@endif
                            </td>
                            <td class="table-td text-slate-500 text-sm">{{ $s->branch?->name ?? 'Mọi chi nhánh' }}</td>
                            <td class="table-td text-sm">{{ substr($s->start_time,0,5) }} – {{ substr($s->end_time,0,5) }}</td>
                            <td class="table-td text-center text-sm">{{ rtrim(rtrim(number_format($s->standard_work_hours, 2, '.', ''), '0'), '.') }}h/công</td>
                            <td class="table-td text-center text-sm">{{ $s->grace_late_minutes }}p / {{ $s->grace_early_minutes }}p</td>
                            <td class="table-td text-center">
                                @if($s->work_mode === 'wfh')
                                    <span class="badge badge-info">WFH</span>
                                @else
                                    <span class="badge badge-neutral">Tại chỗ</span>
                                @endif
                            </td>
                            <td class="table-td text-center">
                                @if($s->is_active)
                                    <span class="badge badge-success">Hoạt động</span>
                                @else
                                    <span class="badge badge-neutral">Ngừng</span>
                                @endif
                            </td>
                            <td class="table-td text-center">
                                <div class="flex items-center justify-center gap-1">
                                    @can('edit-shifts')
                                    <button onclick='openEditShiftModal({{ json_encode([
                                        "id"=>$s->id,"code"=>$s->code,"name"=>$s->name,"branch_id"=>$s->branch_id,
                                        "start_time"=>substr($s->start_time,0,5),"end_time"=>substr($s->end_time,0,5),
                                        "is_overnight"=>$s->is_overnight,"break_minutes"=>$s->break_minutes,
                                        "grace_late_minutes"=>$s->grace_late_minutes,"grace_early_minutes"=>$s->grace_early_minutes,
                                        "standard_work_hours"=>$s->standard_work_hours,"shift_type"=>$s->shift_type,
                                        "work_mode"=>$s->work_mode,"is_active"=>$s->is_active,
                                    ]) }})'
                                            class="btn-ghost btn-sm text-amber-600 dark:text-amber-400" title="Sửa">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    @endcan
                                    @can('delete-shifts')
                                    <button onclick="openDeleteShiftModal({{ $s->id }}, '{{ addslashes($s->name) }}')"
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
                                <i class="bi bi-clock-history text-3xl mb-2 block opacity-40"></i>
                                <p>Chưa có ca làm việc nào</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($shifts->hasPages())
        <div class="card-footer">
            {{ $shifts->links() }}
        </div>
        @endif
    </div>
@endsection

@push('modals')
    @include('shifts.partials.create-modal')
    @include('shifts.partials.edit-modal')
    @include('shifts.partials.delete-modal')
@endpush

@push('scripts')
<script>
function openEditShiftModal(data) {
    document.getElementById('editShiftCode').value = data.code ?? '';
    document.getElementById('editShiftName').value = data.name ?? '';
    document.getElementById('editShiftBranch').value = data.branch_id ?? '';
    document.getElementById('editShiftStart').value = data.start_time ?? '';
    document.getElementById('editShiftEnd').value = data.end_time ?? '';
    document.getElementById('editShiftOvernight').checked = !!data.is_overnight;
    document.getElementById('editShiftBreak').value = data.break_minutes ?? 0;
    document.getElementById('editShiftGraceLate').value = data.grace_late_minutes ?? 0;
    document.getElementById('editShiftGraceEarly').value = data.grace_early_minutes ?? 0;
    document.getElementById('editShiftStandardHours').value = data.standard_work_hours ?? 8;
    document.getElementById('editShiftType').value = data.shift_type ?? 'fulltime';
    document.getElementById('editShiftWorkMode').value = data.work_mode ?? 'onsite';
    document.getElementById('editShiftActive').checked = !!data.is_active;
    document.getElementById('editShiftForm').action = '/shifts/' + data.id;
    openModal('editShiftModal');
}
function openDeleteShiftModal(id, name) {
    document.getElementById('deleteShiftName').textContent = name;
    document.getElementById('deleteShiftForm').action = '/shifts/' + id;
    openModal('deleteShiftModal');
}
</script>
@endpush
