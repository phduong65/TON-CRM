@php
    $theme = $activeTheme ?? null;
    if (!$theme || empty($theme['id']) || ($theme['slug'] ?? 'default') === 'default') {
        return;
    }
    $content = $theme['content'] ?? [];
    $rawGreeting = $content['dashboardGreeting'] ?? 'Xin chào, :name 👋';
    $employeeName = auth()->user()?->employee?->name ?? auth()->user()?->name ?? 'Bạn';
    $greeting = str_replace([':name', '{{employee_name}}'], $employeeName, $rawGreeting);
    $accent = $theme['colors']['accent'] ?? '#2563EB';
    $accentSecondary = $theme['colors']['accentSecondary'] ?? '#F59E0B';
    $banners = $theme['visual']['banners'] ?? [];
    $horizontalBanner = $banners['dashboard_horizontal'] ?? null;
    $subtitle = $content['dashboardSubtitle'] ?? 'Khởi đầu ngày mới bứt phá mục tiêu và gặt hái nhiều thành công!';

    $slug = $theme['slug'] ?? '';
    $isTet = str_contains($slug, 'tet');
    $isQuocKhanh = str_contains($slug, 'quoc-khanh') || str_contains($slug, '2-9');
    $badgeTag = $isTet ? 'Xuân Bính Ngọ · TON Capital' : ($isQuocKhanh ? 'Kỷ Niệm 2/9 · Tự Hào Non Sông' : 'TON-HR Enterprise');
    $icon = $isTet ? '🧧' : ($isQuocKhanh ? '🇻🇳' : '✨');
@endphp

@if($horizontalBanner && file_exists(public_path($horizontalBanner)))
    {{-- Widescreen Rich Horizontal Hero Banner with Pixel-Perfect Aspect & Fit --}}
    <div class="group mb-5 rounded-2xl sm:rounded-3xl overflow-hidden shadow-lg border border-red-900/20 relative min-h-[170px] sm:min-h-[190px] md:h-[210px] flex items-center bg-[#6B0612] text-white">
        {{-- Right-Anchored Artwork with Cover Fit --}}
        <div class="absolute right-0 top-0 bottom-0 w-full sm:w-[70%] md:w-[58%] lg:w-[50%] h-full overflow-hidden pointer-events-none">
            <img src="{{ asset($horizontalBanner) }}"
                 alt="{{ $theme['name'] }}"
                 class="w-full h-full object-cover object-right select-none pointer-events-none transition-transform duration-700 group-hover:scale-105">
            {{-- Seamless horizontal fade into background color --}}
            <div class="absolute inset-y-0 left-0 w-24 sm:w-36 bg-gradient-to-r from-[#6B0612] to-transparent pointer-events-none"></div>
        </div>

        {{-- Gradient Overlay for 100% Typography Contrast on Left Side --}}
        <div class="absolute inset-0 bg-gradient-to-r from-[#6B0612] via-[#6B0612]/95 sm:via-[#6B0612]/85 to-transparent w-full sm:w-3/4 md:w-3/5 pointer-events-none"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent pointer-events-none"></div>

        {{-- Content Overlay --}}
        <div class="relative z-10 p-5 sm:p-7 max-w-2xl space-y-2">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-extrabold uppercase tracking-wider bg-amber-400 text-red-950 shadow-xs">
                    <span class="w-1.5 h-1.5 rounded-full bg-red-600 animate-ping"></span>
                    {{ $theme['name'] }}
                </span>
                <span class="text-xs text-amber-200/90 font-medium">{{ $badgeTag }}</span>
            </div>

            <h2 class="text-lg sm:text-2xl font-black text-white tracking-tight drop-shadow-sm leading-snug">
                {{ $greeting }}
            </h2>

            <p class="text-xs sm:text-sm text-red-100/90 leading-relaxed drop-shadow-xs max-w-md line-clamp-2">
                {{ $subtitle }}
            </p>

            <div class="pt-1 flex items-center gap-2.5 flex-wrap">
                <a href="{{ route('shift-schedules.index') }}"
                   class="inline-flex items-center gap-1.5 text-xs font-bold px-3.5 py-1.5 rounded-xl bg-white/95 hover:bg-white text-red-900 shadow-sm transition hover:shadow">
                    <i class="bi bi-calendar-event"></i>
                    <span>Lịch trực & Ca làm</span>
                </a>
                <a href="{{ route('rankings.index') }}"
                   class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-xl bg-red-950/40 hover:bg-red-950/60 text-amber-200 border border-amber-300/30 transition">
                    <i class="bi bi-trophy"></i>
                    <span>Bảng vinh danh</span>
                </a>
            </div>
        </div>
    </div>
@else
    {{-- Clean Fallback Card --}}
    <div class="mb-4 rounded-2xl p-4 sm:p-5 relative overflow-hidden shadow-xs border border-slate-200/80 dark:border-slate-700/80 bg-gradient-to-r from-white via-slate-50 to-white dark:from-slate-800 dark:via-slate-800/90 dark:to-slate-800 flex items-center justify-between gap-4">
        <div class="relative z-10 flex items-center gap-3.5 min-w-0">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 text-lg shadow-2xs"
                 style="background-color: {{ $accent }}15; color: {{ $accent }}; border: 1px solid {{ $accent }}30;">
                {{ $icon }}
            </div>
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <h2 class="text-base font-bold text-slate-900 dark:text-white truncate">{{ $greeting }}</h2>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase tracking-wider"
                          style="background-color: {{ $accent }}20; color: {{ $accent }};">
                        {{ $theme['name'] }}
                    </span>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ $subtitle }}
                </p>
            </div>
        </div>

        <div class="hidden sm:block absolute right-0 top-0 bottom-0 w-32 pointer-events-none opacity-20"
             style="background: radial-gradient(circle at right center, {{ $accent }} 0%, transparent 70%);">
        </div>
    </div>
@endif
