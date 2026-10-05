@extends('layouts.admin')

@section('title', 'Quản lý chủ đề & sự kiện')
@section('page-title', 'Quản lý chủ đề & sự kiện')
@section('page-subtitle', 'Cấu hình diện mạo lễ hội, sự kiện nội bộ và lịch kích hoạt tự động theo mùa')
@section('breadcrumb', 'Hệ thống / Chủ đề')

@section('page-actions')
    <button type="button" onclick="openPreviewModal({{ $activeLoginTheme['id'] ?? 'null' }}, '{{ addslashes($activeLoginTheme['name'] ?? 'Default TON-HR') }}')"
            class="btn-secondary h-10 px-4 text-sm inline-flex items-center gap-2">
        <i class="bi bi-display text-base"></i>
        <span>Xem giả lập</span>
    </button>
    <button type="button" onclick="openCreateModal()"
            class="btn-primary h-10 px-4 text-sm inline-flex items-center gap-2">
        <i class="bi bi-plus-lg text-base"></i>
        <span>Tạo chủ đề mới</span>
    </button>
@endsection

@section('content')
<div class="space-y-5">

    {{-- Active Theme Spotlight & Quick Stats --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-stretch">
        {{-- Card: Current Winning Theme --}}
        @php
            $activeAccent = $activeLoginTheme['config']['colors']['accent'] ?? '#2F55E7';
            $activeScopes = $activeLoginTheme['scope'] ?? ['login', 'dashboard_greeting', 'app_header'];
        @endphp
        <div class="lg:col-span-8 card p-5 sm:p-6 relative overflow-hidden bg-white dark:bg-slate-800 border border-slate-200/90 dark:border-slate-700/80 shadow-xs flex flex-col justify-between h-full">
            <div class="space-y-4">
                {{-- Top Badge & Timezone --}}
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/50">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            Chủ đề đang áp dụng thực tế
                        </span>
                        <span class="text-xs text-slate-400 font-mono">Timezone: Asia/Ho_Chi_Minh</span>
                    </div>

                    <div class="flex items-center gap-2 text-xs">
                        <span class="text-slate-400 font-medium">Màu chủ đạo:</span>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 font-mono text-xs font-semibold text-slate-700 dark:text-slate-300 shadow-2xs">
                            <span class="w-3.5 h-3.5 rounded-md border border-black/10 shrink-0" style="background-color: {{ $activeAccent }};"></span>
                            {{ $activeAccent }}
                        </span>
                    </div>
                </div>

                {{-- Theme Title & Slug --}}
                <div>
                    <h3 class="text-2xl font-heading font-extrabold text-slate-900 dark:text-white tracking-tight flex items-center gap-2.5">
                        <span>{{ $activeLoginTheme['name'] ?? 'Default TON-HR' }}</span>
                        @if(($activeLoginTheme['slug'] ?? '') === 'default')
                            <span class="text-xs font-medium px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">Mặc định</span>
                        @endif
                    </h3>
                    <p class="font-mono text-xs text-slate-400 mt-0.5">Slug: {{ $activeLoginTheme['slug'] ?? 'default' }}</p>
                </div>

                {{-- Display Messages Preview --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                    <div class="rounded-xl border border-slate-100 dark:border-slate-700/60 bg-slate-50/70 dark:bg-slate-900/30 p-3.5 text-xs space-y-1.5">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
                            <i class="bi bi-box-arrow-in-right text-pcrm-600"></i> Lời chào trang Đăng nhập
                        </span>
                        <p class="font-semibold text-slate-800 dark:text-slate-200 truncate text-sm">
                            "{{ $activeLoginTheme['content']['loginGreeting'] ?? 'Chào mừng trở lại' }}"
                        </p>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 line-clamp-1">
                            {{ $activeLoginTheme['content']['loginSubtitle'] ?? 'Quản lý nhân sự, điểm thưởng và kỷ luật' }}
                        </p>
                    </div>

                    <div class="rounded-xl border border-slate-100 dark:border-slate-700/60 bg-slate-50/70 dark:bg-slate-900/30 p-3.5 text-xs space-y-1.5">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
                            <i class="bi bi-speedometer2 text-pcrm-600"></i> Lời chào Dashboard
                        </span>
                        <p class="font-semibold text-slate-800 dark:text-slate-200 truncate text-sm">
                            "{{ $activeDashboardTheme['content']['dashboardGreeting'] ?? 'Xin chào, :name 👋' }}"
                        </p>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 line-clamp-1">
                            {{ $activeLoginTheme['content']['trustLine'] ?? 'Hệ thống nội bộ TON Capital' }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Card Footer --}}
            <div class="flex items-center justify-between gap-3 pt-4 mt-4 border-t border-slate-100 dark:border-slate-700/80">
                <div class="flex items-center gap-1.5 flex-wrap text-xs">
                    <span class="text-slate-400 font-medium">Phạm vi:</span>
                    @foreach($activeScopes as $sc)
                        <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300 text-[11px] font-medium">
                            @if($sc === 'login') Đăng nhập
                            @elseif(str_contains($sc, 'dashboard')) Dashboard
                            @elseif(str_contains($sc, 'header')) Header
                            @else {{ $sc }}
                            @endif
                        </span>
                    @endforeach
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" onclick="openPreviewModal({{ $activeLoginTheme['id'] ?? 'null' }}, '{{ addslashes($activeLoginTheme['name'] ?? 'Default TON-HR') }}')"
                            class="btn-ghost btn-sm text-xs inline-flex items-center gap-1.5">
                        <i class="bi bi-eye"></i> Xem trước
                    </button>
                    @if(!empty($activeLoginTheme['id']) && ($activeLoginTheme['slug'] ?? '') !== 'default')
                        <button type="button" onclick="openRollbackModal({{ $activeLoginTheme['id'] }}, '{{ addslashes($activeLoginTheme['name']) }}')"
                                class="btn-danger btn-sm text-xs inline-flex items-center gap-1.5">
                            <i class="bi bi-arrow-counterclockwise"></i> Rollback khẩn cấp
                        </button>
                    @endif
                </div>
            </div>
        </div>

        {{-- Card: 2x2 Stats Summary --}}
        <div class="lg:col-span-4 grid grid-cols-2 gap-3.5 h-full">
            {{-- Stat 1: Total --}}
            <div class="card p-4 sm:p-4.5 flex flex-col justify-between border border-slate-200/90 dark:border-slate-700/80 shadow-xs h-full">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Tổng chủ đề</span>
                    <span class="w-8 h-8 rounded-xl bg-pcrm-50 dark:bg-pcrm-900/30 text-pcrm-600 dark:text-pcrm-300 flex items-center justify-center text-sm">
                        <i class="bi bi-palette"></i>
                    </span>
                </div>
                <div class="mt-3">
                    <p class="text-2xl font-heading font-extrabold text-slate-900 dark:text-white tabular-nums">
                        {{ $stats['total'] ?? $themes->total() }}
                    </p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Sự kiện & chủ đề lưu</p>
                </div>
            </div>

            {{-- Stat 2: Active --}}
            <div class="card p-4 sm:p-4.5 flex flex-col justify-between border border-slate-200/90 dark:border-slate-700/80 shadow-xs h-full">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Đang chạy</span>
                    <span class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-300 flex items-center justify-center text-sm">
                        <i class="bi bi-check-circle"></i>
                    </span>
                </div>
                <div class="mt-3">
                    <p class="text-2xl font-heading font-extrabold text-emerald-600 dark:text-emerald-400 tabular-nums">
                        {{ $stats['active'] ?? 0 }}
                    </p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Kích hoạt trực tiếp</p>
                </div>
            </div>

            {{-- Stat 3: Scheduled --}}
            <div class="card p-4 sm:p-4.5 flex flex-col justify-between border border-slate-200/90 dark:border-slate-700/80 shadow-xs h-full">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Đã lên lịch</span>
                    <span class="w-8 h-8 rounded-xl bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-300 flex items-center justify-center text-sm">
                        <i class="bi bi-calendar-event"></i>
                    </span>
                </div>
                <div class="mt-3">
                    <p class="text-2xl font-heading font-extrabold text-blue-600 dark:text-blue-400 tabular-nums">
                        {{ $stats['scheduled'] ?? 0 }}
                    </p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Chờ đến mốc ngày</p>
                </div>
            </div>

            {{-- Stat 4: Draft / Paused --}}
            <div class="card p-4 sm:p-4.5 flex flex-col justify-between border border-slate-200/90 dark:border-slate-700/80 shadow-xs h-full">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Nháp / Tạm dừng</span>
                    <span class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 flex items-center justify-center text-sm">
                        <i class="bi bi-pause-circle"></i>
                    </span>
                </div>
                <div class="mt-3">
                    <p class="text-2xl font-heading font-extrabold text-slate-700 dark:text-slate-300 tabular-nums">
                        {{ $stats['draft'] ?? 0 }}
                    </p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Chưa xuất bản</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Conflict Warning Matrix if any --}}
    @if(!empty($conflicts))
    <div class="rounded-2xl border border-amber-300 dark:border-amber-700/80 bg-amber-50/80 dark:bg-amber-950/40 p-4 sm:p-5 shadow-xs">
        <div class="flex items-start gap-3.5">
            <span class="w-9 h-9 rounded-xl bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-300 flex items-center justify-center shrink-0">
                <i class="bi bi-exclamation-triangle-fill text-lg"></i>
            </span>
            <div class="space-y-1.5 flex-1 min-w-0">
                <h4 class="font-heading font-bold text-amber-900 dark:text-amber-200 text-sm">
                    Phát hiện trùng lịch giữa các sự kiện (Conflict Resolution Matrix)
                </h4>
                <p class="text-xs text-amber-800 dark:text-amber-300 leading-relaxed">
                    Hệ thống tự động giải quyết xung đột bằng cách ưu tiên chủ đề có <strong>Priority (độ ưu tiên) cao hơn</strong> tại cùng một thời điểm.
                </p>
                <div class="mt-3 space-y-2 pt-1">
                    @foreach($conflicts as $cf)
                        <div class="flex items-center gap-2.5 flex-wrap bg-white/90 dark:bg-slate-800/90 p-2.5 rounded-xl border border-amber-200/80 dark:border-amber-800/60 text-xs">
                            <span class="font-bold text-slate-800 dark:text-slate-200">{{ $cf['theme_a']['name'] }} <span class="font-mono text-pcrm-600">(P{{ $cf['theme_a']['priority'] }})</span></span>
                            <span class="text-slate-400 font-semibold">so với</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200">{{ $cf['theme_b']['name'] }} <span class="font-mono text-pcrm-600">(P{{ $cf['theme_b']['priority'] }})</span></span>
                            <span class="text-slate-400">→</span>
                            <span class="badge badge-success text-[11px] font-bold">Thắng: {{ $cf['winner_name'] }}</span>
                            <span class="text-[11px] text-slate-500 dark:text-slate-400">({{ $cf['reason'] }})</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Filter & Search Toolbar --}}
    <div class="card p-3 sm:p-4 border border-slate-200/90 dark:border-slate-700/80 shadow-xs">
        @php
            $isFilterActive = request()->anyFilled(['search', 'status', 'level']);
        @endphp
        <form action="{{ route('themes.index') }}" method="GET" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex flex-col sm:flex-row sm:items-center gap-2.5 flex-1">
                {{-- Search --}}
                <div class="relative flex-1 min-w-[200px]">
                    <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Tìm kiếm theo tên chủ đề, slug..."
                           class="form-input h-9 text-sm pl-8 pr-3 w-full">
                </div>

                {{-- Status Filter --}}
                <div class="w-full sm:w-44 shrink-0">
                    <select name="status" class="form-input h-9 text-sm w-full" onchange="this.form.submit()">
                        <option value="">Tất cả trạng thái</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Đang chạy (Active)</option>
                        <option value="scheduled" {{ request('status') === 'scheduled' ? 'selected' : '' }}>Đã lên lịch (Scheduled)</option>
                        <option value="paused" {{ request('status') === 'paused' ? 'selected' : '' }}>Tạm dừng (Paused)</option>
                        <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Bản nháp (Draft)</option>
                    </select>
                </div>

                {{-- Level Filter --}}
                <div class="w-full sm:w-48 shrink-0">
                    <select name="level" class="form-input h-9 text-sm w-full" onchange="this.form.submit()">
                        <option value="">Tất cả cấp độ</option>
                        <option value="1" {{ request('level') === '1' ? 'selected' : '' }}>Level 1 — Corporate</option>
                        <option value="2" {{ request('level') === '2' ? 'selected' : '' }}>Level 2 — Celebration</option>
                        <option value="3" {{ request('level') === '3' ? 'selected' : '' }}>Level 3 — Company Event</option>
                    </select>
                </div>

                <button type="submit" class="btn-secondary h-9 px-3.5 text-xs inline-flex items-center gap-1.5 shrink-0">
                    <i class="bi bi-funnel text-xs"></i> Lọc
                </button>
                @if($isFilterActive)
                    <a href="{{ route('themes.index') }}" class="btn-secondary h-9 px-2.5 text-xs inline-flex items-center gap-1 shrink-0" title="Xóa bộ lọc">
                        <i class="bi bi-x text-sm"></i>
                    </a>
                @endif
            </div>

            <span class="text-xs text-slate-400 dark:text-slate-500 shrink-0 self-center sm:self-auto">
                {{ $themes->total() }} chủ đề
            </span>
        </form>
    </div>

    {{-- Themes Data Table Card --}}
    <div class="card overflow-hidden border border-slate-200/90 dark:border-slate-700/80 shadow-xs">
        <div class="card-header flex items-center justify-between px-5 py-3.5 border-b border-slate-200/90 dark:border-slate-700/80">
            <div class="flex items-center gap-2.5">
                <h4 class="font-heading font-bold text-base text-slate-900 dark:text-white">
                    Danh sách chủ đề & sự kiện
                </h4>
                <span class="badge badge-neutral text-xs font-bold">{{ $themes->total() }}</span>
            </div>
            <span class="text-xs text-slate-400">Tự động ưu tiên sự kiện đang hoạt động</span>
        </div>

        <div class="table-container border-0 rounded-none overflow-x-auto">
            <table class="table-base w-full">
                <thead>
                    <tr>
                        <th class="table-th min-w-[200px] !px-3">Chủ đề & Màu sắc</th>
                        <th class="table-th w-32 !px-2.5 whitespace-nowrap">Cấp độ</th>
                        <th class="table-th w-44 !px-2.5 whitespace-nowrap">Lịch áp dụng</th>
                        <th class="table-th w-28 !px-2.5 whitespace-nowrap">Phạm vi</th>
                        <th class="table-th w-28 !px-2.5 whitespace-nowrap">Trạng thái</th>
                        <th class="table-th text-right w-36 !px-3 whitespace-nowrap">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/80">
                    @forelse($themes as $theme)
                    @php
                        $isDef = $theme->slug === 'default';
                        $accent = $theme->config['colors']['accent'] ?? '#2F55E7';
                        $scopes = is_array($theme->scope) ? $theme->scope : [];
                    @endphp
                    <tr class="table-tr-hover">
                        {{-- Theme name & color --}}
                        <td class="table-td !px-3 whitespace-nowrap">
                            <div class="flex items-center gap-2.5">
                                <span class="w-3.5 h-3.5 rounded-full shrink-0 shadow-xs ring-2 ring-white dark:ring-slate-800 border border-black/10"
                                      style="background-color: {{ $accent }};"
                                      title="Màu Accent: {{ $accent }}"></span>
                                <div class="min-w-0">
                                    <p class="font-heading font-bold text-slate-900 dark:text-white text-sm whitespace-nowrap">
                                        {{ $theme->name }}
                                    </p>
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <span class="font-mono text-[10px] text-slate-400 bg-slate-50 dark:bg-slate-700/50 px-1 py-0.2 rounded border border-slate-200/60 dark:border-slate-700/60 whitespace-nowrap">
                                            {{ $theme->slug }}
                                        </span>
                                        @if($isDef)
                                            <span class="text-[10px] text-pcrm-600 dark:text-pcrm-400 font-semibold whitespace-nowrap">Chủ đề gốc</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>

                        {{-- Level --}}
                        <td class="table-td !px-2.5 whitespace-nowrap">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-semibold border whitespace-nowrap {{ $theme->getLevelBadgeClass() }}">
                                @if($theme->level == 1)
                                    <i class="bi bi-briefcase text-[10px]"></i>
                                @elseif($theme->level == 2)
                                    <i class="bi bi-balloon text-[10px]"></i>
                                @else
                                    <i class="bi bi-star text-[10px]"></i>
                                @endif
                                <span>{{ $theme->getLevelLabel() }}</span>
                            </span>
                        </td>

                        {{-- Schedule --}}
                        <td class="table-td !px-2.5 whitespace-nowrap text-xs text-slate-600 dark:text-slate-300">
                            @if($theme->start_at && $theme->end_at)
                                <div class="space-y-0.5 text-[11px] leading-tight whitespace-nowrap">
                                    <p class="flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                                        <span class="text-slate-400 text-[10px]">Từ:</span>
                                        <span class="font-medium">{{ $theme->start_at->format('d/m/Y H:i') }}</span>
                                    </p>
                                    <p class="flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500 shrink-0"></span>
                                        <span class="text-slate-400 text-[10px]">Đến:</span>
                                        <span class="font-medium">{{ $theme->end_at->format('d/m/Y H:i') }}</span>
                                    </p>
                                </div>
                            @else
                                <span class="inline-flex items-center gap-1 text-slate-400 italic text-[11px] whitespace-nowrap">
                                    <i class="bi bi-infinity"></i> Luôn sẵn sàng
                                </span>
                            @endif
                        </td>

                        {{-- Scope --}}
                        <td class="table-td !px-2.5 whitespace-nowrap">
                            @if(count($scopes) >= 3)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-700/60 text-xs font-medium text-slate-700 dark:text-slate-300 whitespace-nowrap" title="{{ implode(', ', $scopes) }}">
                                    <i class="bi bi-grid-fill text-[10px] text-pcrm-600"></i> Toàn bộ ({{ count($scopes) }})
                                </span>
                            @elseif(count($scopes) > 0)
                                <div class="flex items-center gap-1 whitespace-nowrap">
                                    @foreach($scopes as $sc)
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700/50 text-[10px] font-medium text-slate-600 dark:text-slate-300 whitespace-nowrap">
                                            @if($sc === 'login') Login
                                            @elseif(str_contains($sc, 'dashboard')) Dash
                                            @elseif(str_contains($sc, 'header')) Header
                                            @elseif($sc === 'notification') Báo cáo
                                            @else {{ $sc }}
                                            @endif
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-xs text-slate-400 whitespace-nowrap">Tất cả</span>
                            @endif
                        </td>

                        {{-- Status --}}
                        <td class="table-td !px-2.5 whitespace-nowrap">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold whitespace-nowrap shrink-0 {{ $theme->getStatusBadgeClass() }}">
                                <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ in_array($theme->status, ['active', 'scheduled']) ? 'bg-current animate-pulse' : 'bg-current' }}"></span>
                                <span class="whitespace-nowrap">{{ $theme->getStatusLabel() }}</span>
                            </span>
                        </td>

                        {{-- Actions --}}
                        <td class="table-td !px-3 text-right whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1">
                                {{-- Preview in Simulator --}}
                                <button type="button" onclick="openPreviewModal({{ $theme->id }}, '{{ addslashes($theme->name) }}')"
                                        class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-pcrm-600 hover:bg-slate-100 dark:hover:bg-slate-700 transition"
                                        title="Xem trước giả lập Sandbox">
                                    <i class="bi bi-eye text-sm"></i>
                                </button>

                                {{-- Edit --}}
                                <button type="button" onclick="openEditModal({{ json_encode($theme) }})"
                                        class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-pcrm-600 hover:bg-slate-100 dark:hover:bg-slate-700 transition"
                                        title="Chỉnh sửa chủ đề">
                                    <i class="bi bi-pencil text-sm"></i>
                                </button>

                                {{-- Toggle Active / Pause --}}
                                @if(!$isDef)
                                <form action="{{ route('themes.toggle', $theme) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-amber-600 hover:bg-slate-100 dark:hover:bg-slate-700 transition"
                                            title="{{ in_array($theme->status, ['active', 'scheduled']) ? 'Tạm dừng chủ đề' : 'Kích hoạt chủ đề' }}">
                                        <i class="bi {{ in_array($theme->status, ['active', 'scheduled']) ? 'bi-pause-circle' : 'bi-play-circle' }} text-sm"></i>
                                    </button>
                                </form>
                                @endif

                                {{-- Duplicate --}}
                                <form action="{{ route('themes.duplicate', $theme) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition"
                                            title="Nhân bản làm chủ đề mới">
                                        <i class="bi bi-copy text-sm"></i>
                                    </button>
                                </form>

                                {{-- Delete --}}
                                @if(!$isDef)
                                <button type="button" onclick="openDeleteModal({{ $theme->id }}, '{{ addslashes($theme->name) }}')"
                                        class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-red-600 hover:bg-slate-100 dark:hover:bg-slate-700 transition"
                                        title="Xóa chủ đề">
                                    <i class="bi bi-trash text-sm"></i>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="p-12 text-center text-slate-400 dark:text-slate-500">
                            <div class="flex flex-col items-center justify-center space-y-2">
                                <span class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-400 text-xl">
                                    <i class="bi bi-palette"></i>
                                </span>
                                <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">Không tìm thấy chủ đề nào</p>
                                <p class="text-xs text-slate-400">Thử thay đổi bộ lọc tìm kiếm hoặc tạo thêm chủ đề sự kiện mới.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($themes->hasPages())
        <div class="px-5 py-4 border-t border-slate-200/90 dark:border-slate-700/80 bg-slate-50/50 dark:bg-slate-800/40">
            {{ $themes->links() }}
        </div>
        @endif
    </div>

</div>

{{-- Modals --}}
@include('themes.partials.create-modal')
@include('themes.partials.edit-modal')
@include('themes.partials.preview-modal')
@include('themes.partials.rollback-modal')
@include('themes.partials.delete-modal')

@if($errors->any() && old('_modal'))
<script>
    document.addEventListener('DOMContentLoaded', function() {
        openModal('{{ old("_modal") }}');
    });
</script>
@endif

@endsection
