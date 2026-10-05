{{-- Header chào dùng chung cho các trang mobile (Trang chủ, Lịch làm, Đơn từ, Thêm) — phương án 01. --}}
@php
    $mgUser = auth()->user();
    $mgEmployee = $mgUser?->employee()->with(['team', 'branch'])->first();
    $mgNow = \Carbon\Carbon::now('Asia/Ho_Chi_Minh');
    $mgHour = $mgNow->hour;
    [$mgGreeting, $mgIcon] = match (true) {
        $mgHour >= 5 && $mgHour < 11 => ['Chào buổi sáng', 'bi-sun'],
        $mgHour >= 11 && $mgHour < 13 => ['Chào buổi trưa', 'bi-brightness-high'],
        $mgHour >= 13 && $mgHour < 18 => ['Chào buổi chiều', 'bi-cloud-sun'],
        default => ['Chào buổi tối', 'bi-moon'],
    };
    $mgDate = mb_convert_case($mgNow->isoFormat('dddd'), MB_CASE_TITLE, 'UTF-8') . ', ' . $mgNow->format('d/m/Y');
    $mgRole = $mgUser?->getRoleNames()->first() ?? 'Nhân viên';
    $mgChip = 'inline-flex items-center gap-1.5 h-8 px-3 rounded-full text-[13px] font-medium bg-white/70 dark:bg-slate-800/70 border border-blue-100 dark:border-slate-700 text-[#1E3A8A] dark:text-blue-200';
@endphp
<header class="bg-white dark:bg-slate-900 rounded-2xl p-4 border border-blue-100/70 dark:border-slate-800 shadow-[0_4px_20px_rgba(37,99,235,0.07)]">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0 flex-1">
            <p class="text-sm text-[#334155] dark:text-slate-300 flex items-center gap-1.5">
                <i class="bi {{ $mgIcon }} text-[#0B1F5C] dark:text-blue-200"></i> {{ $mgGreeting }},
            </p>
            <h2 class="text-[22px] leading-snug font-extrabold tracking-tight text-[#0B1F5C] dark:text-white break-words">{{ $mgUser?->name }}</h2>
            <p class="mt-1 text-sm text-[#475569] dark:text-slate-400 flex items-center gap-1.5">
                <i class="bi bi-calendar3"></i> {{ $mgDate }}
            </p>
        </div>
        <div class="shrink-0 text-right" aria-live="polite">
            <div class="flex items-center justify-end gap-1.5">
                <i id="weather-icon" class="bi bi-cloud-sun text-2xl text-sky-400"></i>
                <span id="weather-temp" class="text-xl font-extrabold text-[#0B1F5C] dark:text-white">--°C</span>
            </div>
            <p id="weather-desc" class="hidden"></p>
        </div>
    </div>
    @php
        $mgBadge = 'inline-flex items-center gap-1.5 min-h-[30px] px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 dark:bg-blue-950/40 border border-blue-100 dark:border-blue-900/50 text-[#1E3A8A] dark:text-blue-200';
    @endphp
    <div class="mt-3 flex flex-wrap gap-1.5">
        <span class="{{ $mgBadge }}"><i class="bi bi-person-fill text-blue-600"></i> {{ $mgRole }}</span>
        @if ($mgEmployee?->team)
            <span class="{{ $mgBadge }}"><i class="bi bi-building text-blue-600"></i> {{ $mgEmployee->team->name }}</span>
        @endif
        @if ($mgEmployee?->branch)
            <span class="{{ $mgBadge }}"><i class="bi bi-geo-alt-fill text-blue-600"></i> {{ $mgEmployee->branch->name }}</span>
        @endif
    </div>
</header>

@once
@push('scripts')
<script>
    // Thời tiết hiện tại (Open-Meteo) cho header chào mobile.
    (function () {
        function loadWeather(lat, lng) {
            fetch(`https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lng}&current=temperature_2m,weather_code&timezone=auto`)
                .then(res => res.json())
                .then(data => {
                    const code = data.current.weather_code;
                    let desc = 'Trời quang', icon = 'bi-sun-fill text-amber-400';
                    if (code >= 1 && code <= 3) { desc = 'Ít mây'; icon = 'bi-cloud-sun-fill text-sky-400'; }
                    else if (code === 45 || code === 48) { desc = 'Có sương mù'; icon = 'bi-cloud-fog-fill text-slate-400'; }
                    else if (code >= 51 && code <= 55) { desc = 'Mưa phùn'; icon = 'bi-cloud-drizzle-fill text-sky-500'; }
                    else if (code >= 61 && code <= 65) { desc = 'Mưa rào'; icon = 'bi-cloud-rain-heavy-fill text-sky-500'; }
                    else if (code >= 71 && code <= 77) { desc = 'Có tuyết'; icon = 'bi-snow text-sky-400'; }
                    else if (code >= 80 && code <= 82) { desc = 'Mưa rào nhẹ'; icon = 'bi-cloud-rain-fill text-sky-500'; }
                    else if (code >= 95) { desc = 'Có giông'; icon = 'bi-cloud-lightning-rain-fill text-amber-500'; }
                    const t = document.getElementById('weather-temp');
                    const d = document.getElementById('weather-desc');
                    const i = document.getElementById('weather-icon');
                    if (t) t.textContent = Math.round(data.current.temperature_2m) + '°C';
                    if (d) d.textContent = desc;
                    if (i) i.className = 'bi ' + icon + ' text-2xl';
                })
                .catch(() => {
                    const d = document.getElementById('weather-desc');
                    if (d) d.textContent = '';
                });
        }
        document.addEventListener('DOMContentLoaded', function () {
            let lat = 10.8231, lng = 106.6297;
            @if ($mgEmployee?->branch?->latitude && $mgEmployee?->branch?->longitude)
                lat = {{ (float) $mgEmployee->branch->latitude }};
                lng = {{ (float) $mgEmployee->branch->longitude }};
            @endif
            loadWeather(lat, lng);
        });
    })();
</script>
@endpush
@endonce
