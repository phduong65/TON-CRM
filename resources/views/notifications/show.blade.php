@extends('layouts.admin')

@section('title', $notification->title)
@section('page-title', 'Chi tiết thông báo')
@section('breadcrumb', 'Thông báo')

@section('content')
    {{-- Navigation bar --}}
    <div class="flex items-center justify-between mb-4">
        <a href="{{ route('notifications.index') }}"
           class="inline-flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 transition-colors">
            <i class="bi bi-arrow-left text-base"></i>
            <span>Quay lại thông báo</span>
        </a>

        <div class="flex items-center gap-2">
            {{-- Prev / Next navigation --}}
            @if($prev)
                <a href="{{ route('notifications.show', $prev) }}"
                   class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
                   title="Thông báo cũ hơn">
                    <i class="bi bi-chevron-up text-sm"></i>
                </a>
            @else
                <span class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-100 dark:border-slate-700/50 text-slate-300 dark:text-slate-600 cursor-not-allowed">
                    <i class="bi bi-chevron-up text-sm"></i>
                </span>
            @endif
            @if($next)
                <a href="{{ route('notifications.show', $next) }}"
                   class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
                   title="Thông báo mới hơn">
                    <i class="bi bi-chevron-down text-sm"></i>
                </a>
            @else
                <span class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-100 dark:border-slate-700/50 text-slate-300 dark:text-slate-600 cursor-not-allowed">
                    <i class="bi bi-chevron-down text-sm"></i>
                </span>
            @endif

            {{-- Delete button --}}
            <form action="{{ route('notifications.destroy', $notification) }}" method="POST"
                  onsubmit="return confirm('Xóa thông báo này?')">
                @csrf @method('DELETE')
                <button type="submit"
                        class="inline-flex items-center gap-1.5 px-3 h-8 rounded-lg border border-red-200 dark:border-red-800/50 text-red-600 dark:text-red-400 text-sm hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                    <i class="bi bi-trash3 text-xs"></i>
                    <span class="hidden sm:inline">Xóa</span>
                </button>
            </form>
        </div>
    </div>

    {{-- Notification detail card --}}
    <div class="card">
        {{-- Header --}}
        <div class="px-6 pt-6 pb-5 border-b border-slate-100 dark:border-slate-700">
            <div class="flex items-start gap-4">
                {{-- Type icon --}}
                <div class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0 {{ $notification->typeColor() }}">
                    <i class="bi {{ $notification->typeIcon() }} text-xl"></i>
                </div>

                <div class="flex-1 min-w-0">
                    {{-- Type badge --}}
                    <span class="inline-flex items-center text-[10px] font-bold px-2 py-0.5 rounded-md uppercase tracking-wider mb-2 {{ $notification->typeBadgeClass() }}">
                        {{ $notification->typeLabel() }}
                    </span>

                    {{-- Title --}}
                    <h1 class="text-xl font-bold text-slate-900 dark:text-white leading-snug">
                        {{ $notification->title }}
                    </h1>

                    {{-- Meta --}}
                    <div class="flex flex-wrap items-center gap-3 mt-2 text-xs text-slate-400 dark:text-slate-500">
                        <span class="flex items-center gap-1.5">
                            <i class="bi bi-clock text-xs"></i>
                            {{ $notification->created_at->format('H:i — d/m/Y') }}
                            <span class="text-slate-300 dark:text-slate-600">·</span>
                            {{ $notification->created_at->diffForHumans() }}
                        </span>
                        @if($notification->creator)
                            <span class="flex items-center gap-1.5">
                                <i class="bi bi-person text-xs"></i>
                                Từ {{ $notification->creator->name }}
                            </span>
                        @endif
                        <span class="flex items-center gap-1.5 text-emerald-500 dark:text-emerald-400">
                            <i class="bi bi-check2-circle text-xs"></i>
                            Đã đọc lúc {{ $notification->read_at?->format('H:i d/m/Y') ?? 'vừa xong' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Body --}}
        <div class="px-6 py-6">
            @if($notification->body)
                <div class="prose prose-sm dark:prose-invert max-w-none text-slate-700 dark:text-slate-300 leading-relaxed whitespace-pre-wrap">{{ $notification->body }}</div>
            @else
                <p class="text-slate-400 dark:text-slate-500 italic text-sm">Không có nội dung chi tiết.</p>
            @endif
        </div>

        {{-- Action footer --}}
        @php $actionUrl = $notification->actionUrl(); @endphp
        @if($actionUrl)
            <div class="px-6 pb-6">
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/30 p-4 flex items-center gap-3">
                    @if($notification->penaltyUrl())
                        <div class="w-9 h-9 rounded-xl bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center shrink-0">
                            <i class="bi bi-hammer text-amber-600 dark:text-amber-400 text-sm"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">Xem phiếu phạt liên quan</p>
                            <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Nhấn để xem chi tiết phiếu phạt và thực hiện các hành động.</p>
                        </div>
                        <a href="{{ $actionUrl }}" class="btn-primary shrink-0">
                            <i class="bi bi-arrow-right-circle"></i>
                            <span>Xem phiếu phạt</span>
                        </a>
                    @else
                        <div class="w-9 h-9 rounded-xl bg-emerald-100 dark:bg-emerald-900/40 flex items-center justify-center shrink-0">
                            <i class="bi bi-gift-fill text-emerald-600 dark:text-emerald-400 text-sm"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">Xem phiếu thưởng liên quan</p>
                            <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Nhấn để xem chi tiết phiếu thưởng và thực hiện các hành động.</p>
                        </div>
                        <a href="{{ $actionUrl }}" class="btn-primary shrink-0">
                            <i class="bi bi-arrow-right-circle"></i>
                            <span>Xem phiếu thưởng</span>
                        </a>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endsection
