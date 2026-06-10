@extends('layouts.admin')

@section('title', 'Xử phạt')
@section('page-title', 'Xử phạt')
@section('breadcrumb', 'Kỷ luật')

@section('content')
    <div class="page-header">
        <div>
            <p class="page-subtitle">Danh sách tất cả các phiếu xử phạt</p>
        </div>
        @can('create-penalties')
        <a href="#" class="btn-primary">
            <i class="ph-plus-circle"></i>
            <span>Tạo xử phạt</span>
        </a>
        @endcan
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-container border-0 rounded-none">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th class="table-th">Mã</th>
                            <th class="table-th">Nhân viên</th>
                            <th class="table-th">Lỗi vi phạm</th>
                            <th class="table-th text-right">Điểm trừ</th>
                            <th class="table-th text-right">Tiền phạt</th>
                            <th class="table-th">Trạng thái</th>
                            <th class="table-th">Ngày tạo</th>
                            <th class="table-th text-center">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($penalties as $penalty)
                        <tr class="table-tr-hover">
                            <td class="table-td font-mono text-xs">{{ $penalty->code ?? '#' . $penalty->id }}</td>
                            <td class="table-td">
                                <a href="{{ route('employees.show', $penalty->employee) }}" class="font-medium text-pcrm-600 dark:text-pcrm-400 hover:underline">
                                    {{ $penalty->employee->name ?? 'N/A' }}
                                </a>
                            </td>
                            <td class="table-td">{{ $penalty->violation->name ?? 'N/A' }}</td>
                            <td class="table-td text-right font-semibold text-red-600 dark:text-red-400">
                                -{{ number_format($penalty->total_points_deducted) }}
                            </td>
                            <td class="table-td text-right font-semibold text-red-600 dark:text-red-400">
                                @if($penalty->total_money_deducted > 0)
                                    {{ number_format($penalty->total_money_deducted, 0, ',', '.') }}₫
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="table-td">
                                @php
                                    $m = ['pending' => ['badge-warning', 'Chờ duyệt'], 'approved' => ['badge-success', 'Đã duyệt'], 'rejected' => ['badge-danger', 'Từ chối']];
                                    [$cls, $lbl] = $m[$penalty->status] ?? ['badge-neutral', $penalty->status];
                                @endphp
                                <span class="{{ $cls }}">{{ $lbl }}</span>
                            </td>
                            <td class="table-td text-sm text-slate-500">{{ $penalty->created_at->format('d/m/Y') }}</td>
                            <td class="table-td text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <a href="{{ route('penalties.show', $penalty) }}" class="btn-ghost btn-sm" title="Chi tiết">
                                        <i class="ph-eye"></i>
                                    </a>
                                    @can('approve-penalties')
                                        @if($penalty->status === 'pending')
                                        <form action="{{ route('penalties.approve', $penalty) }}" method="POST" class="inline" onsubmit="return confirm('Xác nhận duyệt xử phạt này?')">
                                            @csrf
                                            <button type="submit" class="btn-ghost btn-sm text-pcrm-600" title="Duyệt">
                                                <i class="ph-check-circle"></i>
                                            </button>
                                        </form>
                                        <button type="button" class="btn-ghost btn-sm text-red-600" title="Từ chối"
                                            onclick="showRejectModal({{ $penalty->id }})">
                                            <i class="ph-x-circle"></i>
                                        </button>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="table-td text-center py-8 text-slate-400">
                                <i class="ph-gavel text-3xl mb-2 block"></i>
                                <p>Chưa có xử phạt nào</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($penalties->hasPages())
        <div class="card-footer">
            {{ $penalties->links() }}
        </div>
        @endif
    </div>

    <!-- Reject Modal -->
    <div id="reject-modal" class="modal-overlay hidden">
        <div class="modal-content p-6">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Từ chối xử phạt</h3>
            <form id="reject-form" method="POST">
                @csrf
                <div class="mb-4">
                    <label for="rejected_reason" class="form-label">Lý do từ chối</label>
                    <textarea name="rejected_reason" id="rejected_reason" rows="3" class="form-input" required placeholder="Nhập lý do từ chối..."></textarea>
                </div>
                <div class="flex items-center justify-end gap-3">
                    <button type="button" class="btn-secondary" onclick="hideRejectModal()">Hủy</button>
                    <button type="submit" class="btn-danger">Xác nhận từ chối</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showRejectModal(penaltyId) {
            document.getElementById('reject-modal').classList.remove('hidden');
            document.getElementById('reject-form').action = '/penalties/' + penaltyId + '/reject';
        }
        function hideRejectModal() {
            document.getElementById('reject-modal').classList.add('hidden');
        }
    </script>
@endsection
