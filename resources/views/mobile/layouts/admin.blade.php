<!DOCTYPE html>
<html lang="vi" class="{{ auth()->check() && auth()->user()->theme === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        window.FIREBASE_CONFIG = {!! json_encode(array_merge(
            config('services.firebase.web', []),
            ['vapidKey' => config('services.firebase.vapid_key')]
        )) !!};
    </script>
    <script>
        if (localStorage.getItem('sidebarCollapsed') === '1') {
            document.documentElement.classList.add('sidebar-collapsed');
        }
        if (sessionStorage.getItem('tonhrm_splash_shown') === '1' && window.location.search.indexOf('splash=1') === -1) {
            document.documentElement.classList.add('splash-dismissed');
        }
    </script>
    <title>@yield('title', config('app.name', 'TON-HR'))</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('assets/images/TON CAPITAL_LOGO-06.png')}}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=be-vietnam-pro:300,400,500,600,700,800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/phosphor-icons/1.4.2/css/phosphor.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --theme-accent: {{ $activeTheme['colors']['accent'] ?? '#2563EB' }};
            --theme-accent-contrast: {{ $activeTheme['colors']['accentContrast'] ?? '#FFFFFF' }};
            --theme-bg-tint: {{ $activeTheme['colors']['bgTint'] ?? 'transparent' }};
        }
        .theme-active-nav {
            color: var(--theme-accent, #2563EB) !important;
        }
        .theme-accent-btn {
            background: linear-gradient(135deg, var(--theme-accent, #3b82f6), color-mix(in srgb, var(--theme-accent, #2563eb) 80%, black)) !important;
        }
    </style>

    @stack('styles')
</head>
<body class="bg-[#EAF3FF] dark:bg-slate-900 min-h-screen" style="@if(!empty($activeTheme['colors']['bgTint']) && $activeTheme['colors']['bgTint'] !== 'transparent') background-color: color-mix(in srgb, {{ $activeTheme['colors']['bgTint'] }} 30%, #EAF3FF); @endif">

    @include('components.splash-loader')

    @if(session('impersonator_id'))
    <div class="bg-amber-500 text-white text-sm px-4 py-2 flex items-center justify-center gap-3 flex-wrap">
        <span><i class="bi bi-person-badge"></i> Đang đăng nhập hộ <strong>{{ auth()->user()->name }}</strong></span>
        <form action="{{ route('impersonate.leave') }}" method="POST" class="inline">
            @csrf @method('DELETE')
            <button type="submit" class="underline font-semibold hover:no-underline">Thoát chế độ đăng nhập hộ</button>
        </form>
    </div>
    @endif

    {{-- Bản mobile legacy KHÔNG hiển thị topbar: chuông thông báo, hồ sơ, cài đặt,
         đăng xuất... đều truy cập qua tab "Thêm" (menu.index) ở bottom nav. --}}

    <!-- Body layout: left panel + main content -->
    <div class="flex h-screen">

        <!-- Left Profile Panel (includes mobile drawer panel) -->
        @include('components.sidebar')

        <!-- Main content scroll area with bottom nav padding -->
        <div class="flex-1 overflow-y-auto flex flex-col pb-24">
            <main class="flex-1 p-3 pcrm-animate-in">
                @hasSection('page-title')
                <div class="mb-4 bg-white dark:bg-slate-800 border border-blue-100 dark:border-slate-700 rounded-2xl p-4 shadow-[0_4px_20px_rgba(37,99,235,0.08)]">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0 flex-1 basis-32">
                            <h1 class="text-base font-extrabold text-[#0B1F5C] dark:text-white tracking-tight leading-tight">@yield('page-title')</h1>
                            @hasSection('page-subtitle')
                            <p class="hidden">@yield('page-subtitle')</p>
                            @endif
                        </div>
                        @hasSection('page-actions')
                        <div class="pcrm-page-actions !w-auto shrink-0 flex items-center gap-2 [&>*]:!w-auto">
                            @yield('page-actions')
                        </div>
                        @endif
                    </div>
                </div>
                @endif
                <div class="pcrm-page-content" data-page="{{ str_replace('.', '-', request()->route()?->getName() ?? 'page') }}">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    @php
        $bottomUser = auth()->user();
        $canCheckin = $bottomUser && $bottomUser->can('checkin-attendance') && $bottomUser->canSeeSelfAttendance();
        
        // Slot 2: Lịch làm / Xếp ca / Nhân viên
        $slot2Link = route('dashboard');
        $slot2Label = 'Lịch làm';
        $slot2Icon = 'bi-calendar3';
        $slot2Active = false;
        if ($bottomUser) {
            if ($bottomUser->can('view-own-schedule')) {
                $slot2Link = route('my-schedule.index');
                $slot2Active = request()->routeIs('my-schedule.*');
            } elseif ($bottomUser->can('view-shift-schedules')) {
                $slot2Link = route('shift-schedules.index');
                $slot2Label = 'Xếp ca';
                $slot2Icon = 'bi-calendar-week';
                $slot2Active = request()->routeIs('shift-schedules.*');
            } elseif ($bottomUser->can('view-employees')) {
                $slot2Link = route('employees.index');
                $slot2Label = 'Nhân viên';
                $slot2Icon = 'bi-people';
                $slot2Active = request()->routeIs('employees.*');
            }
        }

        // Slot 4: Yêu cầu (Đơn từ) / Xử phạt / Bảng xếp hạng
        $slot4Link = route('rankings.index');
        $slot4Label = 'Bảng XH';
        $slot4Icon = 'bi-trophy';
        $slot4Active = request()->routeIs('rankings.*');
        if ($bottomUser) {
            if ($bottomUser->canAny(['view-staff-requests', 'view-leave-requests', 'view-shift-swaps'])) {
                $slot4Link = route('staff-requests.index');
                $slot4Label = 'Đơn từ';
                $slot4Icon = 'bi-file-earmark-text';
                $slot4Active = request()->routeIs('staff-requests.*') || request()->routeIs('leave-requests.*') || request()->routeIs('shift-swap-requests.*');
            } elseif ($bottomUser->can('view-penalties')) {
                $slot4Link = route('penalties.index');
                $slot4Label = 'Xử phạt';
                $slot4Icon = 'bi-hammer';
                $slot4Active = request()->routeIs('penalties.*');
            }
        }
    @endphp

    <!-- Mobile Bottom Navigation Bar -->
    <div class="fixed bottom-0 left-0 right-0 z-30 bg-white/90 dark:bg-slate-900/90 backdrop-blur-md border-t border-slate-200/50 dark:border-slate-800/50 px-4 pb-[calc(10px+env(safe-area-inset-bottom,0px))] pt-2 shadow-[0_-8px_30px_rgb(0,0,0,0.04)] dark:shadow-[0_-8px_30px_rgb(0,0,0,0.2)]">
        <div class="flex items-center justify-between relative max-w-lg mx-auto">
            <!-- 1. Trang chủ -->
            <a href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif class="flex flex-col items-center justify-center flex-1 py-1 transition-all duration-200 {{ request()->routeIs('dashboard') ? 'theme-active-nav font-bold' : 'text-[#64748B] hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' }}">
                <i class="bi {{ request()->routeIs('dashboard') ? 'bi-house-door-fill' : 'bi-house-door' }} text-[22px] mb-0.5"></i>
                <span class="font-sans text-[11px] font-bold">Trang chủ</span>
            </a>

            <!-- 2. Lịch làm / Xếp ca / Nhân viên -->
            <a href="{{ $slot2Link }}" @if($slot2Active) aria-current="page" @endif class="flex flex-col items-center justify-center flex-1 py-1 transition-all duration-200 {{ $slot2Active ? 'theme-active-nav font-bold' : 'text-[#64748B] hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' }}">
                <i class="bi {{ $slot2Icon }} text-[21px] mb-0.5"></i>
                <span class="font-sans text-[11px] font-bold">{{ $slot2Label }}</span>
            </a>

            <!-- 3. Chấm công (Center, Floating, Large) -->
            <div class="flex-1 flex justify-center -mt-7 relative z-10">
                @if ($canCheckin)
                    <a href="{{ route('attendance.index') }}" class="group relative flex items-center justify-center w-14 h-14 rounded-full bg-blue-100 dark:bg-blue-950 text-blue-600 dark:text-blue-300 ring-4 ring-white dark:ring-slate-900 shadow-[0_4px_16px_rgba(37,99,235,0.2)] transition-all duration-300 hover:scale-110 active:scale-95 {{ request()->routeIs('attendance.*') ? 'ring-4 ring-offset-2 ring-amber-400 dark:ring-offset-slate-900' : '' }}">
                                                <i class="bi bi-fingerprint text-[28px] relative z-10"></i>
                    </a>
                @else
                    <button onclick="pcrmAlert('info', 'Thông báo', 'Tài khoản của bạn không hỗ trợ tự chấm công.')" class="group relative flex items-center justify-center w-14 h-14 rounded-full bg-gradient-to-tr from-slate-400 to-slate-500 text-white shadow-[0_4px_20px_rgba(100,116,139,0.3)] transition-all duration-300 cursor-not-allowed">
                        <i class="bi bi-fingerprint text-[28px] relative z-10"></i>
                    </button>
                @endif
                <span class="absolute bottom-[-22px] whitespace-nowrap font-sans text-[11px] font-bold tracking-wide {{ request()->routeIs('attendance.*') ? 'theme-active-nav' : 'text-slate-500 dark:text-slate-400' }}">Chấm công</span>
            </div>

            <!-- 4. Yêu cầu (Đơn từ) / Xử phạt / Bảng xếp hạng -->
            <a href="{{ $slot4Link }}" @if($slot4Active) aria-current="page" @endif class="flex flex-col items-center justify-center flex-1 py-1 transition-all duration-200 {{ $slot4Active ? 'theme-active-nav font-bold' : 'text-[#64748B] hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' }}">
                <i class="bi {{ $slot4Icon }}{{ $slot4Active && $slot4Icon === 'bi-file-earmark-text' ? '-fill' : '' }} text-[22px] mb-0.5"></i>
                <span class="font-sans text-[11px] font-bold">{{ $slot4Label }}</span>
            </a>

            <!-- 5. Thêm (Menu) -->
            <a href="{{ route('menu.index') }}" @if(request()->routeIs('menu.index')) aria-current="page" @endif class="flex flex-col items-center justify-center flex-1 py-1 transition-all duration-200 {{ request()->routeIs('menu.index') ? 'theme-active-nav font-bold' : 'text-[#64748B] hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' }}">
                <i class="bi {{ request()->routeIs('menu.index') ? 'bi-grid-fill' : 'bi-grid' }} text-[22px] mb-0.5"></i>
                <span class="font-sans text-[11px] font-bold">Thêm</span>
            </a>
        </div>
    </div>

    <!-- ── TON-HR Alert Modal ── -->
    <div id="pcrm-alert-overlay"
         class="hidden fixed inset-0 z-[9999] flex items-center justify-center p-4"
         style="background:rgba(0,0,0,0.55); backdrop-filter:blur(2px);">
        <div id="pcrm-alert-box"
             class="relative bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">

            <!-- Colored top stripe -->
            <div id="pcrm-alert-stripe" class="h-1.5 w-full"></div>

            <!-- Body -->
            <div class="px-6 pt-7 pb-6 text-center">
                <!-- Icon -->
                <div id="pcrm-alert-icon-wrap"
                     class="pcrm-alert-icon-wrap w-20 h-20 rounded-full mx-auto mb-5 flex items-center justify-center">
                    <svg id="pcrm-alert-icon-svg" viewBox="0 0 52 52" class="w-10 h-10" fill="none"></svg>
                </div>

                <!-- Title -->
                <h3 id="pcrm-alert-title"
                    class="text-lg font-bold text-slate-900 dark:text-white mb-2 leading-snug"></h3>

                <!-- Message -->
                <p id="pcrm-alert-message"
                   class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed"></p>
            </div>

            <!-- Button -->
            <div class="px-6 pb-6">
                <button id="pcrm-alert-btn"
                        onclick="pcrmAlertClose()"
                        class="w-full py-2.5 rounded-xl text-sm font-semibold text-white transition-all duration-150 hover:opacity-90 active:scale-95">
                    OK
                </button>
            </div>

            <!-- Timer bar -->
            <div class="h-1 bg-slate-100 dark:bg-slate-700">
                <div id="pcrm-alert-timer-bar"
                     class="h-full rounded-full transition-none"
                     style="width:100%"></div>
            </div>
        </div>
    </div>
    <!-- ── /TON-HR Alert Modal ── -->

    {{-- Đặt ngoài <main> (có animation/transform) để popup nằm trên thanh điều hướng đáy --}}
    <x-attendance-alert-banner />

    @stack('modals')
    @stack('scripts')

    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    <script>
    (function () {
        // Auto-stagger: cards inside .aos-stagger containers get incremental delays
        document.querySelectorAll('.aos-stagger').forEach(function (container) {
            container.querySelectorAll('[data-aos]').forEach(function (el, i) {
                if (!el.hasAttribute('data-aos-delay')) {
                    el.setAttribute('data-aos-delay', String(i * 70));
                }
            });
        });
        AOS.init({ duration: 260, once: true, offset: 30, easing: 'ease-out-quart' });
    })();
    </script>

    <script>
    // ── Modal helpers ──────────────────────────────────────────────────────
    // Ngữ nghĩa accessibility + focus tập trung, đồng bộ với layout web responsive
    // (xem ui-professionalization-spec §5.2).
    let _modalLastFocus = null;
    function _ensureDialogSemantics(el) {
        if (el.getAttribute('role') !== 'dialog') el.setAttribute('role', 'dialog');
        el.setAttribute('aria-modal', 'true');
        if (!el.hasAttribute('aria-labelledby')) {
            const heading = el.querySelector('h1, h2, h3, h4, [data-modal-title]');
            if (heading) {
                if (!heading.id) heading.id = el.id + '-title';
                el.setAttribute('aria-labelledby', heading.id);
            } else {
                el.setAttribute('aria-label', 'Hộp thoại');
            }
        }
    }
    function openModal(id) {
        const el = document.getElementById(id);
        if (!el) return;
        _ensureDialogSemantics(el);
        _modalLastFocus = document.activeElement;
        el.classList.remove('hidden');
        el.classList.add('flex');
        const focusable = el.querySelector(
            'input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])'
        );
        if (focusable) {
            focusable.focus({ preventScroll: true });
        } else {
            if (!el.hasAttribute('tabindex')) el.setAttribute('tabindex', '-1');
            el.focus({ preventScroll: true });
        }
    }
    function closeModal(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.add('hidden');
        el.classList.remove('flex');
        if (_modalLastFocus && typeof _modalLastFocus.focus === 'function' && document.contains(_modalLastFocus)) {
            _modalLastFocus.focus({ preventScroll: true });
        }
        _modalLastFocus = null;
    }
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('[role="dialog"]').forEach(function (dialog) {
            if (dialog.id && !dialog.classList.contains('hidden')) closeModal(dialog.id);
        });
    });
    // ── Filter panel toggle (mobile collapsible filter bar) ────────────
    function toggleEl(id) {
        const el = document.getElementById(id);
        if (el) el.classList.toggle('is-open');
    }

    // ── Chặn double-submit: disable nút bấm ngay khi form được gửi hợp lệ
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        const btn = (e.submitter && e.submitter.tagName === 'BUTTON') ? e.submitter : form.querySelector('button[type="submit"]');
        if (btn && !btn.disabled) {
            btn.disabled = true;
            btn.classList.add('opacity-60', 'cursor-not-allowed');
        }
    }, true);

    // ── TON-HR Alert System ────────────────────────────────────────────────
    (function () {
        var _timer = null;

        var CONFIG = {
            success: {
                icon: 'check',
                iconBg: '#d1fae5',       // emerald-100
                iconBgDark: '#064e3b33',
                iconColor: '#059669',    // emerald-600
                stripe: '#059669',
                btnBg: '#059669',
                btnHover: '#047857',
                duration: 2800,
            },
            error: {
                icon: 'x',
                iconBg: '#fee2e2',       // red-100
                iconBgDark: '#450a0a33',
                iconColor: '#dc2626',    // red-600
                stripe: '#dc2626',
                btnBg: '#dc2626',
                btnHover: '#b91c1c',
                duration: 0,             // no auto-close
            },
            warning: {
                icon: 'exclaim',
                iconBg: '#fef3c7',       // amber-100
                iconBgDark: '#451a0333',
                iconColor: '#d97706',    // amber-600
                stripe: '#d97706',
                btnBg: '#d97706',
                btnHover: '#b45309',
                duration: 3500,
            },
            info: {
                icon: 'info',
                iconBg: '#e0f2fe',       // sky-100
                iconBgDark: '#082f4933',
                iconColor: '#0284c7',    // sky-600
                stripe: '#0284c7',
                btnBg: '#0284c7',
                btnHover: '#0369a1',
                duration: 3000,
            },
        };

        function svgCheck(color) {
            return '<circle cx="26" cy="26" r="24" stroke="' + color + '" stroke-width="3.5" fill="none"/>'
                 + '<path class="pcrm-alert-check-path" d="M14 26 L22 35 L38 18" stroke="' + color + '" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>';
        }
        function svgX(color) {
            return '<circle cx="26" cy="26" r="24" stroke="' + color + '" stroke-width="3.5" fill="none"/>'
                 + '<line class="pcrm-alert-x-left"  x1="16" y1="16" x2="36" y2="36" stroke="' + color + '" stroke-width="4" stroke-linecap="round"/>'
                 + '<line class="pcrm-alert-x-right" x1="36" y1="16" x2="16" y2="36" stroke="' + color + '" stroke-width="4" stroke-linecap="round"/>';
        }
        function svgExclaim(color) {
            return '<circle cx="26" cy="26" r="24" stroke="' + color + '" stroke-width="3.5" fill="none"/>'
                 + '<g class="pcrm-alert-exclaim-path">'
                 + '<line x1="26" y1="13" x2="26" y2="30" stroke="' + color + '" stroke-width="4.5" stroke-linecap="round"/>'
                 + '<circle cx="26" cy="38" r="2.5" fill="' + color + '"/>'
                 + '</g>';
        }
        function svgInfo(color) {
            return '<circle cx="26" cy="26" r="24" stroke="' + color + '" stroke-width="3.5" fill="none"/>'
                 + '<g class="pcrm-alert-exclaim-path">'
                 + '<circle cx="26" cy="16" r="2.5" fill="' + color + '"/>'
                 + '<line x1="26" y1="23" x2="26" y2="38" stroke="' + color + '" stroke-width="4.5" stroke-linecap="round"/>'
                 + '</g>';
        }

        window.pcrmAlert = function (type, title, message, options) {
            var cfg = CONFIG[type] || CONFIG.info;
            var opts = options || {};
            var duration = (typeof opts.duration !== 'undefined') ? opts.duration : cfg.duration;
            var isDark = document.documentElement.classList.contains('dark');

            var overlay = document.getElementById('pcrm-alert-overlay');
            var box     = document.getElementById('pcrm-alert-box');
            var stripe  = document.getElementById('pcrm-alert-stripe');
            var iconWrap = document.getElementById('pcrm-alert-icon-wrap');
            var iconSvg = document.getElementById('pcrm-alert-icon-svg');
            var titleEl = document.getElementById('pcrm-alert-title');
            var msgEl   = document.getElementById('pcrm-alert-message');
            var btn     = document.getElementById('pcrm-alert-btn');
            var timerBar = document.getElementById('pcrm-alert-timer-bar');

            // Clear previous timer
            if (_timer) { clearTimeout(_timer); _timer = null; }

            // Stripe color
            stripe.style.background = cfg.stripe;

            // Icon background
            iconWrap.style.background = isDark ? cfg.iconBgDark : cfg.iconBg;

            // Icon SVG
            var svgMap = { check: svgCheck, x: svgX, exclaim: svgExclaim, info: svgInfo };
            iconSvg.innerHTML = (svgMap[cfg.icon] || svgInfo)(cfg.iconColor);

            // Text
            titleEl.textContent = title || '';
            msgEl.textContent   = message || '';
            msgEl.style.display = message ? '' : 'none';

            // Button
            btn.style.background = cfg.btnBg;

            // Timer bar
            timerBar.style.background   = cfg.stripe;
            timerBar.style.width        = '100%';
            timerBar.style.transition   = 'none';

            // Reset animation classes on box
            box.classList.remove('pcrm-alert-popup', 'pcrm-alert-popup-out');
            overlay.classList.remove('pcrm-alert-backdrop', 'hidden');
            overlay.style.display = 'flex';

            // Trigger reflow to restart animations
            void box.offsetWidth;
            box.classList.add('pcrm-alert-popup');
            overlay.classList.add('pcrm-alert-backdrop');

            // Auto close timer
            if (duration > 0) {
                // Animate the timer bar
                requestAnimationFrame(function () {
                    requestAnimationFrame(function () {
                        timerBar.style.transition = 'width ' + duration + 'ms linear';
                        timerBar.style.width = '0%';
                    });
                });
                _timer = setTimeout(function () { pcrmAlertClose(); }, duration);
            } else {
                timerBar.style.width = '0%';
            }
        };

        window.pcrmAlertClose = function () {
            var overlay = document.getElementById('pcrm-alert-overlay');
            var box     = document.getElementById('pcrm-alert-box');
            if (_timer) { clearTimeout(_timer); _timer = null; }
            box.classList.remove('pcrm-alert-popup');
            box.classList.add('pcrm-alert-popup-out');
            setTimeout(function () {
                overlay.style.display = 'none';
                overlay.classList.add('hidden');
                box.classList.remove('pcrm-alert-popup-out');
            }, 220);
        };

        // Close on backdrop click
        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('pcrm-alert-overlay').addEventListener('click', function (e) {
                if (e.target === this) pcrmAlertClose();
            });

            // ── Auto-show flash messages from session ──────────────────
            @if(session('success'))
            pcrmAlert('success', 'Thành công', @json(session('success')));
            @endif

            @if(session('error'))
            pcrmAlert('error', 'Có lỗi xảy ra', @json(session('error')));
            @endif

            @if(session('warning'))
            pcrmAlert('warning', 'Lưu ý', @json(session('warning')));
            @endif

            @if(session('info'))
            pcrmAlert('info', 'Thông báo', @json(session('info')));
            @endif
        });
    })();
    </script>
</body>
</html>
