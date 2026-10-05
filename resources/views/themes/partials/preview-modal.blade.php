<div id="previewThemeModal" class="hidden fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-2 sm:p-4 overflow-hidden"
     onclick="if(event.target===this)closePreviewModal()">
    <div class="bg-white dark:bg-slate-900 rounded-2xl sm:rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 w-full max-w-5xl h-[92vh] flex flex-col overflow-hidden"
         onclick="event.stopPropagation()">

        {{-- Top Toolbar --}}
        <div class="px-5 py-3.5 bg-slate-50 dark:bg-slate-800/90 border-b border-slate-200 dark:border-slate-700/80 flex flex-wrap items-center justify-between gap-3 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-pcrm-50 dark:bg-pcrm-900/40 text-pcrm-600 dark:text-pcrm-300 flex items-center justify-center shrink-0">
                    <i class="bi bi-display text-base"></i>
                </div>
                <div>
                    <h3 class="text-sm font-heading font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>Trình giả lập giao diện:</span>
                        <span id="previewModalThemeName" class="text-pcrm-600 dark:text-pcrm-400 font-extrabold"></span>
                    </h3>
                    <p class="text-[11px] text-slate-400">Xem trước trực quan trên thiết bị và kiểm tra lịch sự kiện giả lập</p>
                </div>
            </div>

            {{-- Controls Toolbar --}}
            <div class="flex items-center gap-2 flex-wrap text-xs">
                {{-- Device Switcher --}}
                <div class="inline-flex p-1 rounded-xl bg-slate-200/80 dark:bg-slate-700">
                    <button type="button" id="btnDeviceMobile" onclick="setPreviewDevice('mobile')"
                            class="px-2.5 py-1 rounded-lg font-semibold flex items-center gap-1.5 transition-all bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-2xs">
                        <i class="bi bi-phone text-xs"></i>
                        <span>Mobile (390px)</span>
                    </button>
                    <button type="button" id="btnDeviceDesktop" onclick="setPreviewDevice('desktop')"
                            class="px-2.5 py-1 rounded-lg font-medium flex items-center gap-1.5 transition-all text-slate-600 dark:text-slate-300 hover:text-slate-900">
                        <i class="bi bi-laptop text-xs"></i>
                        <span>Desktop</span>
                    </button>
                </div>

                {{-- Simulated Date/Time --}}
                <div class="flex items-center gap-1.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 px-3 py-1 rounded-xl">
                    <i class="bi bi-clock text-slate-400 text-xs"></i>
                    <input type="datetime-local" id="previewSimulatedTime" onchange="refreshPreviewIframe()"
                           class="bg-transparent border-0 text-xs text-slate-700 dark:text-slate-300 focus:outline-none p-0">
                </div>

                <button type="button" onclick="closePreviewModal()" class="w-8 h-8 rounded-xl flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700 transition">
                    <i class="bi bi-x-lg text-sm"></i>
                </button>
            </div>
        </div>

        {{-- Viewport Container --}}
        <div class="flex-1 bg-slate-100 dark:bg-slate-950/80 p-4 overflow-auto flex items-center justify-center relative">

            {{-- Mobile Frame Container --}}
            <div id="previewFrameContainer" class="w-[390px] h-[780px] max-h-full bg-black rounded-[44px] p-3 shadow-2xl transition-all duration-300 border-4 border-slate-700 flex flex-col relative">
                {{-- iPhone notch/island --}}
                <div class="w-28 h-5 bg-black rounded-full mx-auto mb-2 shrink-0 flex items-center justify-center">
                    <div class="w-2.5 h-2.5 rounded-full bg-slate-900"></div>
                </div>

                {{-- Actual IFrame Sandbox --}}
                <div class="flex-1 rounded-[32px] overflow-hidden bg-white relative">
                    <iframe id="previewIframe" src="about:blank" class="w-full h-full border-0"></iframe>
                </div>

                {{-- Home Indicator --}}
                <div class="w-28 h-1 bg-slate-600 rounded-full mx-auto mt-2 shrink-0"></div>
            </div>

        </div>

        {{-- Bottom Status Bar --}}
        <div class="px-5 py-2.5 bg-white dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700 text-[11px] flex items-center justify-between text-slate-500 shrink-0">
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Sandbox mode: Không ảnh hưởng đến dữ liệu hay trải nghiệm của người dùng thực tế</span>
            </div>
            <span class="text-slate-400 font-mono">TON-HR Theme Simulator</span>
        </div>

    </div>
</div>

<script>
    let currentPreviewThemeId = null;
    let currentPreviewDevice = 'mobile';

    function openPreviewModal(themeId, themeName) {
        currentPreviewThemeId = themeId;
        document.getElementById('previewModalThemeName').textContent = themeName || 'Default TON-HR';

        // Set simulated time to now by default
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        document.getElementById('previewSimulatedTime').value = now.toISOString().slice(0, 16);

        setPreviewDevice('mobile');
        refreshPreviewIframe();

        openModal('previewThemeModal');
    }

    function closePreviewModal() {
        closeModal('previewThemeModal');
        document.getElementById('previewIframe').src = 'about:blank';
    }

    function setPreviewDevice(device) {
        currentPreviewDevice = device;
        const container = document.getElementById('previewFrameContainer');
        const btnMob = document.getElementById('btnDeviceMobile');
        const btnDesk = document.getElementById('btnDeviceDesktop');

        if (device === 'mobile') {
            container.className = 'w-[390px] h-[780px] max-h-full bg-black rounded-[44px] p-3 shadow-2xl transition-all duration-300 border-4 border-slate-700 flex flex-col relative';
            btnMob.className = 'px-2.5 py-1 rounded-lg font-semibold flex items-center gap-1.5 transition-all bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-2xs';
            btnDesk.className = 'px-2.5 py-1 rounded-lg font-medium flex items-center gap-1.5 transition-all text-slate-600 dark:text-slate-300 hover:text-slate-900';
        } else {
            container.className = 'w-full h-full bg-slate-800 rounded-2xl p-2 shadow-2xl transition-all duration-300 border border-slate-700 flex flex-col relative';
            btnDesk.className = 'px-2.5 py-1 rounded-lg font-semibold flex items-center gap-1.5 transition-all bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-2xs';
            btnMob.className = 'px-2.5 py-1 rounded-lg font-medium flex items-center gap-1.5 transition-all text-slate-600 dark:text-slate-300 hover:text-slate-900';
        }
    }

    function refreshPreviewIframe() {
        if (!currentPreviewThemeId) return;

        const simTime = document.getElementById('previewSimulatedTime').value;
        const url = `/login?preview_theme_id=${currentPreviewThemeId}&preview_time=${encodeURIComponent(simTime)}`;
        document.getElementById('previewIframe').src = url;
    }
</script>
