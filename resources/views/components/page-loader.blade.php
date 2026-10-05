{{-- TonHRM Page Loading Indicator Component --}}
{{-- Option 1 (Default): Slim Top Shimmer Progress Bar --}}
{{-- Option 2: Center Brand Pulse Overlay --}}
{{-- Option 3: Dual-Line Loader with Micro Spinner --}}

@php
    $loaderStyle = \App\Models\Setting::getValue('page_loader_style', 'option1');
@endphp

<!-- Page Loading Bar (Option 1 & Option 3) -->
<div id="tonhrm-page-loader" class="fixed top-0 left-0 right-0 h-[3px] z-[99999] pointer-events-none opacity-0 transition-opacity duration-200">
    <div id="tonhrm-page-loader-bar" class="h-full w-0 bg-gradient-to-r from-blue-600 via-pcrm-600 to-indigo-500 shadow-[0_0_10px_rgba(47,85,231,0.6)] transition-all duration-300 ease-out"></div>
</div>

<!-- Center Brand Pulse Loader (Option 2 - Activated when loaderStyle is option2 or called programmatically) -->
<div id="tonhrm-center-loader" class="fixed inset-0 z-[99998] pointer-events-none opacity-0 transition-opacity duration-300 flex items-center justify-center bg-slate-900/10 dark:bg-slate-950/20 backdrop-blur-[1px]">
    <div class="bg-white/95 dark:bg-slate-800/95 border border-slate-200 dark:border-slate-700 p-4 rounded-2xl shadow-2xl flex flex-col items-center gap-3 transform scale-95 transition-transform duration-300" id="tonhrm-center-loader-box">
        <div class="relative w-12 h-12 flex items-center justify-center">
            <div class="absolute inset-0 rounded-xl border-2 border-pcrm-600/30 border-t-pcrm-600 animate-spin"></div>
            <x-tonhrm-logo type="submark" class="w-7 h-7 object-contain animate-pulse" />
        </div>
        <span class="text-xs font-semibold text-slate-700 dark:text-slate-200 tracking-wide">Đang tải dữ liệu...</span>
    </div>
</div>

<script>
    (function() {
        var loaderBar = document.getElementById('tonhrm-page-loader-bar');
        var loaderContainer = document.getElementById('tonhrm-page-loader');
        var centerLoader = document.getElementById('tonhrm-center-loader');
        var centerBox = document.getElementById('tonhrm-center-loader-box');
        var progressTimer = null;
        var currentProgress = 0;
        var activeStyle = '{{ $loaderStyle }}'; // 'option1', 'option2', or 'option3'

        function startLoader(style) {
            var selectedStyle = style || activeStyle;
            currentProgress = 15;
            
            if (loaderContainer && loaderBar) {
                loaderContainer.classList.remove('opacity-0');
                loaderBar.style.width = currentProgress + '%';

                clearInterval(progressTimer);
                progressTimer = setInterval(function() {
                    if (currentProgress < 85) {
                        currentProgress += Math.random() * 12 + 4;
                        if (currentProgress > 85) currentProgress = 85;
                        loaderBar.style.width = currentProgress + '%';
                    }
                }, 200);
            }

            if (selectedStyle === 'option2' && centerLoader && centerBox) {
                centerLoader.classList.remove('pointer-events-none', 'opacity-0');
                centerBox.classList.remove('scale-95');
                centerBox.classList.add('scale-100');
            }
        }

        function stopLoader() {
            clearInterval(progressTimer);
            if (loaderBar && loaderContainer) {
                loaderBar.style.width = '100%';
                setTimeout(function() {
                    loaderContainer.classList.add('opacity-0');
                    setTimeout(function() {
                        loaderBar.style.width = '0%';
                    }, 250);
                }, 150);
            }

            if (centerLoader && centerBox) {
                centerLoader.classList.add('opacity-0', 'pointer-events-none');
                centerBox.classList.remove('scale-100');
                centerBox.classList.add('scale-95');
            }
        }

        // Global APIs
        window.startPageLoader = startLoader;
        window.stopPageLoader = stopLoader;

        // Auto trigger on page transitions
        document.addEventListener('click', function(e) {
            var link = e.target.closest('a');
            if (!link) return;
            
            var href = link.getAttribute('href');
            var target = link.getAttribute('target');
            
            // Ignore anchor clicks, empty links, javascript:, new tabs, or cross-origin
            if (!href || href.startsWith('#') || href.startsWith('javascript:') || target === '_blank' || e.ctrlKey || e.metaKey || e.shiftKey) {
                return;
            }

            // Verify same-origin
            try {
                var url = new URL(href, window.location.origin);
                if (url.origin === window.location.origin && url.pathname !== window.location.pathname) {
                    startLoader();
                }
            } catch(err) {}
        });

        // Trigger on form submit (unless prevented)
        document.addEventListener('submit', function(e) {
            var form = e.target;
            if (form && !e.defaultPrevented && form.getAttribute('target') !== '_blank') {
                startLoader();
            }
        });

        // Ensure loader stops on load or back/forward cache
        window.addEventListener('pageshow', function() {
            stopLoader();
        });
        window.addEventListener('DOMContentLoaded', function() {
            stopLoader();
        });
    })();
</script>
