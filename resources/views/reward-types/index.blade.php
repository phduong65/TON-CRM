@extends('layouts.admin')

@section('title', 'Loại thưởng')
@section('page-title', 'Loại thưởng')
@section('breadcrumb', 'Thưởng phạt / Loại thưởng')

@section('content')
    <div class="page-header">
        <div>
            <p class="page-subtitle">Danh mục các loại khen thưởng điểm cho nhân viên</p>
        </div>
        @can('create-reward-types')
        <button onclick="openModal('createRewardTypeModal')" class="btn-primary">
            <i class="bi bi-plus-lg"></i>
            <span>Thêm loại thưởng</span>
        </button>
        @endcan
    </div>

    <div class="card">
        {{-- Filter bar --}}
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700">
            <form action="{{ route('reward-types.index') }}" method="GET" class="flex flex-wrap gap-2 items-end">
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Tìm kiếm</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                           class="form-input h-9 text-sm w-52" placeholder="Tên loại thưởng...">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Danh mục</label>
                    <select name="reward_category_id" class="form-input h-9 text-sm">
                        <option value="">Tất cả danh mục</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" @selected(request('reward_category_id') == $cat->id)>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Trạng thái</label>
                    <select name="status" class="form-input h-9 text-sm">
                        <option value="">Tất cả</option>
                        <option value="active"   @selected(request('status') === 'active')>Đang áp dụng</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Ngừng áp dụng</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="btn-primary h-9 px-4 text-sm">
                        <i class="bi bi-funnel text-xs"></i> Lọc
                    </button>
                    @if(request()->anyFilled(['search', 'status', 'reward_category_id']))
                    <a href="{{ route('reward-types.index') }}" class="btn-secondary h-9 px-4 text-sm inline-flex items-center gap-1">
                        <i class="bi bi-x-circle text-xs"></i> Xóa lọc
                    </a>
                    @endif
                </div>
                <div class="ml-auto flex items-end">
                    <p class="text-xs text-slate-400 dark:text-slate-500">{{ $rewardTypes->total() }} kết quả</p>
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-container border-0 rounded-none">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th class="table-th w-12">#</th>
                            <th class="table-th">Tên loại thưởng</th>
                            <th class="table-th">Danh mục</th>
                            <th class="table-th">Mô tả</th>
                            <th class="table-th text-center">Điểm mặc định</th>
                            <th class="table-th text-center">Số phiếu</th>
                            <th class="table-th text-center">Trạng thái</th>
                            @canany(['edit-reward-types', 'delete-reward-types'])
                            <th class="table-th text-center">Thao tác</th>
                            @endcanany
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rewardTypes as $index => $rt)
                        <tr class="table-tr-hover">
                            <td class="table-td text-slate-400 text-sm">{{ $rewardTypes->firstItem() + $index }}</td>
                            <td class="table-td font-medium">{{ $rt->name }}</td>
                            <td class="table-td text-sm">
                                @if($rt->category)
                                    <span class="badge badge-info">{{ $rt->category->name }}</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="table-td text-sm text-slate-500 dark:text-slate-400 max-w-xs truncate">
                                {{ $rt->description ?? '—' }}
                            </td>
                            <td class="table-td text-center">
                                <span class="inline-flex items-center gap-1 font-semibold text-emerald-600 dark:text-emerald-400">
                                    <i class="bi bi-star-fill text-xs"></i>
                                    {{ $rt->default_points }}
                                </span>
                            </td>
                            <td class="table-td text-center text-slate-600 dark:text-slate-300">{{ $rt->rewards_count }}</td>
                            <td class="table-td text-center">
                                @if($rt->is_active)
                                    <span class="badge-success">Đang áp dụng</span>
                                @else
                                    <span class="badge-neutral">Ngừng áp dụng</span>
                                @endif
                            </td>
                            @canany(['edit-reward-types', 'delete-reward-types'])
                            <td class="table-td text-center">
                                <div class="flex items-center justify-center gap-1">
                                    @can('edit-reward-types')
                                    <button onclick="openEditRewardTypeModal({{ $rt->id }}, '{{ addslashes($rt->name) }}', '{{ addslashes($rt->description ?? '') }}', {{ $rt->default_points }}, {{ $rt->is_active ? 'true' : 'false' }}, {{ $rt->reward_category_id ?? 'null' }})"
                                            class="btn-ghost btn-sm text-amber-600 dark:text-amber-400" title="Sửa">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    @endcan
                                    @can('delete-reward-types')
                                    <button onclick="openDeleteRewardTypeModal({{ $rt->id }}, '{{ addslashes($rt->name) }}')"
                                            class="btn-ghost btn-sm text-red-600 dark:text-red-400" title="Xóa">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    @endcan
                                </div>
                            </td>
                            @endcanany
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="table-td text-center py-8 text-slate-400">
                                <i class="bi bi-gift text-3xl mb-2 block opacity-40"></i>
                                <p>Chưa có loại thưởng nào. Hãy thêm loại thưởng đầu tiên!</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($rewardTypes->hasPages())
        <div class="card-footer">
            {{ $rewardTypes->links() }}
        </div>
        @endif
    </div>
@endsection

@push('modals')
    @include('reward-types.partials.create-modal')
    @include('reward-types.partials.edit-modal')
    @include('reward-types.partials.delete-modal')
@endpush

@push('scripts')
<script>
function openEditRewardTypeModal(id, name, description, defaultPoints, isActive, categoryId) {
    document.getElementById('editRewardTypeId').value = id;
    document.getElementById('editRewardTypeName').value = name;
    document.getElementById('editRewardTypeDescription').value = description;
    document.getElementById('editRewardTypeDefaultPoints').value = defaultPoints;
    document.getElementById('editRewardTypeIsActive').checked = isActive;
    document.getElementById('editRewardTypeCategoryId').value = categoryId ?? '';
    document.getElementById('editRewardTypeForm').action = '/reward-types/' + id;
    openModal('editRewardTypeModal');
}

function openDeleteRewardTypeModal(id, name) {
    document.getElementById('deleteRewardTypeName').textContent = name;
    document.getElementById('deleteRewardTypeForm').action = '/reward-types/' + id;
    openModal('deleteRewardTypeModal');
}

@if($errors->any() && old('_modal'))
document.addEventListener('DOMContentLoaded', function() {
    @if(old('_modal') === 'editRewardTypeModal')
    openEditRewardTypeModal(
        '{{ old("id") }}',
        '{{ old("name") }}',
        '{{ old("description") }}',
        '{{ old("default_points", 10) }}',
        {{ old('is_active') ? 'true' : 'false' }}
    );
    @else
    openModal('{{ old("_modal") }}');
    @endif
});
@endif
</script>
@endpush
