<!DOCTYPE html>
<html lang="vi" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Test Runner — TON-CMS Dev</title>
    @vite(['resources/css/app.css'])
    <style>
        pre { white-space: pre-wrap; word-break: break-word; }

        .spinner { display: none; }
        .spinner.active { display: inline-block; animation: spin 0.7s linear infinite; }
        @@keyframes spin { to { transform: rotate(360deg); } }

        #outputBox::-webkit-scrollbar { width: 5px; }
        #outputBox::-webkit-scrollbar-track { background: transparent; }
        #outputBox::-webkit-scrollbar-thumb { background: #30363d; border-radius: 3px; }
        #outputBox::-webkit-scrollbar-thumb:hover { background: #4b5563; }
    </style>
</head>
<body class="bg-[#0d1117] text-[#e6edf3] min-h-screen font-mono">

<div class="min-h-screen flex flex-col">

    {{-- Top bar --}}
    <header class="border-b border-[#30363d] bg-[#161b22]">
        <div class="max-w-5xl mx-auto px-6 py-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-2 h-2 rounded-full bg-emerald-400" style="box-shadow:0 0 6px #4ade80"></div>
                <span class="text-sm font-semibold tracking-tight">TON-CMS <span class="text-[#7d8590]">/</span> test-runner</span>
                <span class="text-[10px] bg-yellow-500/20 text-yellow-400 border border-yellow-500/30 px-2 py-0.5 rounded font-bold uppercase tracking-wide">
                    {{ app()->environment() }}
                </span>
            </div>
            <div class="flex items-center gap-5 text-xs text-[#7d8590]">
                <span>PHP <span class="text-[#e6edf3]">{{ phpversion() }}</span></span>
                <span>Laravel <span class="text-[#e6edf3]">{{ app()->version() }}</span></span>
                <a href="{{ route('dashboard') }}" class="hover:text-[#e6edf3] transition-colors">← Dashboard</a>
            </div>
        </div>
    </header>

    {{-- Main content --}}
    <main class="flex-1 max-w-5xl mx-auto w-full px-6 py-8 space-y-5">

        {{-- Title + Run button --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-lg font-bold text-[#e6edf3] tracking-tight">Test Runner</h1>
                <p class="text-xs text-[#7d8590] mt-0.5">php artisan test --no-coverage</p>
            </div>
            <div class="flex items-center gap-3">
                <span id="lastRun" class="hidden text-xs text-[#7d8590]">
                    Chạy lúc <span id="lastRunTime" class="text-[#c9d1d9]"></span>
                </span>
                <button id="runBtn"
                        onclick="runTests()"
                        class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed text-white text-sm font-semibold px-5 py-2.5 rounded-lg transition-colors">
                    {{-- Play icon --}}
                    <svg id="btnIcon" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M8 5v14l11-7z"/>
                    </svg>
                    {{-- Spinner --}}
                    <svg id="spinner" class="spinner w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <span id="btnLabel">Run Tests</span>
                </button>
            </div>
        </div>

        {{-- Progress bar --}}
        <div id="progressWrap" class="hidden">
            <div class="w-full bg-[#21262d] rounded-full h-1 overflow-hidden">
                <div id="progressBar" class="h-full rounded-full transition-all duration-500 bg-emerald-500" style="width:0%"></div>
            </div>
        </div>

        {{-- Status banner + Summary cards --}}
        <div id="summarySection" class="hidden space-y-3">

            {{-- Status banner --}}
            <div id="statusBanner" class="flex items-center gap-3 px-4 py-3 rounded-lg border text-sm font-semibold">
                <span id="statusIcon" class="flex-shrink-0"></span>
                <span id="statusText"></span>
                <span id="statusDuration" class="ml-auto text-xs font-normal opacity-60"></span>
            </div>

            {{-- Metric cards --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">

                <div class="bg-[#161b22] border border-[#30363d] rounded-xl p-4">
                    <div id="sumTotal" class="text-3xl font-bold text-[#e6edf3]">—</div>
                    <div class="flex items-center gap-1.5 mt-2">
                        <svg class="w-3.5 h-3.5 text-[#7d8590]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        <span class="text-[#7d8590] text-xs">Total</span>
                    </div>
                </div>

                <div class="bg-[#161b22] border border-[#30363d] rounded-xl p-4">
                    <div id="sumPassed" class="text-3xl font-bold text-emerald-400">—</div>
                    <div class="flex items-center gap-1.5 mt-2">
                        <svg class="w-3.5 h-3.5 text-emerald-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-[#7d8590] text-xs">Passed</span>
                    </div>
                </div>

                <div class="bg-[#161b22] border border-[#30363d] rounded-xl p-4">
                    <div id="sumFailed" class="text-3xl font-bold text-red-400">—</div>
                    <div class="flex items-center gap-1.5 mt-2">
                        <svg class="w-3.5 h-3.5 text-red-800" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        <span class="text-[#7d8590] text-xs">Failed</span>
                    </div>
                </div>

                <div class="bg-[#161b22] border border-[#30363d] rounded-xl p-4">
                    <div id="sumTime" class="text-3xl font-bold text-sky-400">—</div>
                    <div class="flex items-center gap-1.5 mt-2">
                        <svg class="w-3.5 h-3.5 text-sky-800" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="text-[#7d8590] text-xs">Duration</span>
                    </div>
                </div>

            </div>
        </div>

        {{-- Terminal output --}}
        <div id="outputWrap" class="hidden">
            <div class="bg-[#161b22] border border-[#30363d] rounded-xl overflow-hidden">

                {{-- Terminal title bar --}}
                <div class="flex items-center justify-between px-4 py-2.5 border-b border-[#30363d] bg-[#0d1117]/60">
                    <div class="flex items-center gap-3">
                        <div class="flex gap-1.5">
                            <div class="w-3 h-3 rounded-full bg-[#ff5f57]"></div>
                            <div class="w-3 h-3 rounded-full bg-[#febc2e]"></div>
                            <div class="w-3 h-3 rounded-full bg-[#28c840]"></div>
                        </div>
                        <span class="text-[#7d8590] text-xs ml-1">zsh — php artisan test --no-coverage</span>
                    </div>
                    <button onclick="toggleOutput()" id="toggleOutputBtn"
                            class="text-xs text-[#7d8590] hover:text-[#e6edf3] px-2 py-1 rounded hover:bg-[#21262d] transition-colors">
                        Ẩn output
                    </button>
                </div>

                {{-- Output pre --}}
                <pre id="outputBox" class="px-5 py-4 text-xs text-[#c9d1d9] max-h-[480px] overflow-y-auto leading-relaxed"></pre>
            </div>
        </div>

        {{-- Empty state --}}
        <div id="emptyState" class="py-20 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-[#161b22] border border-[#30363d] mb-5">
                <svg class="w-8 h-8 text-[#7d8590]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23-.693L5 14.5m14.8.8l1.402 1.402c1 1 .3 2.7-1.1 2.7H3.9c-1.4 0-2.1-1.7-1.1-2.7L5 14.5"/>
                </svg>
            </div>
            <p class="text-[#e6edf3] text-sm font-medium">Chưa có kết quả nào</p>
            <p class="text-[#7d8590] text-xs mt-1">Nhấn <strong class="text-[#e6edf3]">Run Tests</strong> để chạy toàn bộ test suite</p>
        </div>

    </main>
</div>

<script>
let outputVisible = true;

function toggleOutput() {
    const box = document.getElementById('outputBox');
    const btn = document.getElementById('toggleOutputBtn');
    outputVisible = !outputVisible;
    box.style.display = outputVisible ? '' : 'none';
    btn.textContent = outputVisible ? 'Ẩn output' : 'Hiện output';
}

async function runTests() {
    const btn            = document.getElementById('runBtn');
    const spinner        = document.getElementById('spinner');
    const btnIcon        = document.getElementById('btnIcon');
    const btnLabel       = document.getElementById('btnLabel');
    const progressWrap   = document.getElementById('progressWrap');
    const progressBar    = document.getElementById('progressBar');
    const summarySection = document.getElementById('summarySection');
    const statusBanner   = document.getElementById('statusBanner');
    const outputWrap     = document.getElementById('outputWrap');
    const emptyState     = document.getElementById('emptyState');

    // Reset UI
    btn.disabled = true;
    spinner.classList.add('active');
    btnIcon.classList.add('hidden');
    btnLabel.textContent = 'Running…';
    summarySection.classList.add('hidden');
    outputWrap.classList.add('hidden');
    emptyState.classList.add('hidden');
    progressWrap.classList.remove('hidden');
    progressBar.style.width = '5%';
    progressBar.className = 'h-full rounded-full transition-all duration-500 bg-emerald-500';

    // Fake progress while waiting for response
    let fakeProgress = 5;
    const interval = setInterval(() => {
        fakeProgress = Math.min(fakeProgress + Math.random() * 7, 88);
        progressBar.style.width = fakeProgress + '%';
    }, 900);

    try {
        const res = await fetch('{{ route('dev.test-runner.run') }}', {
            method : 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept'      : 'application/json',
            },
        });

        clearInterval(interval);
        progressBar.style.width = '100%';

        const data      = await res.json();
        const failCount = (data.summary.failed ?? 0) + (data.summary.errors ?? 0);

        // Populate metric cards
        document.getElementById('sumTotal').textContent  = data.summary.total  ?? '—';
        document.getElementById('sumPassed').textContent = data.summary.passed ?? '—';
        document.getElementById('sumFailed').textContent = failCount > 0 ? failCount : (data.summary.failed !== undefined ? 0 : '—');
        document.getElementById('sumTime').textContent   = data.summary.time   ?? '—';
        document.getElementById('statusDuration').textContent = data.summary.time ? data.summary.time : '';

        // Status banner
        if (data.success) {
            statusBanner.className = 'flex items-center gap-3 px-4 py-3 rounded-lg border text-sm font-semibold bg-emerald-950/60 border-emerald-800/60 text-emerald-300';
            document.getElementById('statusIcon').innerHTML = '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>';
            document.getElementById('statusText').textContent = 'All tests passed';
            progressBar.className = 'h-full rounded-full transition-all duration-500 bg-emerald-500';
        } else {
            statusBanner.className = 'flex items-center gap-3 px-4 py-3 rounded-lg border text-sm font-semibold bg-red-950/60 border-red-800/60 text-red-300';
            document.getElementById('statusIcon').innerHTML = '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>';
            document.getElementById('statusText').textContent = failCount + ' test' + (failCount !== 1 ? 's' : '') + ' failed';
            progressBar.className = 'h-full rounded-full transition-all duration-500 bg-red-500';
        }

        summarySection.classList.remove('hidden');

        // Strip ANSI escape codes and render output
        const raw = (data.output || '(no output)').replace(/\x1b\[[0-9;]*m/g, '');
        document.getElementById('outputBox').textContent = raw;
        outputWrap.classList.remove('hidden');
        outputVisible = true;
        document.getElementById('toggleOutputBtn').textContent = 'Ẩn output';

        // Last run timestamp
        document.getElementById('lastRunTime').textContent = new Date().toLocaleTimeString('vi-VN');
        document.getElementById('lastRun').classList.remove('hidden');

    } catch (err) {
        clearInterval(interval);
        progressBar.className = 'h-full rounded-full bg-orange-500';
        progressBar.style.width = '100%';

        statusBanner.className = 'flex items-center gap-3 px-4 py-3 rounded-lg border text-sm font-semibold bg-orange-950/60 border-orange-800/60 text-orange-300';
        document.getElementById('statusIcon').innerHTML = '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>';
        document.getElementById('statusText').textContent = 'Network error';
        document.getElementById('statusDuration').textContent = '';
        summarySection.classList.remove('hidden');

        document.getElementById('outputBox').textContent = String(err);
        outputWrap.classList.remove('hidden');
        emptyState.classList.add('hidden');
    } finally {
        btn.disabled = false;
        spinner.classList.remove('active');
        btnIcon.classList.remove('hidden');
        btnLabel.textContent = 'Run Again';
    }
}
</script>
</body>
</html>
