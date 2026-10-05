@php
    // Referer là nguồn tin cậy nhất cho "trang trước đó" khi lỗi 419 xảy ra — vì session
    // lúc này có thể đã bị dọn/khởi tạo lại, url()->previous() (dựa vào session) không
    // còn chắc chắn đúng. Chỉ dùng nếu cùng domain để tránh open-redirect.
    $referer = request()->headers->get('referer');
    $backUrl = ($referer && str_starts_with($referer, url('/'))) ? $referer : route('dashboard');
@endphp
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="4;url={{ $backUrl }}">
    <title>Phiên làm việc đã hết hạn — {{ config('app.name', 'TON-HR') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=be-vietnam-pro:400,500,600,700,800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />

    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen flex items-center justify-center bg-[#F8FAFC] px-4" style="font-family: 'Be Vietnam Pro', ui-sans-serif, system-ui, sans-serif;">

    <div class="w-full max-w-sm bg-white rounded-2xl shadow-[0_10px_30px_rgba(15,23,42,.08)] p-8 text-center">
        <div class="w-16 h-16 rounded-full bg-amber-50 mx-auto mb-5 flex items-center justify-center">
            <i class="bi bi-clock-history text-2xl text-amber-500"></i>
        </div>

        <h1 class="text-lg font-bold text-[#111827] mb-2">Phiên làm việc đã hết hạn</h1>
        <p class="text-sm text-[#6B7280] leading-relaxed mb-6">
            Để bảo mật, hệ thống tự làm mới phiên làm việc cũ. Thao tác vừa rồi chưa được lưu,
            bạn vui lòng thực hiện lại nhé.
        </p>

        <p class="text-xs text-[#9CA3AF] mb-5">
            Tự động quay lại sau <span id="pcrm419Secs">4</span> giây...
        </p>

        <a href="{{ $backUrl }}"
           class="inline-flex w-full items-center justify-center gap-2 h-11 rounded-xl bg-[#2563EB] text-white text-sm font-semibold hover:bg-[#1D4ED8] transition-colors">
            Quay lại ngay
            <i class="bi bi-arrow-right text-sm"></i>
        </a>
    </div>

    <script>
        (function () {
            var el = document.getElementById('pcrm419Secs');
            var secs = 4;
            var timer = setInterval(function () {
                secs -= 1;
                if (el) el.textContent = Math.max(secs, 0);
                if (secs <= 0) clearInterval(timer);
            }, 1000);
        })();
    </script>
</body>
</html>
