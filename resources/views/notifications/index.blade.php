@extends('layouts.admin')

@section('title', 'Thông báo')
@section('page-title', 'Thông báo')
@section('breadcrumb', 'Thông báo')

@section('content')
    {{-- Page header --}}
    <div class="page-header">
        <div class="flex items-center gap-2.5">
            <h1 class="text-lg font-bold text-slate-900 dark:text-white">Thông báo</h1>
            @if($unreadCount > 0)
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-red-500 text-white">
                    {{ $unreadCount }}
                </span>
            @endif
        </div>
        <div class="flex items-center gap-2">
            @if($unreadCount > 0)
                <form action="{{ route('notifications.read-all') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn-secondary">
                        <i class="bi bi-check2-all"></i>
                        <span class="hidden sm:inline">Đọc tất cả</span>
                    </button>
                </form>
            @endif
            @can('create-notifications')
                <button onclick="openModal('createNotificationModal')" class="btn-primary">
                    <i class="bi bi-send-plus"></i>
                    <span class="hidden sm:inline">Tạo thông báo</span>
                </button>
            @endcan
        </div>
    </div>

    <div class="card">
        {{-- Filter bar --}}
        <form action="{{ route('notifications.index') }}" method="GET"
              class="px-4 py-2.5 border-b border-slate-100 dark:border-slate-700 flex items-center gap-2 flex-wrap">
            <select name="status" class="form-input w-auto h-8 text-sm py-0" onchange="this.form.submit()">
                <option value="">Tất cả trạng thái</option>
                <option value="unread" @selected(request('status') === 'unread')>Chưa đọc</option>
                <option value="read"   @selected(request('status') === 'read')>Đã đọc</option>
            </select>
            <select name="type" class="form-input w-auto h-8 text-sm py-0" onchange="this.form.submit()">
                <option value="">Tất cả loại</option>
                <option value="general"          @selected(request('type') === 'general')>Thông báo chung</option>
                <option value="penalty_created"  @selected(request('type') === 'penalty_created')>Phiếu phạt mới</option>
                <option value="penalty_approved" @selected(request('type') === 'penalty_approved')>Phiếu phạt duyệt</option>
                <option value="penalty_rejected" @selected(request('type') === 'penalty_rejected')>Phiếu phạt từ chối</option>
            </select>
            @if(request()->anyFilled(['status', 'type']))
                <a href="{{ route('notifications.index') }}"
                   class="inline-flex items-center gap-1 h-8 px-2.5 rounded-lg text-xs text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors border border-slate-200 dark:border-slate-600">
                    <i class="bi bi-x text-sm"></i> Xóa lọc
                </a>
            @endif
            <span class="ml-auto text-xs text-slate-400 dark:text-slate-500">{{ $notifications->total() }} thông báo</span>
        </form>

        {{-- Notification list --}}
        <div class="divide-y divide-slate-100 dark:divide-slate-700/60">
            @forelse($notifications as $notif)
                @php $isUnread = $notif->isUnread(); @endphp

                <div class="relative group flex items-center gap-3 px-4 py-3
                    {{ $isUnread
                        ? 'bg-white dark:bg-slate-800 hover:bg-pcrm-50/40 dark:hover:bg-slate-700/40'
                        : 'bg-slate-50/40 dark:bg-slate-800/30 hover:bg-slate-100/60 dark:hover:bg-slate-700/30' }}
                    transition-colors">

                    {{-- Unread dot --}}
                    <div class="w-1.5 shrink-0">
                        @if($isUnread)
                            <span class="block w-1.5 h-1.5 rounded-full bg-pcrm-500"></span>
                        @endif
                    </div>

                    {{-- Type icon --}}
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 {{ $notif->typeColor() }}">
                        <i class="bi {{ $notif->typeIcon() }} text-sm"></i>
                    </div>

                    {{-- Content — main clickable --}}
                    <a href="{{ route('notifications.show', $notif) }}"
                       class="flex-1 min-w-0 flex items-center gap-3 group/link">

                        <div class="flex-1 min-w-0">
                            {{-- Title row: text + badge --}}
                            <div class="flex items-center gap-2 min-w-0">
                                <p class="text-sm truncate leading-snug
                                    {{ $isUnread
                                        ? 'font-semibold text-slate-900 dark:text-white'
                                        : 'font-medium text-slate-500 dark:text-slate-400' }}">
                                    {{ $notif->title }}
                                </p>
                                <span class="shrink-0 text-[10px] font-semibold px-1.5 py-0.5 rounded-md
                                    {{ match($notif->type) {
                                        'penalty_created'  => 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400',
                                        'penalty_approved' => 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400',
                                        'penalty_rejected' => 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
                                        default            => 'bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400',
                                    } }}">
                                    {{ $notif->typeLabel() }}
                                </span>
                            </div>
                            {{-- Body snippet --}}
                            @if($notif->body)
                                <p class="text-xs text-slate-400 dark:text-slate-500 truncate mt-0.5">{{ $notif->body }}</p>
                            @endif
                        </div>

                        {{-- Time --}}
                        <span class="shrink-0 text-xs whitespace-nowrap
                            {{ $isUnread ? 'font-medium text-slate-600 dark:text-slate-300' : 'text-slate-400 dark:text-slate-500' }}">
                            {{ $notif->created_at->diffForHumans(null, true, true) }}
                        </span>
                    </a>

                    {{-- Actions (hover) --}}
                    <div class="shrink-0 flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                        @if($isUnread)
                            <form action="{{ route('notifications.read', $notif) }}" method="POST">
                                @csrf
                                <button type="submit"
                                        class="w-7 h-7 flex items-center justify-center rounded-lg text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-colors"
                                        title="Đánh dấu đã đọc">
                                    <i class="bi bi-check2 text-sm"></i>
                                </button>
                            </form>
                        @endif
                        <form action="{{ route('notifications.destroy', $notif) }}" method="POST">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                                    title="Xóa"
                                    onclick="return confirm('Xóa thông báo này?')">
                                <i class="bi bi-trash3 text-xs"></i>
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="py-16 text-center">
                    <div class="w-14 h-14 rounded-full bg-slate-100 dark:bg-slate-700/60 flex items-center justify-center mx-auto mb-3">
                        <i class="bi bi-bell-slash text-xl text-slate-400 dark:text-slate-500"></i>
                    </div>
                    <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Không có thông báo nào</p>
                    @if(request()->anyFilled(['status', 'type']))
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Thử xóa bộ lọc để xem tất cả</p>
                    @endif
                </div>
            @endforelse
        </div>

        @if($notifications->hasPages())
            <div class="card-footer">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>

@endsection

@can('create-notifications')
@push('modals')
    @include('notifications.partials.create-modal')
@endpush
@endcan
