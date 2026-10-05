{{-- TON-HR Initial Visit Splash Screen Component --}}
{{-- Displays a centered logo with a circular rotating loader on the user's first visit to the web app --}}

<style>
    html.splash-dismissed #tonhrm-initial-splash {
        display: none !important;
    }
</style>

<div id="tonhrm-initial-splash" class="fixed inset-0 z-[999999] flex flex-col items-center justify-center bg-[#F8FAFC] dark:bg-[#0B1120] text-slate-800 dark:text-slate-100 select-none transition-opacity duration-500 ease-out" role="status" aria-live="polite">
    <!-- Ambient radial glow behind the center loader -->
    <div class="absolute w-80 h-80 sm:w-96 sm:h-96 rounded-full bg-gradient-to-tr from-blue-600/10 via-indigo-500/10 to-transparent blur-3xl pointer-events-none"></div>

    <div class="relative flex flex-col items-center px-4">
        <!-- Circular spinner with center software logo (Loading xoay tròn có logo phần mềm ở giữa) -->
        <div class="relative flex items-center justify-center w-24 h-24 sm:w-28 sm:h-28 mb-5">
            <!-- Spinner Ring 1: Stationary background track -->
            <svg class="absolute inset-0 w-full h-full -rotate-90 pointer-events-none" viewBox="0 0 100 100">
                <circle cx="50" cy="50" r="43" fill="none" stroke="currentColor" stroke-width="3"
                    class="text-slate-200/90 dark:text-slate-800/90" />
            </svg>

            <!-- Spinner Ring 2: Rotating gradient arc -->
            <svg class="absolute inset-0 w-full h-full -rotate-90 pointer-events-none animate-spin" style="animation-duration: 1.1s;" viewBox="0 0 100 100">
                <circle cx="50" cy="50" r="43" fill="none" stroke="url(#tonhrm-splash-gradient)" stroke-width="3.5"
                    stroke-linecap="round" stroke-dasharray="270" stroke-dashoffset="180" />
                <defs>
                    <linearGradient id="tonhrm-splash-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#2F55E7" />
                        <stop offset="60%" stop-color="#4F46E5" />
                        <stop offset="100%" stop-color="#60A5FA" />
                    </linearGradient>
                </defs>
            </svg>

            <!-- Inner subtle pulse ring -->
            <div class="absolute inset-2 rounded-full border border-blue-500/20 dark:border-blue-400/20 animate-pulse"></div>

            <!-- Software Logo Box in center -->
            <div class="relative w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800/90 shadow-xl shadow-blue-500/10 dark:shadow-black/50 flex items-center justify-center p-2.5 transition-transform duration-300">
                <x-tonhrm-logo type="submark" class="w-9 h-9 sm:w-10 sm:h-10 object-contain" />
            </div>
        </div>

        <!-- Software Name & Brand Subtitle -->
        <div class="flex flex-col items-center text-center">
            <div class="flex items-baseline leading-none">
                <span class="font-heading font-extrabold text-[26px] sm:text-[30px] tracking-[-0.03em] text-slate-950 dark:text-white">Ton</span>
                <span class="font-heading font-extrabold text-[26px] sm:text-[30px] tracking-[-0.02em] ml-0.5 text-[#2F55E7] dark:text-[#809ff9]">HRM</span>
            </div>
            <span class="text-[10px] sm:text-[11px] font-bold tracking-[0.2em] text-slate-400 dark:text-slate-500 uppercase mt-2">
                Hệ Thống Quản Lý Nhân Sự & Kỷ Luật
            </span>

            <!-- Status Indicator -->
            <div class="mt-6 flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/80 dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/60 shadow-xs backdrop-blur-xs">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#2F55E7] opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-[#2F55E7]"></span>
                </span>
                <span class="text-xs font-medium text-slate-600 dark:text-slate-300">Đang khởi tạo hệ thống...</span>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var splash = document.getElementById('tonhrm-initial-splash');
    if (!splash) return;

    // Check if forced via query string (?splash=1) for testing
    var isForced = window.location.search.indexOf('splash=1') !== -1;

    // If already shown in this session and not forced, dismiss instantly
    if (sessionStorage.getItem('tonhrm_splash_shown') === '1' && !isForced) {
        splash.style.display = 'none';
        splash.remove();
        return;
    }

    var minDisplayTime = 850; // Smooth visual experience
    var startTime = Date.now();
    var dismissed = false;

    function dismissSplash() {
        if (dismissed) return;
        dismissed = true;

        var elapsed = Date.now() - startTime;
        var remaining = Math.max(0, minDisplayTime - elapsed);

        setTimeout(function() {
            splash.classList.add('opacity-0', 'pointer-events-none');
            setTimeout(function() {
                splash.remove();
                try {
                    sessionStorage.setItem('tonhrm_splash_shown', '1');
                    document.documentElement.classList.add('splash-dismissed');
                } catch(e) {}
            }, 500);
        }, remaining);
    }

    // Dismiss when DOM & resources are ready
    if (document.readyState === 'complete') {
        dismissSplash();
    } else {
        window.addEventListener('load', dismissSplash);
    }

    // Fallback safety timeout (max 2.5s)
    setTimeout(dismissSplash, 2500);

    // Global developer helper
    window.previewSplashLoader = function() {
        sessionStorage.removeItem('tonhrm_splash_shown');
        window.location.reload();
    };
})();
</script>
