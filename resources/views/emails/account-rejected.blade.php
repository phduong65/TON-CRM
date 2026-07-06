@extends('emails.layout')

@section('content')
    <h1 style="margin:0 0 16px; font-size:18px; color:#0F172A;">Yêu cầu đăng ký chưa được duyệt</h1>

    <p style="margin:0 0 16px; font-size:14px; line-height:1.6; color:#334155;">
        Xin chào <strong>{{ $rejectedUser->name }}</strong>, yêu cầu đăng ký tài khoản của bạn tại hệ thống
        {{ config('app.name') }} chưa được quản trị viên duyệt. Vui lòng liên hệ quản trị viên hoặc bộ phận
        nhân sự để biết thêm chi tiết.
    </p>
@endsection
