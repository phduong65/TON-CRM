// Giữ session sống khi nhân viên để trang/modal mở lâu (điền phiếu phạt, chấm công...),
// tránh lỗi 419 (CSRF token mismatch) khi họ submit sau một khoảng thời gian dài không
// có request nào tới server. Chỉ chạy trên layout admin (đã đăng nhập) — nhận diện qua
// phần tử #pcrm-alert-overlay chỉ tồn tại trong layouts/admin.blade.php.
(function () {
    if (!document.getElementById('pcrm-alert-overlay')) {
        return;
    }

    var PING_INTERVAL_MS = 5 * 60 * 1000; // 5 phút
    var pingTimer = null;

    function ping() {
        fetch('/keep-alive', {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        }).then(function (res) {
            // Session đã hết hạn hẳn (bị đăng xuất/dọn session) → tải lại trang để nhân viên
            // thấy trang đăng nhập/419 thân thiện ngay, thay vì âm thầm mất dữ liệu khi submit.
            if (res.status === 401 || res.status === 419) {
                window.location.reload();
            }
        }).catch(function () {
            // Mất mạng tạm thời — bỏ qua, thử lại ở lượt ping kế tiếp.
        });
    }

    function startPinging() {
        if (pingTimer) return;
        pingTimer = setInterval(ping, PING_INTERVAL_MS);
    }

    function stopPinging() {
        if (pingTimer) {
            clearInterval(pingTimer);
            pingTimer = null;
        }
    }

    // Tab quay lại foreground sau khi bị ẩn/throttle (đổi tab, khoá màn hình...) → ping ngay
    // để "hồi" session kịp thời trước khi nhân viên thao tác tiếp.
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            ping();
            startPinging();
        } else {
            stopPinging();
        }
    });

    if (document.visibilityState === 'visible') {
        startPinging();
    }
})();
