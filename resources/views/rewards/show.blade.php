@extends('layouts.admin')

@section('title', 'Chi tiết phiếu thưởng')
@section('page-title', 'Phiếu thưởng ' . $reward->code)
@section('breadcrumb', 'Thưởng điểm / Chi tiết')

@section('content')
    <div class="mb-4">
        <a href="{{ route('rewards.index') }}" class="btn-secondary btn-sm">
            <i class="bi bi-arrow-left text-xs"></i> Quay lại
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main info -->
        <div class="lg:col-span-2 space-y-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="font-semibold text-slate-900 dark:text-white">Thông tin phiếu thưởng</h3>
                </div>
                <div class="card-body space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Mã phiếu</p>
                            <p class="font-mono text-sm font-medium">{{ $reward->code }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Trạng thái</p>
                            @php
                                $statusMap = [
                                    'pending'  => ['badge-warning', 'Chờ duyệt'],
                                    'approved' => ['badge-success', 'Đã duyệt'],
                                    'rejected' => ['badge-danger',  'Từ chối'],
                                ];
                                [$cls, $lbl] = $statusMap[$reward->status] ?? ['badge-neutral', $reward->status];
                            @endphp
                            <span class="{{ $cls }} text-sm mt-1 inline-block font-medium">{{ $lbl }}</span>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Nhân viên</p>
                            <a href="{{ route('employees.show', $reward->employee) }}"
                               class="text-sm font-medium text-pcrm-600 dark:text-pcrm-400 hover:underline">
                                {{ $reward->employee?->name }}
                            </a>
                            <p class="text-xs text-slate-400">{{ $reward->employee?->code }} · {{ $reward->employee?->branch?->name }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Loại thưởng</p>
                            <p class="text-sm font-medium">{{ $reward->rewardType?->name }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Điểm thưởng</p>
                            <p class="text-lg font-bold text-emerald-600 dark:text-emerald-400">
                                +{{ number_format($reward->total_points_awarded) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Ngày tạo</p>
                            <p class="text-sm">{{ $reward->created_at->format('d/m/Y H:i') }}</p>
                            <p class="text-xs text-slate-400">bởi {{ $reward->creator?->name }}</p>
                        </div>
                        @if($reward->description)
                        <div class="col-span-2">
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Lý do / Mô tả</p>
                            <p class="text-sm text-slate-700 dark:text-slate-300">{{ $reward->description }}</p>
                        </div>
                        @endif
                        @if($reward->approved_at)
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Ngày duyệt</p>
                            <p class="text-sm">{{ $reward->approved_at->format('d/m/Y H:i') }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Người duyệt</p>
                            <p class="text-sm font-medium">{{ $reward->approver?->name }}</p>
                        </div>
                        @endif
                        @if($reward->rejected_reason)
                        <div class="col-span-2">
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Lý do từ chối</p>
                            <p class="text-sm text-red-600 dark:text-red-400">{{ $reward->rejected_reason }}</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-4">
            {{-- Members card --}}
            <div class="card">
                <div class="card-header">
                    <h4 class="font-semibold text-slate-900 dark:text-white">Thành viên thưởng thêm</h4>
                </div>
                <div class="card-body p-0">
                    @if($reward->members->count() > 0)
                        <div class="divide-y divide-slate-100 dark:divide-slate-700">
                            @foreach($reward->members as $member)
                            <div class="px-4 py-3 flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium">{{ $member->employee?->name }}</p>
                                    <p class="text-xs text-slate-400">{{ $member->employee?->code }}</p>
                                </div>
                                <span class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                                    +{{ number_format($member->points_awarded) }}
                                </span>
                            </div>
                            @endforeach
                            <div class="px-4 py-3 flex items-center justify-between bg-slate-50 dark:bg-slate-700/30">
                                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tổng cộng</span>
                                <span class="text-sm font-bold text-emerald-600 dark:text-emerald-400">
                                    +{{ number_format($reward->total_points_awarded + $reward->members->sum('points_awarded')) }}
                                </span>
                            </div>
                        </div>
                    @else
                        <div class="p-4 text-center text-sm text-slate-400">
                            Không có thành viên thêm
                        </div>
                    @endif
                </div>
            </div>

            {{-- Action card --}}
            @if($reward->status === 'pending')
            @canany(['approve-rewards', 'create-rewards'])
            <div class="card">
                <div class="card-body space-y-2">
                    @can('approve-rewards')
                    <form action="{{ route('rewards.approve', $reward) }}" method="POST"
                          onsubmit="return confirm('Xác nhận duyệt phiếu thưởng {{ $reward->code }}?')">
                        @csrf
                        <button type="submit" class="btn-primary w-full">
                            <i class="bi bi-check-circle"></i>
                            <span>Duyệt phiếu thưởng</span>
                        </button>
                    </form>
                    <button type="button" onclick="openModal('rejectRewardModal')" class="btn-danger w-full">
                        <i class="bi bi-x-circle"></i>
                        <span>Từ chối</span>
                    </button>
                    @endcan
                    @can('create-rewards')
                    <button onclick="openModal('editRewardModal')" class="btn-secondary w-full">
                        <i class="bi bi-pencil"></i>
                        <span>Chỉnh sửa phiếu</span>
                    </button>
                    @endcan
                </div>
            </div>
            @endcanany
            @endif
        </div>
    </div>
@endsection

@push('modals')
{{-- Reject Modal --}}
<div id="rejectRewardModal" class="hidden fixed inset-0 bg-black/50 z-50 items-center justify-center p-4"
     onclick="if(event.target===this)closeModal('rejectRewardModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-x-circle text-red-500"></i> Từ chối phiếu thưởng
            </h3>
            <button onclick="closeModal('rejectRewardModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form action="{{ route('rewards.reject', $reward) }}" method="POST" class="px-6 py-5 space-y-4">
            @csrf
            <div>
                <label class="form-label">Lý do từ chối <span class="text-red-500">*</span></label>
                <textarea name="rejected_reason" class="form-input" rows="3"
                          placeholder="Nhập lý do từ chối..." required maxlength="500"></textarea>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('rejectRewardModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-danger"><i class="bi bi-x-circle"></i> Xác nhận từ chối</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Modal --}}
<div id="editRewardModal" class="hidden fixed inset-0 bg-black/50 z-50 items-center justify-center p-4"
     onclick="if(event.target===this)closeModal('editRewardModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-xl max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700 shrink-0">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-pencil text-pcrm-600"></i> Chỉnh sửa phiếu thưởng
            </h3>
            <button onclick="closeModal('editRewardModal')" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form action="{{ route('rewards.update', $reward) }}" method="POST" class="px-6 py-5 space-y-4 overflow-y-auto">
            @csrf
            @method('PUT')
            <input type="hidden" name="_modal" value="editRewardModal">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Loại thưởng <span class="text-red-500">*</span></label>
                    <select name="reward_type_id" class="form-input" required>
                        @foreach(\App\Models\RewardType::active()->orderBy('name')->get() as $rt)
                            <option value="{{ $rt->id }}" {{ $reward->reward_type_id == $rt->id ? 'selected' : '' }}>
                                {{ $rt->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Điểm thưởng <span class="text-red-500">*</span></label>
                    <input type="number" name="total_points_awarded" class="form-input"
                           value="{{ old('total_points_awarded', $reward->total_points_awarded) }}" min="1" required>
                </div>
            </div>

            <div>
                <label class="form-label">Nhân viên <span class="text-red-500">*</span></label>
                <select name="employee_id" class="form-input" required>
                    @foreach(\App\Models\Employee::where('is_active', true)->with('branch')->orderBy('name')->get() as $emp)
                        <option value="{{ $emp->id }}" {{ $reward->employee_id == $emp->id ? 'selected' : '' }}>
                            {{ $emp->name }} ({{ $emp->code }}) — {{ $emp->branch?->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label">Lý do / Mô tả</label>
                <textarea name="description" class="form-input" rows="2">{{ old('description', $reward->description) }}</textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('editRewardModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-primary"><i class="bi bi-floppy"></i> Cập nhật</button>
            </div>
        </form>
    </div>
</div>
@endpush

@push('scripts')
<script>
@if($errors->any() && old('_modal'))
document.addEventListener('DOMContentLoaded', function() {
    openModal('{{ old("_modal") }}');
});
@endif
</script>
@endpush
