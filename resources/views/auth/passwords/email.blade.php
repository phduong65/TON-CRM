@extends('layouts.guest')

@section('title', 'Quên mật khẩu')

@section('content')
    <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-1">Quên mật khẩu</h2>
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">
        Nhập email của bạn và chúng tôi sẽ gửi link đặt lại mật khẩu.
    </p>

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="form-label">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                class="form-input @error('email') border-red-500 @enderror"
                placeholder="nhap@email.com" required autofocus>
            @error('email')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="btn-primary w-full">
            <i class="ph-paper-plane-right"></i>
            <span>Gửi link đặt lại mật khẩu</span>
        </button>

        <p class="text-center text-sm text-slate-500 dark:text-slate-400">
            <a href="{{ route('login') }}" class="text-pcrm-600 dark:text-pcrm-400 hover:underline">
                <i class="ph-arrow-left"></i> Quay lại đăng nhập
            </a>
        </p>
    </form>
@endsection
