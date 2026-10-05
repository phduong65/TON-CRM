@extends('layouts.auth')

@section('title', 'Đặt lại mật khẩu')

@section('content')
    <h1 class="text-[36px] leading-tight font-bold text-[#111827] tracking-tight">Đặt lại mật khẩu</h1>
    <p class="mt-2 text-base text-[#6B7280]">Nhập mật khẩu mới cho tài khoản của bạn.</p>

    @if (session('status'))
        <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.update') }}" class="mt-8 space-y-5" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        {{-- Email --}}
        <div>
            <label for="email" class="block text-sm font-medium text-[#111827] mb-1.5">Email</label>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-[#9CA3AF]">
                    <i class="bi bi-envelope text-base"></i>
                </span>
                <input id="email" type="email" name="email" value="{{ old('email', request()->email) }}"
                    class="auth-input w-full pl-11 pr-4 text-sm @error('email') has-error @enderror"
                    placeholder="ban@congty.com" required autofocus autocomplete="username">
            </div>
            @error('email')
                <p class="mt-1.5 text-xs text-[#DC2626]">{{ $message }}</p>
            @enderror
        </div>

        {{-- Password --}}
        <div>
            <label for="password" class="block text-sm font-medium text-[#111827] mb-1.5">Mật khẩu mới</label>
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

        <button type="submit" class="auth-btn-primary w-full flex items-center justify-center gap-2 text-sm">
            <span>Đặt lại mật khẩu</span>
            <i class="bi bi-check-lg text-sm"></i>
        </button>
    </form>
@endsection
