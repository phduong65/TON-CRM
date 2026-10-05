@extends('layouts.auth')

@section('title', 'Quên mật khẩu')

@section('content')
    <h1 class="text-[28px] sm:text-[30px] leading-tight font-bold text-[#111827] tracking-tight">Quên mật khẩu?</h1>
    <p class="mt-2 text-[13px] leading-[18px] text-[#6B7280]">Nhập email công ty và chúng tôi sẽ gửi liên kết đặt lại mật khẩu.</p>

    @if (session('status'))
        <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4" novalidate>
        @csrf

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

        <div class="pt-1.5">
            <button type="submit" class="auth-btn-primary w-full flex items-center justify-center gap-2 text-sm shadow-xs hover:shadow-md transition-all">
                <span>Gửi liên kết đặt lại</span>
                <i class="bi bi-send text-xs"></i>
            </button>
        </div>

        <p class="text-center text-sm text-[#6B7280]">
            <a href="{{ route('login') }}" class="font-medium text-[#2563EB] hover:text-[#1D4ED8]">
                <i class="bi bi-arrow-left"></i> Quay lại đăng nhập
            </a>
        </p>
    </form>
@endsection
