@extends('layouts.auth')

@section('title', 'Đăng nhập')

@section('content')
    @php
        $banners = $activeTheme['visual']['banners'] ?? [];
        $mobileBanner = $banners['login_banner_mobile'] ?? null;
        $slug = $activeTheme['slug'] ?? '';
        $isTet = str_contains($slug, 'tet');
        $isQuocKhanh = str_contains($slug, 'quoc-khanh') || str_contains($slug, '2-9');
        $mobileTag = $isTet ? 'Xuân Bính Ngọ' : ($isQuocKhanh ? 'Kỷ Niệm 2/9' : 'TON-HR');
    @endphp

    @if($mobileBanner && file_exists(public_path($mobileBanner)))
        {{-- High-Def Responsive Mobile Banner with Fixed Aspect Ratio --}}
        <div class="mb-5 rounded-2xl overflow-hidden shadow-sm border border-slate-200/80 relative aspect-[16/6] sm:aspect-[16/5] bg-slate-900">
            <img src="{{ asset($mobileBanner) }}"
                 alt="{{ $activeTheme['name'] ?? 'Theme' }}"
                 class="w-full h-full object-cover object-center select-none pointer-events-none">
            <div class="absolute inset-0 bg-gradient-to-t from-black/75 via-black/25 to-transparent flex items-end p-3 pointer-events-none">
                <div class="flex items-center justify-between w-full text-white">
                    <span class="text-xs font-bold uppercase tracking-wider drop-shadow-sm flex items-center gap-1.5 text-amber-300">
                        <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
                        {{ $activeTheme['name'] ?? 'Lễ Hội' }}
                    </span>
                    <span class="text-[11px] text-amber-200 font-semibold drop-shadow-sm">{{ $mobileTag }}</span>
                </div>
            </div>
        </div>
    @endif

    <h1 class="text-[28px] sm:text-[30px] leading-tight font-bold text-[#111827] tracking-tight">
        {{ $activeTheme['content']['loginGreeting'] ?? 'Chào mừng trở lại' }}
    </h1>
    <p class="mt-2 text-[13px] leading-[18px] text-[#6B7280]">
        {{ $activeTheme['content']['loginSubtitle'] ?? 'Quản lý nhân sự, điểm thưởng và kỷ luật — tất cả tại một nơi.' }}
    </p>

    @if (session('status'))
        <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    <form id="loginForm" method="POST" action="{{ route('login') }}" class="mt-6 space-y-4" novalidate>
        @csrf
        <input type="hidden" name="lat" id="loginLat">
        <input type="hidden" name="lng" id="loginLng">

        {{-- Email --}}
        <div>
            <label for="email" class="block text-sm font-medium text-[#111827] mb-2.5">Email</label>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-[#9CA3AF]">
                    <i class="bi bi-envelope text-base"></i>
                </span>
                <input id="email" type="email" name="email" value="{{ old('email') }}"
                    class="auth-input w-full pl-11 pr-4 text-sm @error('email') has-error @enderror"
                    placeholder="Nhập email công ty" required autofocus autocomplete="username">
            </div>
            @error('email')
                <p class="mt-1.5 text-xs text-[#DC2626]">{{ $message }}</p>
            @enderror
        </div>

        {{-- Password --}}
        <div>
            <label for="password" class="block text-sm font-medium text-[#111827] mb-2.5">Mật khẩu</label>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-[#9CA3AF]">
                    <i class="bi bi-lock text-base"></i>
                </span>
                <input id="password" type="password" name="password"
                    class="auth-input w-full pl-11 pr-11 text-sm @error('password') has-error @enderror"
                    placeholder="••••••••" required autocomplete="current-password">
                <button type="button" onclick="togglePasswordVisibility('password', 'passwordToggleIcon')"
                    class="absolute inset-y-0 right-0 flex items-center pr-4 text-[#9CA3AF] hover:text-[#6B7280] transition-colors"
                    aria-label="Hiện/ẩn mật khẩu">
                    <i id="passwordToggleIcon" class="bi bi-eye text-base"></i>
                </button>
            </div>
            @error('password')
                <p class="mt-1.5 text-xs text-[#DC2626]">{{ $message }}</p>
            @enderror
        </div>

        {{-- Forgot password --}}
        @if (Route::has('password.request'))
            <div class="flex items-center justify-end text-sm pt-0.5">
                <a href="{{ route('password.request') }}" class="inline-flex items-center py-2 px-1 text-[13px] font-medium text-[#2563EB] hover:text-[#1D4ED8] transition-colors focus:outline-none focus:underline" style="min-height: 44px;">
                    Quên mật khẩu?
                </a>
            </div>
        @endif

        <div class="pt-1.5">
            <button type="submit" id="loginSubmitBtn" class="auth-btn-primary w-full flex items-center justify-center gap-2 text-sm shadow-xs hover:shadow-md transition-all">
                <span>Đăng nhập</span>
                <i class="bi bi-arrow-right text-xs"></i>
            </button>
        </div>

        {{-- Divider --}}
        <div class="flex items-center gap-3 pt-1">
            <span class="flex-1 h-px bg-[#E5E7EB]"></span>
            <span class="text-[12px] font-medium text-[#6B7280] whitespace-nowrap">Hoặc tiếp tục với</span>
            <span class="flex-1 h-px bg-[#E5E7EB]"></span>
        </div>

        {{-- Social buttons (Official brand SVGs) --}}
        <div class="grid grid-cols-3 gap-3">
            <button type="button" class="auth-btn-social" title="Tiếp tục với Google" aria-label="Tiếp tục với Google">
                <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.66-5.17 3.66-9.17z"/>
                    <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.33 24 12 24z"/>
                    <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.19 0 10.04 0 12s.45 3.81 1.25 5.42l4.03-3.15z"/>
                    <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.33 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
                </svg>
            </button>
            <button type="button" class="auth-btn-social" title="Tiếp tục với Microsoft 365" aria-label="Tiếp tục với Microsoft 365">
                <svg class="w-[18px] h-[18px]" viewBox="0 0 21 21">
                    <rect x="1" y="1" width="9" height="9" fill="#F25022"/>
                    <rect x="11" y="1" width="9" height="9" fill="#7FBA00"/>
                    <rect x="1" y="11" width="9" height="9" fill="#00A4EF"/>
                    <rect x="11" y="11" width="9" height="9" fill="#FFB900"/>
                </svg>
            </button>
            <button type="button" class="auth-btn-social" title="Tiếp tục với Apple" aria-label="Tiếp tục với Apple">
                <svg class="w-[18px] h-[18px]" viewBox="0 0 170 170" fill="#111827">
                    <path d="M150.37 130.25c-2.45 5.66-5.35 10.87-8.71 15.66-4.58 6.53-8.33 11.05-11.22 13.56-4.48 4.12-9.28 6.23-14.42 6.35-3.69 0-8.14-1.05-13.32-3.18-5.19-2.12-9.97-3.17-14.34-3.17-4.58 0-9.49 1.05-14.75 3.17-5.26 2.13-9.5 3.24-12.74 3.35-4.35.13-9.16-1.9-14.42-6.08-3.69-3.08-7.7-7.94-12.04-14.58-6.19-9.49-10.99-19.82-14.4-31-3.41-11.19-5.12-21.78-5.12-31.78 0-14.28 3.59-25.75 10.77-34.41 7.18-8.66 16.2-13.06 27.05-13.2 4.69 0 10.05 1.25 16.08 3.76 6.03 2.51 10.05 3.82 12.06 3.93 1.79 0 6.09-1.46 12.9-4.37 6.81-2.92 12.63-4.14 17.47-3.66 13.54 1.03 24.28 6.37 32.22 16.02-11.83 7.19-17.65 16.92-17.46 29.21.19 9.84 3.91 18.06 11.16 24.66 7.25 6.6 15.82 10.36 25.71 11.28-2.61 7.84-5.69 15.34-9.24 22.5zM119.22 31.02c0-7.39 2.66-14.29 7.98-20.7 5.32-6.41 11.85-10.32 19.59-11.73.34 2.82.26 5.66-.25 8.52-.51 2.86-1.57 5.67-3.18 8.43-1.88 3.19-4.38 6.05-7.5 8.58-3.12 2.53-6.49 4.19-10.11 4.98-.79-3.47-2.92-6.17-6.53-8.08z"/>
                </svg>
            </button>
        </div>

        {{-- Enterprise Account Note --}}
        <div class="text-center pt-2">
            <p class="text-xs sm:text-sm text-[#6B7280]">
                Tài khoản chưa được kích hoạt?
                <button type="button" onclick="openContactHrModal()" class="font-semibold text-[#2563EB] hover:text-[#1D4ED8] underline-offset-2 hover:underline ml-1">
                    Liên hệ HR
                </button>
            </p>
        </div>

        {{-- Trust & Security Notice --}}
        <div class="pt-3.5 border-t border-slate-100/90 flex items-center justify-center gap-1.5 text-[11px] font-medium text-[#64748B]">
            <i class="bi bi-shield-check text-emerald-600 text-xs"></i>
            <span>{{ $activeTheme['content']['trustLine'] ?? 'Hệ thống nội bộ TON Capital · Bảo mật thông tin' }}</span>
        </div>
    </form>

    {{-- Modal liên hệ HR --}}
    <div id="contactHrModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs" onclick="if(event.target === this) closeContactHrModal()">
        <div class="bg-white rounded-2xl p-6 w-full max-w-sm shadow-xl border border-slate-100 text-center relative animate-in fade-in zoom-in-95 duration-150">
            <button type="button" onclick="closeContactHrModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600" aria-label="Đóng">
                <i class="bi bi-x-lg text-sm"></i>
            </button>
            <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center mx-auto mb-3">
                <i class="bi bi-headset text-xl"></i>
            </div>
            <h3 class="text-base font-bold text-[#111827]">Hỗ trợ tài khoản TON-HR</h3>
            <p class="text-xs text-[#6B7280] mt-1 leading-relaxed">
                Tài khoản hệ thống TON-HR được quản lý và cấp phát nội bộ bởi phòng Nhân sự &amp; Ban quản trị.
            </p>
            <div class="mt-4 p-3.5 bg-slate-50 rounded-xl text-left space-y-2.5 text-xs text-[#374151]">
                <div class="flex items-center gap-2.5">
                    <i class="bi bi-envelope-fill text-[#2563EB]"></i>
                    <span>Email: <a href="mailto:hr@toncapital.net" class="font-semibold text-[#2563EB] hover:underline">hr@toncapital.net</a></span>
                </div>
                <div class="flex items-center gap-2.5">
                    <i class="bi bi-building-fill text-[#2563EB]"></i>
                    <span>Bộ phận: <span class="font-semibold">Phòng Nhân sự TON Capital</span></span>
                </div>
                <div class="flex items-center gap-2.5">
                    <i class="bi bi-shield-lock-fill text-emerald-600"></i>
                    <span class="text-[#6B7280]">Chỉ áp dụng cho cán bộ nhân viên nội bộ</span>
                </div>
            </div>
            <button type="button" onclick="closeContactHrModal()" class="mt-4 w-full py-2.5 rounded-xl bg-[#2563EB] hover:bg-[#1D4ED8] text-xs font-semibold text-white transition-colors">
                Đã hiểu
            </button>
        </div>
    </div>

    <script>
        function openContactHrModal() {
            var modal = document.getElementById('contactHrModal');
            if (modal) modal.classList.remove('hidden');
        }
        function closeContactHrModal() {
            var modal = document.getElementById('contactHrModal');
            if (modal) modal.classList.add('hidden');
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeContactHrModal();
            }
        });

        (function () {
            var form = document.getElementById('loginForm');
            var submitBtn = document.getElementById('loginSubmitBtn');
            var latInput = document.getElementById('loginLat');
            var lngInput = document.getElementById('loginLng');
            var locationResolved = false;

            if (!form) return;

            form.addEventListener('submit', function (e) {
                if (locationResolved || !navigator.geolocation) {
                    return;
                }
                e.preventDefault();
                if (submitBtn) submitBtn.disabled = true;

                navigator.geolocation.getCurrentPosition(
                    function (pos) {
                        latInput.value = pos.coords.latitude;
                        lngInput.value = pos.coords.longitude;
                        locationResolved = true;
                        if (submitBtn) submitBtn.disabled = false;
                        form.submit();
                    },
                    function () {
                        locationResolved = true;
                        if (submitBtn) submitBtn.disabled = false;
                        form.submit();
                    },
                    { enableHighAccuracy: true, timeout: 5000 }
                );
            });
        })();
    </script>
@endsection
