@extends('layouts.guest')

@section('title', 'Đăng nhập')

@section('content')
    <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-1">Đăng nhập</h2>
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">Vui lòng đăng nhập để tiếp tục</p>

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="form-label">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                class="form-input @error('email') border-red-500 @enderror"
                placeholder="nhap@email.com" required autofocus autocomplete="username">
            @error('email')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="form-label">Mật khẩu</label>
            <input id="password" type="password" name="password"
                class="form-input @error('password') border-red-500 @enderror"
                placeholder="••••••••" required autocomplete="current-password">
            @error('password')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400 cursor-pointer">
                <input type="checkbox" name="remember" value="1"
                    class="rounded border-slate-300 dark:border-slate-600 text-pcrm-600 focus:ring-pcrm-500">
                <span>Ghi nhớ đăng nhập</span>
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}"
                    class="text-sm text-pcrm-600 dark:text-pcrm-400 hover:underline">
                    Quên mật khẩu?
                </a>
            @endif
        </div>

        <button type="submit" class="btn-primary w-full">
            <i class="ph-sign-in"></i>
            <span>Đăng nhập</span>
        </button>

        @if (Route::has('register'))
            <p class="text-center text-sm text-slate-500 dark:text-slate-400">
                Chưa có tài khoản?
                <a href="{{ route('register') }}" class="text-pcrm-600 dark:text-pcrm-400 hover:underline font-medium">
                    Đăng ký ngay
                </a>
            </p>
        @endif
    </form>
@endsection
