@extends('emails.layout')

@section('content')
    <h1 style="margin:0 0 16px; font-size:18px; color:#0F172A;">Tài khoản của bạn đã được duyệt 🎉</h1>

    <p style="margin:0 0 16px; font-size:14px; line-height:1.6; color:#334155;">
        Xin chào <strong>{{ $approvedUser->name }}</strong>, tài khoản của bạn đã được quản trị viên duyệt và
        kích hoạt. Bạn có thể đăng nhập vào hệ thống ngay bây giờ.
    </p>

    <a href="{{ $loginUrl }}"
       style="display:inline-block; background-color:#16A34A; color:#FFFFFF; text-decoration:none; font-size:14px; font-weight:600; padding:12px 24px; border-radius:10px;">
        Đăng nhập ngay
    </a>
@endsection
