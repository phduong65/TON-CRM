<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
</head>
{{--
    Email HTML KHÔNG dùng Tailwind (không compile trong mail client) — inline style thuần theo
    đúng quy ước "chỉ dùng framework khi thực sự cần" của dự án, áp dụng cho ngữ cảnh email.
--}}
<body style="margin:0; padding:0; background-color:#F1F5F9; font-family: -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F1F5F9; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px; background-color:#FFFFFF; border-radius:16px; overflow:hidden; box-shadow:0 4px 16px rgba(15,23,42,0.06);">
                    <tr>
                        <td style="background-color:#2563EB; padding:20px 28px;">
                            <span style="color:#FFFFFF; font-size:16px; font-weight:700;">{{ config('app.name') }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 28px; background-color:#F8FAFC; border-top:1px solid #E5E7EB;">
                            <p style="margin:0; font-size:12px; color:#94A3B8;">
                                Email tự động từ hệ thống {{ config('app.name') }} — vui lòng không trả lời email này.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
