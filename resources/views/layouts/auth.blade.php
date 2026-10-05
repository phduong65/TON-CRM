<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'TON-HR')) — {{ config('app.name', 'TON-HR') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />

    <script>
        if (sessionStorage.getItem('tonhrm_splash_shown') === '1' && window.location.search.indexOf('splash=1') === -1) {
            document.documentElement.classList.add('splash-dismissed');
        }
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        .auth-page {
            font-family: var(--font-sans, 'Be Vietnam Pro'), ui-sans-serif, system-ui, sans-serif;
            background-color: #F8FAFC;
        }
        @if(!empty($activeTheme['colors']['bgTint']) && $activeTheme['colors']['bgTint'] !== 'transparent')
        .auth-page {
            background-color: color-mix(in srgb, {{ $activeTheme['colors']['bgTint'] }} 4%, #F8FAFC);
        }
        @endif
        .auth-input {
            height: 48px;
            border: 1px solid #E5E7EB;
            border-radius: 10px;
            background-color: #FFFFFF;
            color: #111827;
            transition: border-color 200ms ease, box-shadow 200ms ease;
        }
        .auth-input::placeholder { color: #9CA3AF; }
        .auth-input:focus {
            outline: none;
            border-color: var(--theme-accent, #2563EB);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--theme-accent, #2563EB) 12%, transparent);
        }
        .auth-input.has-error { border-color: #DC2626; }
        .auth-btn-primary {
            height: 48px;
            border-radius: 10px;
            background-color: var(--theme-accent, #2563EB);
            color: #FFFFFF;
            font-weight: 600;
            transition: background-color 180ms ease, transform 120ms ease, box-shadow 180ms ease;
        }
        .auth-btn-primary:hover { filter: brightness(0.92); }
        .auth-btn-primary:active { transform: scale(0.99); }
        .auth-btn-social {
            height: 46px;
            border-radius: 10px;
            border: 1px solid #E5E7EB;
            background-color: #FFFFFF;
            color: #111827;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background-color 180ms ease, border-color 180ms ease, transform 120ms ease, box-shadow 180ms ease;
        }
        .auth-btn-social:hover { background-color: #F8FAFC; border-color: #CBD5E1; }
        .auth-btn-social:active { transform: scale(0.98); }
        .auth-card-illustration {
            background: linear-gradient(180deg, #EAF4FF, #DDEEFF);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, .06);
        }
        .auth-orbit {
            border: 1px solid rgba(37, 99, 235, 0.12);
            border-radius: 9999px;
            position: absolute;
        }
        .auth-glow {
            position: absolute;
            border-radius: 9999px;
            background: radial-gradient(circle, rgba(59,130,246,0.22) 0%, rgba(59,130,246,0) 70%);
        }
        .auth-chip {
            position: absolute;
            display: flex;
            align-items: center;
            gap: 8px;
            background-color: #FFFFFF;
            border-radius: 9999px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .08);
            padding: 10px 16px;
            font-size: 13px;
            font-weight: 600;
            color: #111827;
            white-space: nowrap;
            animation: auth-float 5s ease-in-out infinite;
        }
        .auth-chip i { color: #2563EB; font-size: 15px; }
        @keyframes auth-float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        .auth-chip-mid { animation-name: auth-float-mid; }
        @keyframes auth-float-mid {
            0%, 100% { transform: translateY(-50%); }
            50% { transform: translateY(calc(-50% - 10px)); }
        }
        @media (prefers-reduced-motion: reduce) {
            .auth-chip { animation: none; }
        }
    </style>
</head>
<body class="auth-page h-screen overflow-hidden relative">
    @include('components.splash-loader')
    <div class="h-screen grid xl:grid-cols-12 relative">

        {{-- ============ FORM COLUMN — right side ============ --}}
        <div class="xl:col-span-4 xl:order-2 flex flex-col h-screen overflow-y-auto px-4 sm:px-6 xl:px-8 py-4 sm:py-6 relative">

            {{-- Seasonal decoration slot (only in form column, non-intrusive) --}}
            @include('themes.slots.login-decoration')

            <div class="flex-1 flex items-start pt-8 sm:pt-12 xl:items-center xl:pt-0 relative z-10">
                <div class="w-full max-w-md mx-auto py-2 sm:py-6">
                    <a href="{{ url('/') }}" class="inline-flex items-center gap-3 mb-7 sm:mb-8 group" aria-label="TonHRM Trang chủ">
                        <x-tonhrm-logo type="primary" class="h-10 w-auto transition-transform group-hover:scale-105" />
                    </a>

                    <div class="pcrm-auth-content">
                        @yield('content')
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ ILLUSTRATION COLUMN — left side (wider dashboard preview) ============ --}}
        <div class="hidden xl:flex xl:col-span-8 xl:order-1 p-6 flex-col">
            @php
                $banners = $activeTheme['visual']['banners'] ?? [];
                $heroDesktop = $banners['login_hero_desktop'] ?? null;
            @endphp

            @if($heroDesktop && file_exists(public_path($heroDesktop)))
                @php
                    $slug = $activeTheme['slug'] ?? '';
                    $isTet = str_contains($slug, 'tet');
                    $isQuocKhanh = str_contains($slug, 'quoc-khanh') || str_contains($slug, '2-9');
                    $badgeTag = $isTet ? 'Xuân Bính Ngọ · TON Capital' : ($isQuocKhanh ? 'Kỷ Niệm 2/9 · Tự Hào Non Sông' : 'TON-HR Enterprise');
                    $pill1Icon = $isTet ? '🧧' : ($isQuocKhanh ? '🇻🇳' : '✨');
                    $pill1Title = $isTet ? 'Chúc Mừng Năm Mới' : ($isQuocKhanh ? 'Kỷ Niệm Quốc Khánh' : 'Hệ Thống Nhân Sự');
                    $pill1Sub = $isTet ? 'An Khang Thịnh Vượng' : ($isQuocKhanh ? 'Tự Hào Non Sông Việt Nam' : 'TON-HR Enterprise');
                    $pill2Title = $isTet ? 'Chấm Công & Ca Tết' : ($isQuocKhanh ? 'Ca Làm & Trực Lễ' : 'Xếp Ca & Chấm Công');
                    $pill3Title = $isTet ? 'Thưởng Tết & Điểm' : ($isQuocKhanh ? 'Thi Đua & Thành Tích' : 'Điểm Thưởng & Vinh Danh');
                @endphp
                {{-- Festive Hero Artwork Container with Perfect Responsive Fit --}}
                <div class="group relative w-full flex-1 min-h-[520px] overflow-hidden rounded-[32px] shadow-2xl flex flex-col justify-between p-8 border border-white/10 bg-slate-950">
                    {{-- High-Resolution Artwork Image with Proper Object Fit --}}
                    <img src="{{ asset($heroDesktop) }}"
                         alt="{{ $activeTheme['name'] ?? 'Theme Hero' }}"
                         class="absolute inset-0 w-full h-full object-cover object-center select-none pointer-events-none transition-transform duration-1000 group-hover:scale-105">

                    {{-- Elegant Vignette Overlays for Maximum Text Contrast --}}
                    <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/30 to-black/55 pointer-events-none"></div>

                    {{-- Top Header Pill --}}
                    <div class="relative z-10 flex items-center justify-between">
                        <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-black/45 backdrop-blur-md border border-white/20 text-white shadow-lg">
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-400 animate-pulse"></span>
                            <span class="text-xs font-bold uppercase tracking-wider text-amber-300">{{ $activeTheme['name'] ?? 'Lễ Hội' }}</span>
                            <span class="text-xs text-slate-200">· {{ $badgeTag }}</span>
                        </div>

                        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/10 backdrop-blur-md border border-white/15 text-xs text-white/90">
                            <i class="bi bi-shield-check text-emerald-400"></i>
                            <span>TON-HR Enterprise</span>
                        </div>
                    </div>

                    {{-- Floating Feature Badges --}}
                    <div class="relative z-10 grid grid-cols-3 gap-3">
                        <div class="bg-black/55 backdrop-blur-md border border-white/15 p-4 rounded-2xl text-white shadow-xl flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-red-600/40 border border-red-500/50 flex items-center justify-center text-xl shrink-0">
                                {{ $pill1Icon }}
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-amber-300 truncate">{{ $pill1Title }}</p>
                                <p class="text-[11px] text-slate-300 truncate">{{ $pill1Sub }}</p>
                            </div>
                        </div>

                        <div class="bg-black/55 backdrop-blur-md border border-white/15 p-4 rounded-2xl text-white shadow-xl flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-amber-600/40 border border-amber-500/50 flex items-center justify-center text-xl shrink-0 text-amber-300">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-white truncate">{{ $pill2Title }}</p>
                                <p class="text-[11px] text-slate-300 truncate">Tự động chuẩn xác</p>
                            </div>
                        </div>

                        <div class="bg-black/55 backdrop-blur-md border border-white/15 p-4 rounded-2xl text-white shadow-xl flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-emerald-600/40 border border-emerald-500/50 flex items-center justify-center text-xl shrink-0 text-emerald-300">
                                <i class="bi bi-trophy"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-white truncate">{{ $pill3Title }}</p>
                                <p class="text-[11px] text-slate-300 truncate">Ghi nhận xứng đáng</p>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="auth-card-illustration relative w-full flex-1 overflow-hidden flex items-center justify-center">

                    {{-- concentric circles --}}
                    <div class="auth-orbit" style="width:680px; height:680px;"></div>
                    <div class="auth-orbit" style="width:500px; height:500px;"></div>
                    <div class="auth-orbit" style="width:320px; height:320px;"></div>

                    {{-- soft glow --}}
                    <div class="auth-glow" style="width:500px; height:500px;"></div>

                    {{-- feature chips — các tính năng chính của hệ thống --}}
                    <div class="auth-chip" style="top:56px; left:32px; animation-delay:0s;">
                        <i class="bi bi-people-fill"></i><span>Quản lý nhân viên</span>
                    </div>
                    <div class="auth-chip" style="top:56px; right:32px; animation-delay:.4s;">
                        <i class="bi bi-geo-alt-fill"></i><span>Chấm công GPS</span>
                    </div>
                    <div class="auth-chip auth-chip-mid" style="top:50%; left:4px; animation-delay:.8s;">
                        <i class="bi bi-file-earmark-text-fill"></i><span>Xử lý vi phạm</span>
                    </div>
                    <div class="auth-chip auth-chip-mid" style="top:50%; right:4px; animation-delay:1.2s;">
                        <i class="bi bi-trophy-fill"></i><span>Xếp hạng &amp; điểm</span>
                    </div>
                    <div class="auth-chip" style="bottom:56px; left:32px; animation-delay:1.6s;">
                        <i class="bi bi-exclamation-triangle-fill"></i><span>Cảnh báo Vùng đỏ</span>
                    </div>
                    <div class="auth-chip" style="bottom:56px; right:32px; animation-delay:2s;">
                        <i class="bi bi-bar-chart-fill"></i><span>Báo cáo &amp; Export</span>
                    </div>

                {{-- center mock dashboard preview --}}
                <div class="relative bg-white rounded-[24px] shadow-[0_20px_45px_rgba(15,23,42,.14)] w-[380px] p-6">
                    {{-- title bar --}}
                    <div class="flex items-center gap-1.5 mb-3">
                        <span class="w-2.5 h-2.5 rounded-full bg-red-400"></span>
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
                        <span class="ml-auto text-[11px] font-semibold text-[#9CA3AF]">P-CRM Dashboard</span>
                        <span class="relative inline-flex ml-2">
                            <i class="bi bi-bell-fill text-[12px] text-[#9CA3AF]"></i>
                            <span class="absolute -top-0.5 -right-0.5 w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                        </span>
                    </div>
                    <p class="text-[11px] text-[#9CA3AF] mb-3">Xin chào, Quản trị viên 👋</p>

                    {{-- mini stat grid — 4 chỉ số chính --}}
                    <div class="grid grid-cols-2 gap-2 mb-3">
                        <div class="rounded-xl bg-blue-50 p-2.5">
                            <div class="flex items-center justify-between">
                                <p class="text-[16px] font-bold text-blue-600">128</p>
                                <i class="bi bi-people-fill text-blue-400 text-[12px]"></i>
                            </div>
                            <p class="text-[10px] text-[#6B7280] mt-0.5">Nhân viên</p>
                        </div>
                        <div class="rounded-xl bg-amber-50 p-2.5">
                            <div class="flex items-center justify-between">
                                <p class="text-[16px] font-bold text-amber-600">12</p>
                                <i class="bi bi-hourglass-split text-amber-400 text-[12px]"></i>
                            </div>
                            <p class="text-[10px] text-[#6B7280] mt-0.5">Chờ duyệt</p>
                        </div>
                        <div class="rounded-xl bg-emerald-50 p-2.5">
                            <div class="flex items-center justify-between">
                                <p class="text-[16px] font-bold text-emerald-600">98%</p>
                                <i class="bi bi-check-circle-fill text-emerald-400 text-[12px]"></i>
                            </div>
                            <p class="text-[10px] text-[#6B7280] mt-0.5">Đúng giờ</p>
                        </div>
                        <div class="rounded-xl bg-rose-50 p-2.5">
                            <div class="flex items-center justify-between">
                                <p class="text-[16px] font-bold text-rose-600">5</p>
                                <i class="bi bi-exclamation-triangle-fill text-rose-400 text-[12px]"></i>
                            </div>
                            <p class="text-[10px] text-[#6B7280] mt-0.5">Vùng đỏ</p>
                        </div>
                    </div>

                    {{-- mini chart with day labels --}}
                    <div class="mb-3">
                        <div class="flex items-center justify-between mb-1.5">
                            <p class="text-[10px] font-semibold text-[#6B7280]">Chấm công tuần này</p>
                            <p class="text-[10px] font-semibold text-blue-600">+12%</p>
                        </div>
                        <div class="flex items-end gap-1.5 h-14">
                            <span class="flex-1 rounded-t bg-blue-200" style="height:45%"></span>
                            <span class="flex-1 rounded-t bg-blue-300" style="height:70%"></span>
                            <span class="flex-1 rounded-t bg-blue-500" style="height:100%"></span>
                            <span class="flex-1 rounded-t bg-blue-300" style="height:60%"></span>
                            <span class="flex-1 rounded-t bg-blue-200" style="height:35%"></span>
                            <span class="flex-1 rounded-t bg-blue-400" style="height:80%"></span>
                            <span class="flex-1 rounded-t bg-blue-300" style="height:55%"></span>
                        </div>
                        <div class="flex gap-1.5 mt-1">
                            <span class="flex-1 text-center text-[8px] text-[#9CA3AF]">T2</span>
                            <span class="flex-1 text-center text-[8px] text-[#9CA3AF]">T3</span>
                            <span class="flex-1 text-center text-[8px] text-[#9CA3AF]">T4</span>
                            <span class="flex-1 text-center text-[8px] text-[#9CA3AF]">T5</span>
                            <span class="flex-1 text-center text-[8px] text-[#9CA3AF]">T6</span>
                            <span class="flex-1 text-center text-[8px] text-[#9CA3AF]">T7</span>
                            <span class="flex-1 text-center text-[8px] text-[#9CA3AF]">CN</span>
                        </div>
                    </div>

                    {{-- redzone alert banner --}}
                    <div class="flex items-center gap-2 rounded-xl bg-rose-50 px-3 py-2 mb-3">
                        <i class="bi bi-exclamation-triangle-fill text-rose-500 text-[12px]"></i>
                        <p class="text-[10px] font-semibold text-rose-600">5 nhân viên đang trong Vùng đỏ</p>
                    </div>

                    {{-- mini leaderboard --}}
                    <p class="text-[10px] font-semibold text-[#6B7280] mb-2">Bảng xếp hạng tháng</p>
                    <div class="space-y-1.5 mb-3">
                        <div class="flex items-center gap-2">
                            <span class="w-5 h-5 shrink-0 rounded-full bg-amber-400 flex items-center justify-center text-[9px] font-bold text-white">1</span>
                            <span class="w-6 h-6 shrink-0 rounded-full bg-blue-100 flex items-center justify-center text-[9px] font-bold text-blue-600">NV</span>
                            <span class="h-2 rounded-full bg-slate-100 flex-1"></span>
                            <span class="text-[9px] font-semibold text-blue-600 whitespace-nowrap">98đ</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-5 h-5 shrink-0 rounded-full bg-slate-300 flex items-center justify-center text-[9px] font-bold text-white">2</span>
                            <span class="w-6 h-6 shrink-0 rounded-full bg-emerald-100 flex items-center justify-center text-[9px] font-bold text-emerald-600">TL</span>
                            <span class="h-2 rounded-full bg-slate-100 flex-1"></span>
                            <span class="text-[9px] font-semibold text-blue-600 whitespace-nowrap">95đ</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-5 h-5 shrink-0 rounded-full bg-orange-300 flex items-center justify-center text-[9px] font-bold text-white">3</span>
                            <span class="w-6 h-6 shrink-0 rounded-full bg-violet-100 flex items-center justify-center text-[9px] font-bold text-violet-600">KT</span>
                            <span class="h-2 rounded-full bg-slate-100 flex-1"></span>
                            <span class="text-[9px] font-semibold text-blue-600 whitespace-nowrap">91đ</span>
                        </div>
                    </div>

                    {{-- progress footer --}}
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <p class="text-[10px] font-semibold text-[#6B7280]">Phiếu phạt đã xử lý</p>
                            <p class="text-[10px] font-semibold text-emerald-600">82%</p>
                        </div>
                        <div class="h-1.5 rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full rounded-full bg-emerald-400" style="width:82%"></div>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    <script>
        function togglePasswordVisibility(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            icon.classList.toggle('bi-eye', !isHidden);
            icon.classList.toggle('bi-eye-slash', isHidden);
        }
    </script>
</body>
</html>
