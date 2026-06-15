<!DOCTYPE html>
<html lang="vi" class="{{ auth()->check() && auth()->user()->theme === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'TON-CMS')) — TON-CMS</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=be-vietnam-pro:300,400,500,600,700,800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/phosphor-icons/1.4.2/css/phosphor.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>
<body class="bg-slate-100 dark:bg-slate-900 min-h-screen">

    <!-- Top Navigation Bar -->
    @include('components.topbar')

    <!-- Body layout: left panel + main content -->
    <div class="flex" style="height: calc(100vh - 56px);">

        <!-- Left Profile Panel -->
        @include('components.sidebar')

        <!-- Main content scroll area -->
        <div class="flex-1 overflow-y-auto flex flex-col">
            <main class="flex-1 p-5 md:p-6 pcrm-animate-in">
                @hasSection('page-title')
                <div class="mb-5">
                    @hasSection('breadcrumb')
                    <p class="text-xs text-slate-400 dark:text-slate-500 mb-0.5">@yield('breadcrumb')</p>
                    @endif
                    <h1 class="text-xl font-bold text-slate-900 dark:text-white tracking-tight">@yield('page-title')</h1>
                </div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>

    <!-- ── TON-CMS Alert Modal ── -->
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
    <!-- ── /TON-CMS Alert Modal ── -->

    @stack('modals')
    @stack('scripts')

    <script>
    // ── Modal helpers ──────────────────────────────────────────────────────
    function openModal(id) {
        const el = document.getElementById(id);
        if (el) { el.classList.remove('hidden'); el.classList.add('flex'); }
    }
    function closeModal(id) {
        const el = document.getElementById(id);
        if (el) { el.classList.add('hidden'); el.classList.remove('flex'); }
    }

    // ── TON-CMS Alert System ────────────────────────────────────────────────
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
