@extends('emails.layout')

@section('content')
    <h1 style="margin:0 0 16px; font-size:18px; color:#0F172A;">Có tài khoản mới đăng ký</h1>

    <p style="margin:0 0 16px; font-size:14px; line-height:1.6; color:#334155;">
        Một tài khoản mới vừa đăng ký và đang <strong>chờ bạn duyệt</strong> trước khi có thể đăng nhập vào hệ thống.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F8FAFC; border-radius:12px; margin-bottom:20px;">
        <tr>
            <td style="padding:16px 20px; font-size:13px; color:#334155;">
                <p style="margin:0 0 6px;"><strong>Họ tên:</strong> {{ $registeredUser->name }}</p>
                <p style="margin:0 0 6px;"><strong>Email:</strong> {{ $registeredUser->email }}</p>
                <p style="margin:0;"><strong>Thời gian đăng ký:</strong> {{ $registeredUser->created_at?->format('H:i d/m/Y') }}</p>
            </td>
        </tr>
    </table>

    <a href="{{ $usersUrl }}"
       style="display:inline-block; background-color:#2563EB; color:#FFFFFF; text-decoration:none; font-size:14px; font-weight:600; padding:12px 24px; border-radius:10px;">
        Vào Quản lý người dùng để duyệt
    </a>

    <p style="margin:20px 0 0; font-size:12px; color:#94A3B8; line-height:1.6;">
        Tài khoản này chưa có bất kỳ quyền truy cập nào cho tới khi được duyệt và gán vai trò.
    </p>
@endsection
