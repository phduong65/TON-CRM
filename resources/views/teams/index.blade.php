@extends('layouts.admin')

@section('title', 'Đội nhóm')

@section('content')
    <div class="page-header">
        <div>
            <p class="page-subtitle">Danh sách tất cả đội nhóm trong hệ thống</p>
        </div>
        @can('create-teams')
        <button onclick="openModal('createTeamModal')" class="btn-primary">
            <i class="bi bi-plus-lg"></i>
            <span>Thêm đội nhóm</span>
        </button>
        @endcan
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-container border-0 rounded-none">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th class="table-th">Mã</th>
                            <th class="table-th">Tên đội nhóm</th>
                            <th class="table-th">Chi nhánh</th>
                            <th class="table-th text-center">Số nhân viên</th>
                            <th class="table-th text-right">Điểm TB</th>
                            <th class="table-th text-center">Trạng thái</th>
                            <th class="table-th text-center">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($teams as $t)
                        <tr class="table-tr-hover">
                            <td class="table-td font-mono text-xs">{{ $t->code ?? '—' }}</td>
                            <td class="table-td font-medium">{{ $t->name }}</td>
                            <td class="table-td">{{ $t->branch->name ?? '—' }}</td>
                            <td class="table-td text-center">{{ $t->employees_count ?? 0 }}</td>
                            <td class="table-td text-right font-semibold">{{ number_format($t->average_score ?? 0, 1) }}</td>
                            <td class="table-td text-center">
                                @if($t->is_active)
                                    <span class="badge badge-success">Hoạt động</span>
                                @else
                                    <span class="badge badge-neutral">Ngừng</span>
                                @endif
                            </td>
                            <td class="table-td text-center">
                                <div class="flex items-center justify-center gap-1">
                                    @can('edit-teams')
                                    <button onclick='openEditTeamModal({{ json_encode(["id"=>$t->id,"code"=>$t->code,"name"=>$t->name,"branch_id"=>$t->branch_id,"description"=>$t->description,"is_active"=>$t->is_active]) }})'
                                            class="btn-ghost btn-sm text-amber-600 dark:text-amber-400" title="Sửa">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    @endcan
                                    @can('delete-teams')
                                    <button onclick="openDeleteTeamModal({{ $t->id }}, '{{ addslashes($t->name) }}')"
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
                                <i class="bi bi-diagram-3 text-3xl mb-2 block opacity-40"></i>
                                <p>Chưa có đội nhóm nào</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($teams->hasPages())
        <div class="card-footer">
            {{ $teams->links() }}
        </div>
        @endif
    </div>

    @include('teams.partials.create-modal')
    @include('teams.partials.edit-modal')
    @include('teams.partials.delete-modal')
@endsection

@push('scripts')
<script>
function openEditTeamModal(data) {
    document.getElementById('editTeamId').value      = data.id;
    document.getElementById('editTeamCode').value    = data.code        ?? '';
    document.getElementById('editTeamName').value    = data.name        ?? '';
    document.getElementById('editTeamDesc').value    = data.description ?? '';
    document.getElementById('editTeamBranch').value  = data.branch_id   ?? '';
    document.getElementById('editTeamActive').checked = !!data.is_active;
    document.getElementById('editTeamForm').action   = '/teams/' + data.id;
    openModal('editTeamModal');
}
function openDeleteTeamModal(id, name) {
    document.getElementById('deleteTeamName').textContent = name;
    document.getElementById('deleteTeamForm').action = '/teams/' + id;
    openModal('deleteTeamModal');
}

@if($errors->any() && old('_modal'))
document.addEventListener('DOMContentLoaded', function() {
    @if(old('_modal') === 'editTeamModal')
    openEditTeamModal({
        id: '{{ old("_edit_id") }}',
        code: '{{ old("code") }}',
        name: '{{ old("name") }}',
        branch_id: '{{ old("branch_id") }}',
        description: '{{ old("description") }}',
        is_active: {{ old("is_active") ? "true" : "false" }}
    });
    @else
    openModal('{{ old("_modal") }}');
    @endif
});
@endif
</script>
@endpush
