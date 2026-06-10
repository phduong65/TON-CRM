@extends('layouts.admin')

@section('title', 'Chi tiết xử phạt')
@section('page-title', 'Xử phạt #' . $penalty->id)
@section('breadcrumb', 'Xử phạt / Chi tiết')

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Info + Attachments -->
        <div class="lg:col-span-2 space-y-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="font-semibold text-slate-900 dark:text-white">Thông tin xử phạt</h3>
                </div>
                <div class="card-body space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Mã phiếu</p>
                            <p class="font-mono text-sm">{{ $penalty->code ?? '#' . $penalty->id }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Trạng thái</p>
                            @php
                                $m = ['pending' => ['badge-warning', 'Chờ duyệt'], 'approved' => ['badge-success', 'Đã duyệt'], 'rejected' => ['badge-danger', 'Từ chối']];
                                [$cls, $lbl] = $m[$penalty->status] ?? ['badge-neutral', $penalty->status];
                            @endphp
                            <span class="{{ $cls }} mt-1 inline-block">{{ $lbl }}</span>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Nhân viên</p>
                            <p class="text-sm font-medium">{{ $penalty->employee->name ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Lỗi vi phạm</p>
                            <p class="text-sm">{{ $penalty->violation->name ?? 'N/A' }}</p>
                        </div>
                        @if($penalty->description)
                        <div class="col-span-2">
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Mô tả</p>
                            <p class="text-sm text-slate-700 dark:text-slate-300">{{ $penalty->description }}</p>
                        </div>
                        @endif
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Điểm trừ</p>
                            <p class="text-lg font-bold text-red-600 dark:text-red-400">-{{ number_format($penalty->total_points_deducted) }}</p>
                        </div>
                        @if($penalty->total_money_deducted > 0)
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Tiền phạt</p>
                            <p class="text-lg font-bold text-red-600 dark:text-red-400">{{ number_format($penalty->total_money_deducted, 0, ',', '.') }}₫</p>
                        </div>
                        @endif
                        @if($penalty->approved_at)
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Ngày duyệt</p>
                            <p class="text-sm">{{ $penalty->approved_at->format('d/m/Y H:i') }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Người duyệt</p>
                            <p class="text-sm">{{ $penalty->approver->name ?? 'N/A' }}</p>
                        </div>
                        @endif
                        @if($penalty->rejected_reason)
                        <div class="col-span-2">
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Lý do từ chối</p>
                            <p class="text-sm text-red-600 dark:text-red-400">{{ $penalty->rejected_reason }}</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Attachments Card --}}
            @if($penalty->attachments->count() > 0)
            <div class="card">
                <div class="card-header flex items-center gap-2">
                    <i class="bi bi-paperclip text-slate-500 dark:text-slate-400"></i>
                    <h3 class="font-semibold text-slate-900 dark:text-white">
                        Tệp đính kèm
                        <span class="ml-1.5 text-xs font-normal text-slate-400">({{ $penalty->attachments->count() }})</span>
                    </h3>
                </div>
                <div class="card-body">
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                        @foreach($penalty->attachments as $att)
                        @if($att->type === 'image')
                        <a href="{{ $att->url }}" target="_blank"
                           class="group relative aspect-square rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-800 block">
                            <img src="{{ $att->url }}" alt="{{ $att->filename }}"
                                 class="w-full h-full object-cover transition-transform duration-200 group-hover:scale-105">
                            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors flex items-end">
                                <p class="w-full text-[10px] text-white bg-black/50 px-2 py-1 truncate opacity-0 group-hover:opacity-100 transition-opacity">
                                    {{ $att->filename }}
                                </p>
                            </div>
                        </a>
                        @else
                        <div class="aspect-square rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 flex flex-col items-center justify-center gap-2 p-3">
                            <i class="bi bi-film text-3xl text-slate-400"></i>
                            <p class="text-[10px] text-slate-500 dark:text-slate-400 text-center break-all leading-tight line-clamp-2">
                                {{ $att->filename }}
                            </p>
                            <p class="text-[10px] text-slate-400">{{ $att->formatted_size }}</p>
                            <a href="{{ $att->url }}" target="_blank"
                               class="text-[10px] text-pcrm-600 dark:text-pcrm-400 hover:underline flex items-center gap-0.5">
                                <i class="bi bi-play-circle text-xs"></i> Xem
                            </a>
                        </div>
                        @endif
                        @endforeach
                    </div>
                </div>
            </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="space-y-4">
            <div class="card">
                <div class="card-header">
                    <h4 class="font-semibold text-slate-900 dark:text-white">Thành viên liên quan</h4>
                </div>
                <div class="card-body p-0">
                    @if($penalty->members->count() > 0)
                        <div class="divide-y divide-slate-100 dark:divide-slate-700">
                            @foreach($penalty->members as $member)
                            <div class="px-4 py-3 flex items-center justify-between">
                                <span class="text-sm">{{ $member->employee->name ?? 'N/A' }}</span>
                                <span class="text-sm font-semibold text-red-600">-{{ number_format($member->points_deducted) }}</span>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="p-4 text-center text-sm text-slate-400">
                            Không có thành viên khác
                        </div>
                    @endif
                </div>
            </div>

            @can('approve-penalties')
                @if($penalty->status === 'pending')
                <div class="card">
                    <div class="card-body space-y-2">
                        <form action="{{ route('penalties.approve', $penalty) }}" method="POST" onsubmit="return confirm('Xác nhận duyệt xử phạt này?')">
                            @csrf
                            <button type="submit" class="btn-primary w-full">
                                <i class="ph-check-circle"></i>
                                <span>Duyệt xử phạt</span>
                            </button>
                        </form>
                        <button type="button" class="btn-secondary w-full" onclick="showRejectModal({{ $penalty->id }})">
                            <i class="ph-x-circle"></i>
                            <span>Từ chối</span>
                        </button>
                    </div>
                </div>
                @endif
            @endcan
        </div>
    </div>

    <div id="reject-modal" class="modal-overlay hidden">
        <div class="modal-content p-6">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Từ chối xử phạt</h3>
            <form id="reject-form" method="POST">
                @csrf
                <div class="mb-4">
                    <label class="form-label">Lý do từ chối</label>
                    <textarea name="rejected_reason" rows="3" class="form-input" required placeholder="Nhập lý do..."></textarea>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" class="btn-secondary" onclick="hideRejectModal()">Hủy</button>
                    <button type="submit" class="btn-danger">Xác nhận</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showRejectModal(id) {
            document.getElementById('reject-modal').classList.remove('hidden');
            document.getElementById('reject-form').action = '/penalties/' + id + '/reject';
        }
        function hideRejectModal() {
            document.getElementById('reject-modal').classList.add('hidden');
        }
    </script>
@endsection
