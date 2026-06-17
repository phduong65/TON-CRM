@extends('layouts.admin')

@section('title', 'Danh mục thưởng')
@section('page-title', 'Danh mục thưởng')
@section('breadcrumb', 'Thưởng phạt / Danh mục thưởng')

@section('content')
    <div class="page-header">
        <div>
            <p class="page-subtitle">Danh mục nhóm loại thưởng — phân loại các hình thức khen thưởng</p>
        </div>
        @can('create-reward-categories')
        <button onclick="openModal('createRewardCategoryModal')" class="btn-primary">
            <i class="bi bi-plus-lg"></i>
            <span>Thêm danh mục</span>
        </button>
        @endcan
    </div>

    <div class="card">
        {{-- Filter bar --}}
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700">
            @php $rcFilterActive = request()->anyFilled(['search', 'status']); @endphp
            <form action="{{ route('reward-categories.index') }}" method="GET"
                  class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-end">
                <div class="relative min-w-0 sm:flex-1 sm:max-w-xs">
                    <i class="bi bi-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                           class="form-input pl-7 h-9 text-sm w-full" placeholder="Tên danh mục...">
                </div>
                <div class="grid grid-cols-2 gap-2 sm:contents">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Trạng thái</label>
                        <select name="status" class="form-input h-9 text-sm w-full">
                            <option value="">Tất cả</option>
                            <option value="active"   @selected(request('status') === 'active')>Đang áp dụng</option>
                            <option value="inactive" @selected(request('status') === 'inactive')>Tạm ngưng</option>
                        </select>
                    </div>
                    <div class="flex gap-2 items-end sm:hidden">
                        <button type="submit" class="btn-primary h-9 px-4 text-sm flex-1 gap-1">
                            <i class="bi bi-funnel text-xs"></i> Lọc
                        </button>
                        @if($rcFilterActive)
                        <a href="{{ route('reward-categories.index') }}" class="btn-secondary h-9 w-9 inline-flex items-center justify-center shrink-0">
                            <i class="bi bi-x text-sm"></i>
                        </a>
                        @endif
                    </div>
                </div>
                <div class="hidden sm:flex items-end gap-2">
                    <button type="submit" class="btn-primary h-9 px-4 text-sm gap-1.5">
                        <i class="bi bi-funnel text-xs"></i> Lọc
                    </button>
                    @if($rcFilterActive)
                    <a href="{{ route('reward-categories.index') }}" class="btn-secondary h-9 px-3 inline-flex items-center gap-1 text-sm">
                        <i class="bi bi-x text-sm"></i>
                    </a>
                    @endif
                    <span class="text-xs text-slate-400 dark:text-slate-500 ml-2">{{ $categories->total() }} kết quả</span>
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-container border-0 rounded-none">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th class="table-th">Tên danh mục</th>
                            <th class="table-th">Mô tả</th>
                            <th class="table-th text-center">Số loại thưởng</th>
                            <th class="table-th text-center">Trạng thái</th>
                            @canany(['edit-reward-categories', 'delete-reward-categories'])
                            <th class="table-th text-center">Thao tác</th>
                            @endcanany
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categories as $cat)
                        <tr class="table-tr-hover">
                            <td class="table-td font-medium">{{ $cat->name }}</td>
                            <td class="table-td text-sm text-slate-500 dark:text-slate-400 max-w-xs truncate">
                                {{ $cat->description ?? '—' }}
                            </td>
                            <td class="table-td text-center">
                                <a href="{{ route('reward-types.index', ['reward_category_id' => $cat->id]) }}"
                                   class="inline-flex items-center gap-1 text-pcrm-600 dark:text-pcrm-400 hover:underline font-semibold">
                                    {{ $cat->reward_types_count }}
                                    <i class="bi bi-arrow-right text-xs"></i>
                                </a>
                            </td>
                            <td class="table-td text-center">
                                @if($cat->is_active)
                                    <span class="badge badge-success">Đang áp dụng</span>
                                @else
                                    <span class="badge badge-neutral">Tạm ngưng</span>
                                @endif
                            </td>
                            @canany(['edit-reward-categories', 'delete-reward-categories'])
                            <td class="table-td text-center">
                                <div class="flex items-center justify-center gap-1">
                                    @can('edit-reward-categories')
                                    <button onclick='openEditRewardCategoryModal({{ json_encode([
                                        "id"          => $cat->id,
                                        "name"        => $cat->name,
                                        "description" => $cat->description,
                                        "is_active"   => (bool) $cat->is_active,
                                    ]) }})'
                                            class="btn-ghost btn-sm text-amber-600 dark:text-amber-400" title="Sửa">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    @endcan
                                    @can('delete-reward-categories')
                                    <button onclick="openDeleteRewardCategoryModal({{ $cat->id }}, '{{ addslashes($cat->name) }}')"
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
                            <td colspan="5" class="table-td text-center py-8 text-slate-400">
                                <i class="bi bi-folder text-3xl mb-2 block opacity-40"></i>
                                <p>Chưa có danh mục thưởng nào. Hãy thêm danh mục đầu tiên!</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($categories->hasPages())
        <div class="card-footer">
            {{ $categories->links() }}
        </div>
        @endif
    </div>
@endsection

@push('modals')
    @include('reward-categories.partials.create-modal')
    @include('reward-categories.partials.edit-modal')
    @include('reward-categories.partials.delete-modal')
@endpush

@push('scripts')
<script>
function openEditRewardCategoryModal(data) {
    document.getElementById('editRewardCategoryId').value          = data.id;
    document.getElementById('editRewardCategoryName').value        = data.name        ?? '';
    document.getElementById('editRewardCategoryDesc').value        = data.description ?? '';
    document.getElementById('editRewardCategoryActive').checked    = !!data.is_active;
    document.getElementById('editRewardCategoryForm').action       = '/reward-categories/' + data.id;
    openModal('editRewardCategoryModal');
}

function openDeleteRewardCategoryModal(id, name) {
    document.getElementById('deleteRewardCategoryName').textContent = name;
    document.getElementById('deleteRewardCategoryForm').action = '/reward-categories/' + id;
    openModal('deleteRewardCategoryModal');
}

@if($errors->any() && old('_modal'))
document.addEventListener('DOMContentLoaded', function() {
    @if(old('_modal') === 'editRewardCategoryModal')
    openEditRewardCategoryModal({
        id:          '{{ old("_edit_id") }}',
        name:        '{{ old("name") }}',
        description: '{{ old("description") }}',
        is_active:   {{ old('is_active') ? 'true' : 'false' }},
    });
    @else
    openModal('{{ old("_modal") }}');
    @endif
});
@endif
</script>
@endpush
