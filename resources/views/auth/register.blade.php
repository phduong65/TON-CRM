@extends('layouts.auth')

@section('title', 'Đăng ký')

@section('content')
    <h1 class="text-[28px] sm:text-[32px] leading-tight font-bold text-[#111827] tracking-tight">Tạo tài khoản</h1>
    <p class="mt-1.5 sm:mt-2 text-sm sm:text-base text-[#6B7280]">Đăng ký để bắt đầu quản lý nhân sự &amp; đội ngũ.</p>

    @if (session('status'))
        <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4 sm:space-y-5" novalidate>
        @csrf

        {{-- Name --}}
        <div>
            <label for="name" class="block text-sm font-medium text-[#111827] mb-1.5">Họ và tên</label>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-[#9CA3AF]">
                    <i class="bi bi-person text-base"></i>
                </span>
                <input id="name" type="text" name="name" value="{{ old('name') }}"
                    class="auth-input w-full pl-11 pr-4 text-sm @error('name') has-error @enderror"
                    placeholder="Nguyễn Văn A" required autofocus autocomplete="name">
            </div>
            @error('name')
                <p class="mt-1.5 text-xs text-[#DC2626]">{{ $message }}</p>
            @enderror
        </div>

        {{-- Email --}}
        <div>
            <label for="email" class="block text-sm font-medium text-[#111827] mb-1.5">Email</label>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-[#9CA3AF]">
                    <i class="bi bi-envelope text-base"></i>
                </span>
                <input id="email" type="email" name="email" value="{{ old('email') }}"
                    class="auth-input w-full pl-11 pr-4 text-sm @error('email') has-error @enderror"
                    placeholder="Nhập email công ty" required autocomplete="username">
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
                    placeholder="•••••••• (tối thiểu 8 ký tự)" required autocomplete="new-password">
                <button type="button" onclick="togglePasswordVisibility('password', 'passwordToggleIcon')"
                    class="absolute inset-y-0 right-0 flex items-center pr-4 text-[#9CA3AF] hover:text-[#6B7280]"
                    aria-label="Hiện/ẩn mật khẩu">
                    <i id="passwordToggleIcon" class="bi bi-eye text-base"></i>
                </button>
            </div>
            @error('password')
                <p class="mt-1.5 text-xs text-[#DC2626]">{{ $message }}</p>
            @enderror
        </div>

        {{-- Confirm Password --}}
        <div>
            <label for="password-confirm" class="block text-sm font-medium text-[#111827] mb-1.5">Xác nhận mật khẩu</label>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-[#9CA3AF]">
                    <i class="bi bi-lock text-base"></i>
                </span>
                <input id="password-confirm" type="password" name="password_confirmation"
                    class="auth-input w-full pl-11 pr-11 text-sm"
                    placeholder="Nhập lại mật khẩu" required autocomplete="new-password">
                <button type="button" onclick="togglePasswordVisibility('password-confirm', 'passwordConfirmToggleIcon')"
                    class="absolute inset-y-0 right-0 flex items-center pr-4 text-[#9CA3AF] hover:text-[#6B7280]"
                    aria-label="Hiện/ẩn mật khẩu">
                    <i id="passwordConfirmToggleIcon" class="bi bi-eye text-base"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="auth-btn-primary w-full flex items-center justify-center gap-2 text-sm shadow-xs hover:shadow-md transition-all">
            <span>Đăng ký</span>
            <i class="bi bi-arrow-right text-xs"></i>
        </button>

        <p class="text-center text-sm text-[#6B7280]">
            Đã có tài khoản?
            <a href="{{ route('login') }}" class="font-medium text-[#2563EB] hover:text-[#1D4ED8]">Đăng nhập</a>
        </p>
    </form>
@endsection
