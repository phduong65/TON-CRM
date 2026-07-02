<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Đăng nhập — {{ config('app.name', 'TON-HR') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        .auth-page { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; background-color: #F8FAFC; }
        .auth-input {
            height: 50px;
            border: 1px solid #E5E7EB;
            border-radius: 12px;
            background-color: #FFFFFF;
            color: #111827;
            transition: border-color 200ms ease, box-shadow 200ms ease;
        }
        .auth-input::placeholder { color: #9CA3AF; }
        .auth-input:focus {
            outline: none;
            border-color: #2563EB;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }
        .auth-input.has-error { border-color: #DC2626; }
        .auth-btn-primary {
            height: 50px;
            border-radius: 12px;
            background-color: #2563EB;
            color: #FFFFFF;
            font-weight: 600;
            transition: background-color 200ms ease;
        }
        .auth-btn-primary:hover { background-color: #1D4ED8; }
        .auth-btn-social {
            height: 50px;
            border-radius: 12px;
            border: 1px solid #E5E7EB;
            background-color: #FFFFFF;
            color: #111827;
            transition: background-color 200ms ease, border-color 200ms ease;
        }
        .auth-btn-social:hover { background-color: #F8FAFC; border-color: #D1D5DB; }
        .auth-card-illustration {
            background: linear-gradient(180deg, #EAF4FF, #DDEEFF);
            border-radius: 32px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, .06);
        }
        .auth-orbit {
            border: 1px solid rgba(37, 99, 235, 0.12);
            border-radius: 9999px;
            position: absolute;
        }
        .auth-node {
            position: absolute;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 9999px;
            background-color: #FFFFFF;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .08);
            color: #2563EB;
            animation: auth-float 5s ease-in-out infinite;
        }
        .auth-node svg { width: 22px; height: 22px; }
        @keyframes auth-float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        .auth-glow {
            position: absolute;
            border-radius: 9999px;
            background: radial-gradient(circle, rgba(59,130,246,0.22) 0%, rgba(59,130,246,0) 70%);
        }
        @media (prefers-reduced-motion: reduce) {
            .auth-node { animation: none; }
        }
    </style>
</head>
<body class="auth-page min-h-screen">
    <div class="min-h-screen grid lg:grid-cols-5">

        {{-- ============ FORM COLUMN — 40% (right side) ============ --}}
        <div class="lg:col-span-2 lg:order-2 flex flex-col px-6 sm:px-10 lg:px-16 py-8">

            <a href="{{ url('/') }}" class="inline-flex items-center gap-3 self-start">
                <img src="{{ asset('assets/images/TON CAPITAL_LOGO-06.png') }}" alt="{{ config('app.name', 'TON-HR') }}"
                     class="w-10 h-10 rounded-lg object-cover">
                <span class="text-base font-bold text-[#111827]">{{ config('app.name', 'TON-HR') }}</span>
            </a>

            <div class="flex-1 flex items-center">
                <div class="w-full  mx-auto py-10">

                    <h1 class="text-[36px] leading-tight font-bold text-[#111827] tracking-tight">Chào mừng trở lại</h1>
                    <p class="mt-2 text-base text-[#6B7280]">Đăng nhập để tiếp tục quản lý kỷ luật &amp; nhân sự.</p>

                    @if (session('status'))
                        <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                            {{ session('status') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5" novalidate>
                        @csrf

                        {{-- Email --}}
                        <div>
                            <label for="email" class="block text-sm font-medium text-[#111827] mb-1.5">Email</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-[#9CA3AF]">
                                    <i class="bi bi-envelope text-base"></i>
                                </span>
                                <input id="email" type="email" name="email" value="{{ old('email') }}"
                                    class="auth-input w-full pl-11 pr-4 text-sm @error('email') has-error @enderror"
                                    placeholder="ban@congty.com" required autofocus autocomplete="username">
                            </div>
                            @error('email')
                                <p class="mt-1.5 text-xs text-[#DC2626]">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Password --}}
                        <div>
                            <label for="password" class="block text-sm font-medium text-[#111827] mb-1.5">Mật khẩu</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-[#9CA3AF]">
                                    <i class="bi bi-lock text-base"></i>
                                </span>
                                <input id="password" type="password" name="password"
                                    class="auth-input w-full pl-11 pr-11 text-sm @error('password') has-error @enderror"
                                    placeholder="••••••••" required autocomplete="current-password">
                                <button type="button" onclick="togglePasswordVisibility()"
                                    class="absolute inset-y-0 right-0 flex items-center pr-4 text-[#9CA3AF] hover:text-[#6B7280]"
                                    aria-label="Hiện/ẩn mật khẩu">
                                    <i id="passwordToggleIcon" class="bi bi-eye text-base"></i>
                                </button>
                            </div>
                            @error('password')
                                <p class="mt-1.5 text-xs text-[#DC2626]">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Remember + Forgot --}}
                        <div class="flex items-center justify-between text-sm">
                            <label class="flex items-center gap-2 text-[#6B7280] cursor-pointer select-none">
                                <input type="checkbox" name="remember" value="1"
                                    class="w-4 h-4 rounded border-[#E5E7EB] text-[#2563EB] focus:ring-[#2563EB]">
                                <span>Ghi nhớ đăng nhập</span>
                            </label>

                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}" class="font-medium text-[#2563EB] hover:text-[#1D4ED8]">
                                    Quên mật khẩu?
                                </a>
                            @endif
                        </div>

                        <button type="submit" class="auth-btn-primary w-full flex items-center justify-center gap-2 text-sm">
                            <span>Đăng nhập</span>
                            <i class="bi bi-arrow-right text-sm"></i>
                        </button>

                        {{-- Divider --}}
                        <div class="flex items-center gap-3 pt-1">
                            <span class="flex-1 h-px bg-[#E5E7EB]"></span>
                            <span class="text-xs font-medium text-[#6B7280] whitespace-nowrap">Hoặc đăng nhập bằng</span>
                            <span class="flex-1 h-px bg-[#E5E7EB]"></span>
                        </div>

                        {{-- Social buttons --}}
                        <div class="grid grid-cols-3 gap-3">
                            <button type="button" class="auth-btn-social flex items-center justify-center" title="Đăng nhập với Google">
                                <i class="bi bi-google text-base"></i>
                            </button>
                            <button type="button" class="auth-btn-social flex items-center justify-center" title="Đăng nhập với Microsoft">
                                <i class="bi bi-microsoft text-base"></i>
                            </button>
                            <button type="button" class="auth-btn-social flex items-center justify-center" title="Đăng nhập với Apple">
                                <i class="bi bi-apple text-base"></i>
                            </button>
                        </div>

                        @if (Route::has('register'))
                            <p class="text-center text-sm text-[#6B7280]">
                                Chưa có tài khoản?
                                <a href="{{ route('register') }}" class="font-medium text-[#2563EB] hover:text-[#1D4ED8]">Đăng ký ngay</a>
                            </p>
                        @endif
                    </form>
                </div>
            </div>
        </div>

        {{-- ============ ILLUSTRATION COLUMN — 60% (left side) ============ --}}
        <div class="hidden lg:flex lg:col-span-3 lg:order-1 p-6">
            <div class="auth-card-illustration relative w-full overflow-hidden flex items-center justify-center">

                {{-- concentric circles --}}
                <div class="auth-orbit" style="width:560px; height:560px;"></div>
                <div class="auth-orbit" style="width:400px; height:400px;"></div>
                <div class="auth-orbit" style="width:250px; height:250px;"></div>

                {{-- soft glow --}}
                <div class="auth-glow" style="width:420px; height:420px;"></div>

                {{-- connecting lines --}}
                <svg class="absolute" width="560" height="560" viewBox="0 0 560 560" fill="none">
                    <line x1="280" y1="280" x2="80"  y2="120" stroke="#93C5FD" stroke-width="1.5" stroke-dasharray="4 4" />
                    <line x1="280" y1="280" x2="480" y2="130" stroke="#93C5FD" stroke-width="1.5" stroke-dasharray="4 4" />
                    <line x1="280" y1="280" x2="70"  y2="430" stroke="#93C5FD" stroke-width="1.5" stroke-dasharray="4 4" />
                    <line x1="280" y1="280" x2="470" y2="440" stroke="#93C5FD" stroke-width="1.5" stroke-dasharray="4 4" />
                </svg>

                {{-- floating icon nodes --}}
                <div class="auth-node" style="width:64px; height:64px; top:110px; left:60px; animation-delay:0s;">
                    <i class="bi bi-cloud-fill"></i>
                </div>
                <div class="auth-node" style="width:56px; height:56px; top:100px; right:60px; animation-delay:.4s;">
                    <i class="bi bi-cpu-fill"></i>
                </div>
                <div class="auth-node" style="width:60px; height:60px; bottom:110px; left:50px; animation-delay:.8s;">
                    <i class="bi bi-envelope-fill"></i>
                </div>
                <div class="auth-node" style="width:56px; height:56px; bottom:100px; right:50px; animation-delay:1.2s;">
                    <i class="bi bi-briefcase-fill"></i>
                </div>

                {{-- center node --}}
                <div class="auth-node" style="width:96px; height:96px; animation-delay:.2s;">
                    <i class="bi bi-window" style="width:32px; height:32px; font-size:32px;"></i>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePasswordVisibility() {
            const input = document.getElementById('password');
            const icon = document.getElementById('passwordToggleIcon');
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            icon.classList.toggle('bi-eye', !isHidden);
            icon.classList.toggle('bi-eye-slash', isHidden);
        }
    </script>
</body>
</html>
