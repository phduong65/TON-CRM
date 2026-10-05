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
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/images/logos/tonhrm-submark-opt' . \App\Models\Setting::getValue('tonhrm_logo_option', '2-a') . '.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=Bricolage+Grotesque:opsz,wght@12..96,400..800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/phosphor-icons/1.4.2/css/phosphor.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
    <style>
        :root {
            --theme-accent: {{ $activeTheme['colors']['accent'] ?? '#2F55E7' }};
            --theme-accent-contrast: {{ $activeTheme['colors']['accentContrast'] ?? '#FFFFFF' }};
            --theme-bg-tint: {{ $activeTheme['colors']['bgTint'] ?? 'transparent' }};
        }
    </style>
</head>
<body class="bg-[#F5F7FB] dark:bg-slate-900 min-h-screen" style="@if(!empty($activeTheme['colors']['bgTint']) && $activeTheme['colors']['bgTint'] !== 'transparent') background-color: color-mix(in srgb, {{ $activeTheme['colors']['bgTint'] }} 35%, #F5F7FB); @endif">

    @include('components.splash-loader')
    @include('components.page-loader')

    <a href="#main-content" class="skip-link">Bỏ qua điều hướng</a>

    @if(session('impersonator_id'))
    <div class="bg-amber-500 text-white text-sm px-4 py-2 flex items-center justify-center gap-3 flex-wrap">
        <span><i class="bi bi-person-badge"></i> Đang đăng nhập hộ <strong>{{ auth()->user()->name }}</strong></span>
        <form action="{{ route('impersonate.leave') }}" method="POST" class="inline">
            @csrf @method('DELETE')
            <button type="submit" class="underline font-semibold hover:no-underline">Thoát chế độ đăng nhập hộ</button>
        </form>
    </div>
    @endif

    <!-- Top Navigation Bar -->
    @include('components.topbar')

    <!-- Body layout: left panel + main content -->
    <div class="flex h-screen lg:h-[calc(100vh-70px)]">

        <!-- Left Profile Panel -->
        @include('components.sidebar')

        <!-- Main content scroll area -->
        <div class="flex-1 overflow-y-auto flex flex-col">
            <main id="main-content" tabindex="-1" class="flex-1 px-4 py-5 md:px-6 2xl:px-8 lg:py-6 2xl:py-7 pcrm-animate-in">
                <div class="mx-auto w-full">
                @hasSection('page-title')
                <div class="pcrm-page-head mb-6 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="min-w-0">
                            @hasSection('breadcrumb')
                            <div class="flex items-center gap-1 text-xs font-medium text-slate-400 dark:text-slate-500 mb-1.5">
                                <span>TON-HR</span>
                                <i class="bi bi-chevron-right text-[8px] text-slate-300 dark:text-slate-600"></i>
                                <span class="text-pcrm-550 dark:text-pcrm-400">@yield('breadcrumb')</span>
                            </div>
                            @endif
                            <h1 class="@yield('page-title-class', 'text-2xl sm:text-[28px] font-bold') text-slate-950 dark:text-white tracking-[-0.025em] leading-tight">@yield('page-title')</h1>
                            @hasSection('page-subtitle')
                            <p class="text-sm sm:text-[15px] text-slate-500 dark:text-slate-400 mt-1.5">@yield('page-subtitle')</p>
                            @endif
                        </div>
                    </div>
                    @hasSection('page-actions')
                    <div class="pcrm-page-actions flex flex-wrap items-center gap-2.5 lg:justify-end lg:shrink-0 lg:max-w-[60%]">
                        @yield('page-actions')
                    </div>
                    @endif
                </div>
                @endif
                <div class="pcrm-page-content" data-page="{{ str_replace('.', '-', request()->route()?->getName() ?? 'page') }}">
                    <x-attendance-alert-banner />
                    @yield('content')
                </div>
                </div>
            </main>
        </div>
    </div>

    <!-- ── TON-HR Alert Modal ── -->
    <div id="pcrm-alert-overlay"
         class="hidden fixed inset-0 z-[9999] flex items-center justify-center p-4"
         role="dialog" aria-modal="true" aria-labelledby="pcrm-alert-title" aria-describedby="pcrm-alert-message"
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

    @stack('modals')
    @stack('scripts')

    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

    <script>
    // ── Modal helpers ──────────────────────────────────────────────────────
    // Gắn ngữ nghĩa accessibility (role/aria) + quản lý focus tập trung tại đây
    // để mọi modal dùng openModal()/closeModal() đều đạt chuẩn mà không phải sửa
    // từng partial — xem ui-professionalization-spec §5.2.
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
        // Đưa focus vào phần tử tương tác đầu tiên (hoặc chính dialog) để bàn phím
        // và screen reader đi thẳng vào nội dung modal.
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
        // Trả focus về phần tử đã mở modal (thường là nút Thêm/Sửa/Xoá).
        if (_modalLastFocus && typeof _modalLastFocus.focus === 'function' && document.contains(_modalLastFocus)) {
            _modalLastFocus.focus({ preventScroll: true });
        }
        _modalLastFocus = null;
    }
    // ── Filter panel toggle (mobile collapsible filter bar) ────────────
    function toggleEl(id) {
        const el = document.getElementById(id);
        if (el) el.classList.toggle('is-open');
    }

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        const alertOverlay = document.getElementById('pcrm-alert-overlay');
        if (alertOverlay && !alertOverlay.classList.contains('hidden')) pcrmAlertClose();
        if (typeof closeMobilePanel === 'function') closeMobilePanel();
        document.querySelectorAll('[role="dialog"]:not(#pcrm-alert-overlay)').forEach(function (dialog) {
            if (dialog.id && !dialog.classList.contains('hidden')) closeModal(dialog.id);
        });
    });

    // ── Chặn double-submit: disable nút bấm ngay khi form được gửi hợp lệ, để
    // double-click / bấm dồn dập không tạo 2 request giống nhau (VD: tạo trùng
    // phiếu phạt, đơn nghỉ phép...). Dùng capture-phase submit listener chung cho
    // toàn bộ form trong trang — nếu trình duyệt chặn submit (validation lỗi) thì
    // sự kiện này không bao giờ fire nên nút không bị disable oan.
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
