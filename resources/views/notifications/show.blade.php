@extends('layouts.admin')

@php
    $actionUrl    = $linkedDeleted ? null : $notification->actionUrl();
    $isPenalty    = isset($notification->data['penalty_id']);
    $isReward     = isset($notification->data['reward_id']);
    $isReport     = isset($notification->data['report_id']);
    $hasLinkedRef = $isPenalty || $isReward || $isReport;

    [$linkLabel, $linkIcon] = match (true) {
        (bool) $notification->penaltyUrl() => ['Xem phiếu phạt', 'bi-hammer'],
        (bool) $notification->rewardUrl()  => ['Xem phiếu thưởng', 'bi-gift'],
        (bool) $notification->reportUrl()  => ['Xem báo cáo', 'bi-flag'],
        default                            => ['Mở mục liên quan', 'bi-box-arrow-up-right'],
    };
    $at = $notification->created_at;
@endphp

@section('title', $notification->title)
@section('page-title', 'Thông báo')
@section('breadcrumb', 'Thông báo / Chi tiết')

@section('page-actions')
    <a href="{{ route('notifications.index') }}" class="btn-secondary">
        <i class="bi bi-arrow-left"></i> <span>Hộp thư</span>
    </a>
    <div class="notif-pager" role="group" aria-label="Chuyển thông báo">
        @if ($newer)
            <a href="{{ route('notifications.show', $newer) }}" class="row-action" title="Thông báo mới hơn" aria-label="Thông báo mới hơn"><i class="bi bi-chevron-up"></i></a>
        @else
            <span class="row-action is-disabled" aria-hidden="true"><i class="bi bi-chevron-up"></i></span>
        @endif
        @if ($older)
            <a href="{{ route('notifications.show', $older) }}" class="row-action" title="Thông báo cũ hơn" aria-label="Thông báo cũ hơn"><i class="bi bi-chevron-down"></i></a>
        @else
            <span class="row-action is-disabled" aria-hidden="true"><i class="bi bi-chevron-down"></i></span>
        @endif
    </div>
    <button type="button" class="row-action row-action-danger notif-pager-delete" title="Xoá thông báo" aria-label="Xoá thông báo"
            onclick="openDeleteNotificationModal({{ Illuminate\Support\Js::from(route('notifications.destroy', $notification)) }}, {{ Illuminate\Support\Js::from($notification->title) }})">
        <i class="bi bi-trash"></i>
    </button>
@endsection

@section('content')
    <article class="card notif-detail" aria-labelledby="notifTitle">
        @unless ($isOwner)
            <p class="notif-detail-notice">
                <i class="bi bi-eye" aria-hidden="true"></i>
                Bạn đang xem thông báo của {{ $notification->user?->name ?? 'người khác' }} — trạng thái đọc không thay đổi
            </p>
        @endunless

        <header class="notif-detail-head">
            <span class="notif-icon {{ $notification->typeColor() }}" aria-hidden="true">
                <i class="bi {{ $notification->typeIcon() }}"></i>
            </span>
            <div class="min-w-0 flex-1">
                <h2 id="notifTitle" class="notif-detail-title">{{ $notification->title }}</h2>
                <p class="notif-detail-meta">
                    <span class="font-semibold">{{ $notification->typeLabel() }}</span>
                    <span aria-hidden="true">·</span>
                    <time datetime="{{ $at->toIso8601String() }}" title="{{ $at->diffForHumans() }}">{{ $at->format('H:i d/m/Y') }}</time>
                    <span aria-hidden="true">·</span>
                    <span>{{ $notification->creator ? 'Từ ' . $notification->creator->name : 'Hệ thống' }}</span>
                    @if ($isOwner && $notification->read_at)
                        <span aria-hidden="true">·</span>
                        <span class="text-[#168A63] dark:text-[#34d399]" title="Đã đọc lúc {{ $notification->read_at->format('H:i d/m/Y') }}">
                            <i class="bi bi-check2" aria-hidden="true"></i> Đã đọc
                        </span>
                    @endif
                </p>
            </div>
        </header>

        <div class="notif-detail-body">
            @if ($notification->body)
                <p class="whitespace-pre-wrap">{{ $notification->body }}</p>
            @else
                <p class="italic text-slate-400">Thông báo không có nội dung chi tiết.</p>
            @endif
        </div>

        @if ($linkedDeleted && $hasLinkedRef)
            <footer class="notif-detail-foot">
                <p class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                    <i class="bi bi-trash" aria-hidden="true"></i>
                    {{ $isPenalty ? 'Phiếu phạt' : ($isReward ? 'Phiếu thưởng' : 'Báo cáo') }} liên quan đã bị xoá khỏi hệ thống.
                </p>
            </footer>
        @elseif ($actionUrl)
            <footer class="notif-detail-foot">
                <a href="{{ $actionUrl }}" class="btn-primary">
                    <i class="bi {{ $linkIcon }}"></i> <span>{{ $linkLabel }}</span>
                </a>
            </footer>
        @endif
    </article>
@endsection

@push('modals')
    @include('notifications.partials.delete-modal')
@endpush
