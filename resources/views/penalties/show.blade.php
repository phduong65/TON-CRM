@extends('layouts.admin')

@section('title', 'Chi tiết xử phạt')
@section('page-title', 'Phiếu phạt ' . ($penalty->code ?? '#' . $penalty->id))
@section('breadcrumb', 'Xử phạt / Chi tiết')

@section('content')
    <div class="mb-4">
        <a href="{{ route('penalties.index') }}" class="btn-secondary btn-sm">
            <i class="bi bi-arrow-left text-xs"></i> Quay lại
        </a>
    </div>

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
                                $m = [
                                    'pending'  => ['badge-warning', 'Chờ duyệt'],
                                    'approved' => ['badge-success', 'Đã duyệt'],
                                    'rejected' => ['badge-danger', 'Từ chối'],
                                    'revoked'  => ['bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300 px-2 py-0.5 rounded text-xs font-medium', 'Đã thu hồi'],
                                ];
                                [$cls, $lbl] = $m[$penalty->status] ?? ['badge-neutral', $penalty->status];
                            @endphp
                            <span class="{{ $cls }} text-sm mt-1 inline-block font-medium">{{ $lbl }}</span>
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
                        @if($penalty->revoked_at)
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Ngày thu hồi</p>
                            <p class="text-sm">{{ $penalty->revoked_at->format('d/m/Y H:i') }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Người thu hồi</p>
                            <p class="text-sm font-medium">{{ $penalty->revoker?->name }}</p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-xs text-slate-400 uppercase tracking-wider">Lý do thu hồi</p>
                            <p class="text-sm text-slate-700 dark:text-slate-300">{{ $penalty->revoked_reason }}</p>
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

            @canany(['approve-penalties', 'revoke-penalties'])
            @if(in_array($penalty->status, ['pending', 'approved']))
            <div class="card">
                <div class="card-body space-y-2">
                    @if($penalty->status === 'pending')
                        @can('approve-penalties')
                        <form action="{{ route('penalties.approve', $penalty) }}" method="POST"
                              onsubmit="return confirm('Xác nhận duyệt xử phạt này?')">
                            @csrf
                            <button type="submit" class="btn-primary w-full">
                                <i class="bi bi-check-circle"></i>
                                <span>Duyệt xử phạt</span>
                            </button>
                        </form>
                        <button type="button" class="btn-danger w-full" onclick="openModal('rejectPenaltyModal')">
                            <i class="bi bi-x-circle"></i>
                            <span>Từ chối</span>
                        </button>
                        @endcan
                    @endif

                    @if($penalty->status === 'approved')
                        @can('revoke-penalties')
                        <button type="button" class="btn-secondary w-full border-orange-300 text-orange-700 hover:bg-orange-50 dark:border-orange-700 dark:text-orange-400 dark:hover:bg-orange-900/20"
                                onclick="openModal('revokePenaltyModal')">
                            <i class="bi bi-arrow-counterclockwise"></i>
                            <span>Thu hồi phiếu phạt</span>
                        </button>
                        @endcan
                    @endif
                </div>
            </div>
            @endif
            @endcanany
        </div>
    </div>
@endsection

@push('modals')
{{-- Reject Modal --}}
<div id="rejectPenaltyModal" class="hidden fixed inset-0 bg-black/50 z-50 items-center justify-center p-4"
     onclick="if(event.target===this)closeModal('rejectPenaltyModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-x-circle text-red-500"></i> Từ chối xử phạt
            </h3>
            <button onclick="closeModal('rejectPenaltyModal')"
                    class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form action="{{ route('penalties.reject', $penalty) }}" method="POST" class="px-6 py-5 space-y-4">
            @csrf
            <div>
                <label class="form-label">Lý do từ chối <span class="text-red-500">*</span></label>
                <textarea name="rejected_reason" class="form-input" rows="3"
                          placeholder="Nhập lý do từ chối..." required maxlength="500"></textarea>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('rejectPenaltyModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-danger"><i class="bi bi-x-circle"></i> Xác nhận từ chối</button>
            </div>
        </form>
    </div>
</div>

{{-- Revoke Modal --}}
<div id="revokePenaltyModal" class="hidden fixed inset-0 bg-black/50 z-50 items-center justify-center p-4"
     onclick="if(event.target===this)closeModal('revokePenaltyModal')">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
            <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="bi bi-arrow-counterclockwise text-orange-500"></i> Thu hồi phiếu phạt
            </h3>
            <button onclick="closeModal('revokePenaltyModal')"
                    class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
        </div>
        <form action="{{ route('penalties.revoke', $penalty) }}" method="POST" class="px-6 py-5 space-y-4">
            @csrf
            <div class="rounded-lg bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 p-3 text-sm text-orange-700 dark:text-orange-300">
                <i class="bi bi-exclamation-triangle mr-1"></i>
                Thu hồi sẽ hoàn lại <strong>{{ number_format($penalty->total_points_deducted) }} điểm</strong>
                cho nhân viên liên quan. Hành động này không thể hoàn tác.
            </div>
            <div>
                <label class="form-label">Lý do thu hồi <span class="text-red-500">*</span></label>
                <textarea name="revoked_reason" class="form-input" rows="3"
                          placeholder="Nhập lý do thu hồi..." required maxlength="500"></textarea>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closeModal('revokePenaltyModal')" class="btn-secondary">Hủy</button>
                <button type="submit" class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-1.5 transition-colors">
                    <i class="bi bi-arrow-counterclockwise"></i> Xác nhận thu hồi
                </button>
            </div>
        </form>
    </div>
</div>
@endpush
