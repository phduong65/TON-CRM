@extends('layouts.admin')

@section('title', 'Thưởng điểm')
@section('page-title', 'Thưởng điểm')
@section('breadcrumb', 'Thưởng phạt / Thưởng điểm')

@section('content')
    <div class="page-header">
        <div>
            <p class="page-subtitle">Nhấn vào phiếu để xem chi tiết và thực hiện duyệt</p>
        </div>
        @can('create-rewards')
        <button onclick="openModal('createRewardModal')" class="btn-primary">
            <i class="bi bi-plus-circle"></i>
            <span>Tạo phiếu thưởng</span>
        </button>
        @endcan
    </div>

    <div class="card">
        {{-- Filter bar --}}
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700">
            <form action="{{ route('rewards.index') }}" method="GET" class="flex flex-wrap gap-2 items-end">
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Tìm kiếm</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-input h-9 text-sm w-48"
                           placeholder="Tên NV, mã phiếu...">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Loại thưởng</label>
                    <select name="reward_type_id" class="form-input h-9 text-sm">
                        <option value="">Tất cả loại</option>
                        @foreach($rewardTypes as $rt)
                            <option value="{{ $rt->id }}" @selected(request('reward_type_id') == $rt->id)>
                                {{ $rt->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Trạng thái</label>
                    <select name="status" class="form-input h-9 text-sm">
                        <option value="">Tất cả</option>
                        <option value="pending"  @selected(request('status') === 'pending')>Chờ duyệt</option>
                        <option value="approved" @selected(request('status') === 'approved')>Đã duyệt</option>
                        <option value="rejected" @selected(request('status') === 'rejected')>Từ chối</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Từ ngày</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-input h-9 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Đến ngày</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-input h-9 text-sm">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="btn-primary h-9 px-4 text-sm">
                        <i class="bi bi-funnel text-xs"></i> Lọc
                    </button>
                    @if(request()->anyFilled(['search', 'status', 'reward_type_id', 'date_from', 'date_to']))
                    <a href="{{ route('rewards.index') }}" class="btn-secondary h-9 px-4 text-sm inline-flex items-center gap-1">
                        <i class="bi bi-x-circle text-xs"></i> Xóa lọc
                    </a>
                    @endif
                </div>
                <div class="ml-auto flex items-end">
                    <p class="text-xs text-slate-400 dark:text-slate-500">{{ $rewards->total() }} kết quả</p>
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-container border-0 rounded-none">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th class="table-th w-32">Mã phiếu</th>
                            <th class="table-th">Nhân viên</th>
                            <th class="table-th">Loại thưởng</th>
                            <th class="table-th text-center">Điểm thưởng</th>
                            <th class="table-th text-center">Thành viên thêm</th>
                            <th class="table-th text-center">Trạng thái</th>
                            <th class="table-th">Ngày tạo</th>
                            <th class="table-th text-center">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rewards as $reward)
                        <tr class="table-tr-hover">
                            <td class="table-td">
                                <a href="{{ route('rewards.show', $reward) }}"
                                   class="font-mono text-sm text-pcrm-600 dark:text-pcrm-400 hover:underline">
                                    {{ $reward->code }}
                                </a>
                            </td>
                            <td class="table-td">
                                <div class="font-medium text-sm">{{ $reward->employee?->name }}</div>
                                <div class="text-xs text-slate-400">{{ $reward->employee?->code }} · {{ $reward->employee?->branch?->name }}</div>
                            </td>
                            <td class="table-td text-sm text-slate-700 dark:text-slate-300">{{ $reward->rewardType?->name }}</td>
                            <td class="table-td text-center">
                                <span class="inline-flex items-center gap-1 font-semibold text-emerald-600 dark:text-emerald-400">
                                    <i class="bi bi-star-fill text-xs"></i>
                                    +{{ number_format($reward->total_points_awarded) }}
                                </span>
                            </td>
                            <td class="table-td text-center text-slate-500 dark:text-slate-400 text-sm">
                                {{ $reward->members->count() > 0 ? '+' . $reward->members->count() . ' NV' : '—' }}
                            </td>
                            <td class="table-td text-center">
                                @php
                                    $statusMap = [
                                        'pending'  => ['badge-warning', 'Chờ duyệt'],
                                        'approved' => ['badge-success', 'Đã duyệt'],
                                        'rejected' => ['badge-danger',  'Từ chối'],
                                    ];
                                    [$cls, $lbl] = $statusMap[$reward->status] ?? ['badge-neutral', $reward->status];
                                @endphp
                                <span class="{{ $cls }}">{{ $lbl }}</span>
                            </td>
                            <td class="table-td text-sm text-slate-500 dark:text-slate-400">
                                {{ $reward->created_at->format('d/m/Y') }}
                            </td>
                            <td class="table-td text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <a href="{{ route('rewards.show', $reward) }}"
                                       class="btn-ghost btn-sm" title="Chi tiết">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    @if($reward->status === 'pending')
                                        @can('delete-rewards')
                                        <button onclick="openDeleteRewardModal({{ $reward->id }}, '{{ $reward->code }}')"
                                                class="btn-ghost btn-sm text-red-600 dark:text-red-400" title="Xóa">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="table-td text-center py-8 text-slate-400">
                                <i class="bi bi-gift text-3xl mb-2 block opacity-40"></i>
                                <p>Chưa có phiếu thưởng nào. Hãy tạo phiếu thưởng đầu tiên!</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($rewards->hasPages())
        <div class="card-footer">
            {{ $rewards->links() }}
        </div>
        @endif
    </div>
@endsection

@push('modals')
    @include('rewards.partials.create-modal')
    @include('rewards.partials.delete-modal')
@endpush

@push('scripts')
<script>
const rewardTypeDefaults = @json($rewardTypeDefaults);

let rewardMemberIndex = {{ old('members') ? count(old('members')) : 0 }};

function addRewardMemberRow() {
    const idx = rewardMemberIndex++;
    const container = document.getElementById('rewardMembersContainer');
    const row = document.createElement('div');
    row.className = 'flex items-center gap-2 reward-member-row';
    row.innerHTML = `
        <select name="members[${idx}][employee_id]" class="form-input flex-1 text-sm">
            <option value="">-- Chọn nhân viên --</option>
            @foreach($employees as $emp)
            <option value="{{ $emp->id }}">{{ $emp->name }} ({{ $emp->code }})</option>
            @endforeach
        </select>
        <input type="number" name="members[${idx}][points_awarded]"
               class="form-input w-24 text-sm" value="10" min="0" placeholder="Điểm">
        <input type="text" name="members[${idx}][note]"
               class="form-input flex-1 text-sm" placeholder="Ghi chú...">
        <button type="button" onclick="this.closest('.reward-member-row').remove()"
                class="text-red-400 hover:text-red-600 shrink-0">
            <i class="bi bi-x-circle"></i>
        </button>
    `;
    container.appendChild(row);
}

document.getElementById('createRewardTypeId')?.addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    const pts = selected.dataset.points;
    if (pts !== undefined) {
        document.getElementById('createRewardPoints').value = pts;
    }
});

function openDeleteRewardModal(id, code) {
    document.getElementById('deleteRewardCode').textContent = code;
    document.getElementById('deleteRewardForm').action = '/rewards/' + id;
    openModal('deleteRewardModal');
}

@if($errors->any() && old('_modal'))
document.addEventListener('DOMContentLoaded', function() {
    openModal('{{ old("_modal") }}');
});
@endif
</script>
@endpush
