@extends('layouts.admin')

@section('title', 'Thông báo')
@section('page-title', 'Thông báo')
@section('breadcrumb', 'Thông báo')

@section('page-subtitle')
    @if ($unreadCount > 0)
        Bạn có <span class="font-semibold text-[#C94758]">{{ $unreadCount }} thông báo chưa đọc</span>
    @else
        Tất cả thông báo đã được đọc
    @endif
@endsection

@can('create-notifications')
@section('page-actions')
    <button type="button" onclick="openModal('createNotificationModal')" class="btn-primary">
        <i class="bi bi-send"></i>
        <span>Gửi thông báo</span>
    </button>
@endsection
@endcan

@section('content')
    @php
        $currentStatus = in_array(request('status'), ['unread', 'read'], true) ? request('status') : null;
        $statusTabs = ['unread' => 'Chưa đọc', 'read' => 'Đã đọc'];
        $q = fn(array $set = [], array $drop = []) => array_filter(
            array_merge(request()->except(array_merge(['page'], $drop, array_keys($set))), $set),
            fn($v) => $v !== null && $v !== ''
        );
        $isFiltered = $currentStatus || $activeCategory;
    @endphp

    <div class="card overflow-hidden">
        <div class="notif-head">
            <nav class="status-tabs" aria-label="Lọc theo trạng thái đọc">
                <a href="{{ route('notifications.index', $q([], ['status'])) }}"
                   class="status-tab {{ !$currentStatus ? 'is-active' : '' }}" @if (!$currentStatus) aria-current="page" @endif>
                    Tất cả <span class="status-tab-count">{{ number_format($statusCounts['all']) }}</span>
                </a>
                @foreach ($statusTabs as $key => $label)
                    <a href="{{ route('notifications.index', $q(['status' => $key])) }}"
                       class="status-tab {{ $currentStatus === $key ? 'is-active' : '' }} {{ $key === 'unread' && $statusCounts['unread'] > 0 ? 'has-attention' : '' }}"
                       @if ($currentStatus === $key) aria-current="page" @endif>
                        {{ $label }} <span class="status-tab-count">{{ number_format($statusCounts[$key]) }}</span>
                    </a>
                @endforeach
            </nav>
            @if ($unreadCount > 0)
                <form action="{{ route('notifications.read-all') }}" method="POST">
                    @csrf
                    <button type="submit" class="notif-head-action" title="Đánh dấu tất cả đã đọc">
                        <i class="bi bi-check2-all" aria-hidden="true"></i>
                        <span class="hidden sm:inline">Đánh dấu tất cả đã đọc</span>
                        <span class="sm:hidden">Đọc hết</span>
                    </button>
                </form>
            @endif
        </div>

        {{-- Nhóm thông báo — số trên chip là số CHƯA ĐỌC của nhóm --}}
        <div class="type-chips notif-chips" role="group" aria-label="Lọc theo nhóm thông báo">
            <a href="{{ route('notifications.index', $q([], ['category'])) }}"
               class="type-chip {{ !$activeCategory ? 'is-active' : '' }}" @if (!$activeCategory) aria-current="true" @endif>
                <i class="bi bi-grid" aria-hidden="true"></i> Mọi nhóm
            </a>
            @foreach ($categories as $key => $cat)
                @php $catUnread = $categoryUnreadCounts[$key] ?? 0; @endphp
                <a href="{{ route('notifications.index', $q(['category' => $key])) }}"
                   class="type-chip {{ $activeCategory === $key ? 'is-active' : '' }}" @if ($activeCategory === $key) aria-current="true" @endif
                   @if ($catUnread > 0) aria-label="{{ $cat['label'] }}, {{ $catUnread }} chưa đọc" @endif>
                    <i class="bi {{ $cat['icon'] }}" aria-hidden="true"></i> {{ $cat['label'] }}
                    @if ($catUnread > 0)<span class="notif-unread-count" aria-hidden="true">{{ $catUnread }}</span>@endif
                </a>
            @endforeach
        </div>

        @if ($notifications->isEmpty())
            <div class="px-6 py-16 text-center text-slate-400 dark:text-slate-500">
                <i class="bi {{ $currentStatus === 'unread' ? 'bi-check2-all text-[#168A63]' : 'bi-bell-slash' }} mb-3 block text-4xl" aria-hidden="true"></i>
                <p class="text-sm font-medium text-slate-600 dark:text-slate-300">
                    {{ $currentStatus === 'unread' ? 'Không còn thông báo chưa đọc' : ($isFiltered ? 'Không có thông báo nào khớp bộ lọc' : 'Bạn chưa có thông báo nào') }}
                </p>
                @if ($isFiltered)
                    <a href="{{ route('notifications.index') }}" class="mt-3 inline-flex items-center gap-1.5 text-sm text-pcrm-600 dark:text-pcrm-400 hover:underline">
                        <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Xem tất cả thông báo
                    </a>
                @endif
            </div>
        @else
            @php
                // Nhóm theo mốc thời gian để hộp thư dễ quét — chỉ trên trang hiện tại của phân trang
                $groupOf = function ($date) {
                    if ($date->isToday()) return 'Hôm nay';
                    if ($date->isYesterday()) return 'Hôm qua';
                    if ($date->greaterThanOrEqualTo(now()->subDays(6)->startOfDay())) return '7 ngày qua';
                    return 'Tháng ' . $date->format('m/Y');
                };
                $groups = $notifications->getCollection()->groupBy(fn($n) => $groupOf($n->created_at));
            @endphp
            <ul class="notif-list" aria-label="Danh sách thông báo">
                @foreach ($groups as $groupLabel => $items)
                    <li class="notif-group-head" role="presentation">{{ $groupLabel }}</li>
                @foreach ($items as $notif)
                    @php
                        $isUnread = $notif->isUnread();
                        $at = $notif->created_at;
                        $timeLabel = $at->isToday() || $at->isYesterday() ? $at->format('H:i') : $at->format('d/m');
                    @endphp
                    <li class="notif-item {{ $isUnread ? 'is-unread' : '' }}">
                        <span class="notif-dot" aria-hidden="true"></span>
                        <span class="notif-icon {{ $notif->typeColor() }}" title="{{ $notif->typeLabel() }}" aria-hidden="true">
                            <i class="bi {{ $notif->typeIcon() }}"></i>
                        </span>
                        <a href="{{ route('notifications.show', $notif) }}" class="min-w-0 flex-1 group">
                            <span class="notif-line">
                                <span class="notif-title group-hover:text-pcrm-600 dark:group-hover:text-pcrm-400">{{ $notif->title }}</span>
                                <span class="sr-only">— {{ $notif->typeLabel() }}{{ $isUnread ? ', chưa đọc' : '' }}</span>
                                <time class="notif-time" datetime="{{ $at->toIso8601String() }}" title="{{ $at->format('H:i d/m/Y') }} · {{ $at->diffForHumans() }}">{{ $timeLabel }}</time>
                            </span>
                            @if ($notif->body)
                                <span class="notif-body">{{ $notif->body }}</span>
                            @endif
                        </a>
                        <div class="notif-actions">
                            <a href="{{ route('notifications.show', ['notification' => $notif, 'stay' => 1]) }}" class="row-action" title="Xem chi tiết thông báo" aria-label="Xem chi tiết thông báo: {{ $notif->title }}">
                                <i class="bi bi-eye"></i>
                            </a>
                            @if ($isUnread)
                                <form action="{{ route('notifications.read', $notif) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="row-action row-action-success" title="Đánh dấu đã đọc" aria-label="Đánh dấu đã đọc: {{ $notif->title }}">
                                        <i class="bi bi-check2"></i>
                                    </button>
                                </form>
                            @endif
                            <button type="button" class="row-action row-action-danger" title="Xoá" aria-label="Xoá thông báo: {{ $notif->title }}"
                                    onclick="openDeleteNotificationModal({{ Illuminate\Support\Js::from(route('notifications.destroy', $notif)) }}, {{ Illuminate\Support\Js::from($notif->title) }})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </li>
                @endforeach
                @endforeach
            </ul>
        @endif

        @if ($notifications->hasPages())
            <div class="card-footer">{{ $notifications->links() }}</div>
        @endif
    </div>
@endsection

@push('modals')
    @include('notifications.partials.delete-modal')
    @can('create-notifications')
        @include('notifications.partials.create-modal')
    @endcan
@endpush

@can('create-notifications')
    @if ($errors->any() && old('_modal') === 'createNotificationModal')
        @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () { openModal('createNotificationModal'); });
        </script>
        @endpush
    @endif
@endcan
