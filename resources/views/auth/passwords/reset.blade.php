@extends('layouts.guest')

@section('title', 'Đặt lại mật khẩu')

@section('content')
    <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-1">Đặt lại mật khẩu</h2>
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">Nhập mật khẩu mới cho tài khoản của bạn.</p>

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <label for="email" class="form-label">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email', request()->email) }}"
                class="form-input @error('email') border-red-500 @enderror"
                placeholder="nhap@email.com" required autofocus autocomplete="username">
            @error('email')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="form-label">Mật khẩu mới</label>
            <input id="password" type="password" name="password"
                class="form-input @error('password') border-red-500 @enderror"
                placeholder="•••••••• (tối thiểu 8 ký tự)" required autocomplete="new-password">
            @error('password')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password-confirm" class="form-label">Xác nhận mật khẩu</label>
            <input id="password-confirm" type="password" name="password_confirmation"
                class="form-input"
                placeholder="Nhập lại mật khẩu" required autocomplete="new-password">
        </div>

        <button type="submit" class="btn-primary w-full">
            <i class="ph-check-circle"></i>
            <span>Đặt lại mật khẩu</span>
        </button>
    </form>
@endsection
