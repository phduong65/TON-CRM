@extends('layouts.guest')

@section('title', 'Đăng ký')

@section('content')
    <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-1">Đăng ký</h2>
    <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">Tạo tài khoản mới</p>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <div>
            <label for="name" class="form-label">Họ và tên</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}"
                class="form-input @error('name') border-red-500 @enderror"
                placeholder="Nguyễn Văn A" required autofocus autocomplete="name">
            @error('name')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="email" class="form-label">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                class="form-input @error('email') border-red-500 @enderror"
                placeholder="nhap@email.com" required autocomplete="username">
            @error('email')
                <p class="form-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="form-label">Mật khẩu</label>
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
            <i class="ph-user-plus"></i>
            <span>Đăng ký</span>
        </button>

        <p class="text-center text-sm text-slate-500 dark:text-slate-400">
            Đã có tài khoản?
            <a href="{{ route('login') }}" class="text-pcrm-600 dark:text-pcrm-400 hover:underline font-medium">
                Đăng nhập
            </a>
        </p>
    </form>
@endsection
