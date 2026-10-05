@php
    $theme = $activeTheme ?? null;
    if (!$theme || empty($theme['id']) || empty($theme['visual'])) {
        return;
    }
    $visual = $theme['visual'];
    $colors = $theme['colors'] ?? [];
    $accent = $colors['accent'] ?? '#2563EB';
    $slug = $theme['slug'] ?? '';
    $animEnabled = !empty($visual['animation']['enabled']);
@endphp

{{-- Seasonal Decoration Container (Bounded to corner / borders, maximum 15-20% coverage) --}}
<div class="pointer-events-none absolute inset-0 overflow-hidden z-0 select-none opacity-90 transition-opacity duration-500" aria-hidden="true">

    @if(str_contains($slug, 'tet'))
        {{-- Tết: Delicate line-art apricot blossoms & soft red/gold lantern accent in top-right and bottom-left --}}
        <div class="absolute -top-6 -right-6 w-40 h-40 opacity-70 text-amber-500">
            <svg viewBox="0 0 160 160" fill="none" class="w-full h-full">
                <circle cx="120" cy="40" r="18" fill="rgba(245, 158, 11, 0.08)" stroke="currentColor" stroke-width="1.5" stroke-dasharray="2 2"/>
                <path d="M120 22 C115 28, 115 34, 120 40 C125 34, 125 28, 120 22 Z" fill="#EF4444" opacity="0.65"/>
                <path d="M120 40 C115 46, 115 52, 120 58 C125 52, 125 46, 120 40 Z" fill="#EF4444" opacity="0.65"/>
                <path d="M102 40 C108 35, 114 35, 120 40 C114 45, 108 45, 102 40 Z" fill="#F59E0B" opacity="0.75"/>
                <path d="M120 40 C126 35, 132 35, 138 40 C132 45, 126 45, 120 40 Z" fill="#F59E0B" opacity="0.75"/>
                <circle cx="120" cy="40" r="4" fill="#B45309"/>
                {{-- subtle branches --}}
                <path d="M100 20 Q115 35 140 30" stroke="#D97706" stroke-width="1.5" stroke-linecap="round"/>
                <path d="M120 45 Q125 70 145 80" stroke="#D97706" stroke-width="1.2" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="absolute -bottom-8 -left-8 w-44 h-44 opacity-60 text-rose-500">
            <svg viewBox="0 0 160 160" fill="none" class="w-full h-full">
                <path d="M30 140 Q60 110 80 120" stroke="#E11D48" stroke-width="1.5" stroke-linecap="round"/>
                <circle cx="70" cy="115" r="3" fill="#F59E0B"/>
                <circle cx="45" cy="130" r="2.5" fill="#EF4444"/>
            </svg>
        </div>

    @elseif(str_contains($slug, 'quoc-khanh') || str_contains($slug, '2-9') || str_contains($slug, '30-4'))
        {{-- National Days: Elegant ribbon shape & subtle star corner --}}
        <div class="absolute top-0 right-0 w-32 h-32 overflow-hidden pointer-events-none">
            <div class="absolute transform rotate-45 bg-gradient-to-r from-red-600 via-rose-600 to-amber-500 text-white shadow-xs font-semibold py-1 right-[-45px] top-[22px] w-[150px] text-center text-[9px] tracking-widest uppercase">
                🇻🇳 1945 - {{ now()->year }}
            </div>
        </div>

    @elseif(str_contains($slug, 'noel') || str_contains($slug, 'giang-sinh'))
        {{-- Noel: Minimalist pine branch & quiet snowflakes --}}
        <div class="absolute top-2 right-4 text-emerald-600/30 w-24 h-24">
            <svg viewBox="0 0 100 100" fill="none" class="w-full h-full">
                <path d="M50 10 L40 35 L46 35 L35 55 L43 55 L30 80 L70 80 L57 55 L65 55 L54 35 L60 35 Z" stroke="currentColor" stroke-width="1.5"/>
            </svg>
        </div>

    @elseif(str_contains($slug, 'phu-nu') || str_contains($slug, '8-3') || str_contains($slug, '20-10'))
        {{-- Women's Day: Soft floral pastel lines --}}
        <div class="absolute top-3 right-4 text-rose-400/40 w-28 h-28">
            <svg viewBox="0 0 100 100" fill="none" class="w-full h-full">
                <circle cx="50" cy="50" r="20" stroke="currentColor" stroke-width="1.2" stroke-dasharray="3 3"/>
                <path d="M50 20 Q55 40 50 50 Q45 40 50 20 Z" fill="rgba(244, 63, 94, 0.15)"/>
                <path d="M50 80 Q55 60 50 50 Q45 60 50 80 Z" fill="rgba(244, 63, 94, 0.15)"/>
                <path d="M20 50 Q40 55 50 50 Q40 45 20 50 Z" fill="rgba(244, 63, 94, 0.15)"/>
                <path d="M80 50 Q60 55 50 50 Q60 45 80 50 Z" fill="rgba(244, 63, 94, 0.15)"/>
            </svg>
        </div>

    @elseif(str_contains($slug, 'sinh-nhat') || str_contains($slug, 'birthday'))
        {{-- Company Birthday: Minimalist festive confetti shapes --}}
        <div class="absolute top-4 right-6 flex gap-2 opacity-50">
            <span class="w-2 h-2 rounded-full bg-amber-400"></span>
            <span class="w-2.5 h-1 rounded-sm bg-blue-500 transform rotate-45"></span>
            <span class="w-1.5 h-3 rounded-full bg-rose-400 transform -rotate-12"></span>
        </div>
    @endif

</div>
