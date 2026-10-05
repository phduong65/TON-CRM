@extends('layouts.admin')

@section('title', 'Định biên nhân sự theo khung giờ')

@section('content')
<div class="space-y-6">
    <!-- Breadcrumb & Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-1">
                <a href="{{ route('dashboard') }}" class="hover:text-indigo-600 dark:hover:text-indigo-400">Bảng điều khiển</a>
                <span>/</span>
                <a href="{{ route('shifts.index') }}" class="hover:text-indigo-600 dark:hover:text-indigo-400">Ca làm việc</a>
                <span>/</span>
                <span class="text-slate-800 dark:text-slate-200 font-medium">Định biên nhân sự</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-people-fill text-indigo-600 dark:text-indigo-400"></i>
                Định biên nhân sự theo khung giờ
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                Thiết lập số nhân sự tối thiểu và mục tiêu cần có mặt đồng thời theo chi nhánh, bộ phận và khung giờ.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('operational-schedule.index') }}"
                class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700 shadow-sm transition-all">
                <i class="bi bi-calendar2-range text-indigo-500"></i>
                Xem Lịch vận hành
            </a>

            @can('manage-shift-coverage')
            <button type="button" onclick="openModal('createRequirementModal')"
                class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 focus:ring-4 focus:ring-indigo-300 shadow-sm shadow-indigo-600/30 transition-all">
                <i class="bi bi-plus-lg"></i>
                Thêm quy tắc định biên
            </button>
            @endcan
        </div>
    </div>

    <!-- Filter Card -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200/80 dark:border-slate-700/80 p-4">
        <form method="GET" action="{{ route('shift-coverage-requirements.index') }}" class="grid grid-cols-1 sm:grid-cols-3 md:grid-cols-4 gap-3 items-end">
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Chi nhánh</label>
                <select name="branch_id" onchange="this.form.submit()"
                    class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white px-3 py-2 focus:ring-2 focus:ring-indigo-500">
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}" {{ $selectedBranchId == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Bộ phận</label>
                <select name="team_id" onchange="this.form.submit()"
                    class="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-white px-3 py-2 focus:ring-2 focus:ring-indigo-500">
                    <option value="">-- Tất cả bộ phận --</option>
                    @foreach($teams as $t)
                        <option value="{{ $t->id }}" {{ $selectedTeamId == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <a href="{{ route('shift-coverage-requirements.index') }}"
                    class="inline-flex items-center justify-center w-full px-3 py-2 text-xs font-medium text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-700 rounded-xl hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors">
                    <i class="bi bi-arrow-counterclockwise mr-1.5"></i> Đặt lại bộ lọc
                </a>
            </div>
        </form>
    </div>

    <!-- Requirements Table -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200/80 dark:border-slate-700/80 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 uppercase tracking-wider font-semibold">
                        <th class="px-4 py-3.5">Khung vận hành</th>
                        <th class="px-4 py-3.5">Bộ phận</th>
                        <th class="px-4 py-3.5">Khung giờ</th>
                        <th class="px-4 py-3.5">Ngày áp dụng</th>
                        <th class="px-4 py-3.5 text-center">Tối thiểu</th>
                        <th class="px-4 py-3.5 text-center">Mục tiêu</th>
                        <th class="px-4 py-3.5">Thời hạn hiệu lực</th>
                        <th class="px-4 py-3.5 text-center">Trạng thái</th>
                        <th class="px-4 py-3.5 text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($requirements as $req)
                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-700/40 transition-colors">
                            <td class="px-4 py-3 font-semibold text-slate-800 dark:text-white">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full {{ $req->is_active ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                    {{ $req->name }}
                                </div>
                                @if($req->shift)
                                    <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-0.5 ml-4">
                                        Mẫu: {{ $req->shift->name }}
                                    </p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-700 font-medium">
                                    {{ $req->team?->name }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-mono font-medium text-slate-700 dark:text-slate-300">
                                {{ substr($req->start_time, 0, 5) }} – {{ substr($req->end_time, 0, 5) }}
                                @if($req->isOvernight())
                                    <span class="ml-1 text-[10px] px-1.5 py-0.5 rounded bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300 font-sans">
                                        Qua đêm
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                                <span class="font-medium text-indigo-600 dark:text-indigo-400">
                                    {{ $req->daysLabel() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">
                                    {{ $req->minimum_staff }} người
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($req->target_staff)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300">
                                        {{ $req->target_staff }} người
                                    </span>
                                @else
                                    <span class="text-slate-400 text-xs">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400 text-[11px]">
                                <div>Từ: {{ $req->effective_from?->format('d/m/Y') }}</div>
                                <div>Đến: {{ $req->effective_until ? $req->effective_until->format('d/m/Y') : 'Không thời hạn' }}</div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($req->is_active)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">
                                        <i class="bi bi-check-circle-fill text-[10px]"></i> Kích hoạt
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400">
                                        Tạm dừng
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-1">
                                    @can('manage-shift-coverage')
                                    <button type="button"
                                        onclick="openEditModal({{ json_encode($req) }})"
                                        class="p-1.5 text-slate-500 hover:text-amber-600 dark:hover:text-amber-400 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
                                        title="Chỉnh sửa">
                                        <i class="bi bi-pencil-square text-sm"></i>
                                    </button>
                                    <button type="button"
                                        onclick="openDeleteModal({{ $req->id }}, '{{ addslashes($req->name) }}', '{{ route('shift-coverage-requirements.destroy', $req) }}')"
                                        class="p-1.5 text-slate-500 hover:text-red-600 dark:hover:text-red-400 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
                                        title="Xoá">
                                        <i class="bi bi-trash text-sm"></i>
                                    </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-slate-400 dark:text-slate-500">
                                <i class="bi bi-shield-slash text-3xl mb-2 block"></i>
                                Chưa có quy tắc định biên nào cho chi nhánh này.
                                @can('manage-shift-coverage')
                                <div class="mt-3">
                                    <button type="button" onclick="openModal('createRequirementModal')" class="text-xs text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">
                                        + Thêm quy tắc định biên đầu tiên
                                    </button>
                                </div>
                                @endcan
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($requirements->hasPages())
            <div class="px-4 py-3 border-t border-slate-100 dark:border-slate-700">
                {{ $requirements->links() }}
            </div>
        @endif
    </div>
</div>

{{-- Modals --}}
@include('shift-coverage-requirements.partials.create-modal')
@include('shift-coverage-requirements.partials.edit-modal')
@include('shift-coverage-requirements.partials.delete-modal')

@push('scripts')
<script>
function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('hidden');
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('hidden');
}

function selectDaysPreset(prefix, daysArray) {
    const selector = prefix === 'create' ? '.create-day-checkbox' : '.edit-day-checkbox';
    document.querySelectorAll(selector).forEach(cb => {
        cb.checked = daysArray.includes(parseInt(cb.value));
    });
}

function openEditModal(req) {
    document.getElementById('editRequirementId').value = req.id;
    document.getElementById('editRequirementForm').action = '/shift-coverage-requirements/' + req.id;
    document.getElementById('editBranchId').value = req.branch_id;
    document.getElementById('editTeamId').value = req.team_id;
    document.getElementById('editName').value = req.name;
    document.getElementById('editStartTime').value = req.start_time ? req.start_time.substring(0, 5) : '';
    document.getElementById('editEndTime').value = req.end_time ? req.end_time.substring(0, 5) : '';
    document.getElementById('editMinStaff').value = req.minimum_staff;
    document.getElementById('editTargetStaff').value = req.target_staff || '';
    document.getElementById('editEffectiveFrom').value = req.effective_from ? req.effective_from.substring(0, 10) : '';
    document.getElementById('editEffectiveUntil').value = req.effective_until ? req.effective_until.substring(0, 10) : '';
    document.getElementById('editShiftId').value = req.shift_id || '';
    document.getElementById('editIsActive').checked = !!req.is_active;

    // Reset and check days
    const activeDays = Array.isArray(req.days_of_week) ? req.days_of_week.map(d => parseInt(d)) : [];
    for (let i = 1; i <= 7; i++) {
        const cb = document.getElementById('edit_day_' + i);
        if (cb) cb.checked = activeDays.includes(i);
    }

    openModal('editRequirementModal');
}

function openDeleteModal(id, name, actionUrl) {
    document.getElementById('deleteRequirementName').textContent = name;
    document.getElementById('deleteRequirementForm').action = actionUrl;
    openModal('deleteRequirementModal');
}

// Tự động mở lại modal nếu có validation error
@if($errors->any())
document.addEventListener('DOMContentLoaded', function() {
    const modalId = '{{ old("_modal", "createRequirementModal") }}';
    openModal(modalId);
});
@endif
</script>
@endpush
@endsection
