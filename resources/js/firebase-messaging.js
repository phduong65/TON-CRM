// Thông báo đẩy Chrome qua Firebase Cloud Messaging — bật khi user bấm nút chuông "Bật thông
// báo trình duyệt" trên Topbar (không tự động xin quyền khi load trang: trình duyệt chặn
// auto-prompt không gắn với thao tác rõ ràng của người dùng).
//
// Config (window.FIREBASE_CONFIG) được render bởi layouts/admin.blade.php từ
// config('services.firebase.web') — cùng nguồn dữ liệu với route /firebase-messaging-sw.js.

import { initializeApp } from 'firebase/app';
import { getMessaging, getToken, deleteToken, onMessage, isSupported } from 'firebase/messaging';

let messagingInstance = null;

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

async function getMessagingInstance() {
    if (messagingInstance) return messagingInstance;
    if (!(await isSupported())) return null;

    const config = window.FIREBASE_CONFIG;
    if (!config || !config.apiKey) return null;

    const app = initializeApp(config);
    messagingInstance = getMessaging(app);
    return messagingInstance;
}

function registerTokenOnServer(token) {
    return fetch('/fcm-tokens', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json',
        },
        body: JSON.stringify({ token }),
    }).then((res) => {
        // fetch() chỉ reject khi lỗi mạng — HTTP lỗi (419 CSRF hết hạn, 500...) vẫn resolve
        // bình thường với res.ok = false. Phải tự throw để activatePush() bắt được và không
        // âm thầm coi như đã đăng ký thành công trong khi server chưa lưu token nào.
        if (!res.ok) {
            throw new Error('Đăng ký token thất bại, server trả về HTTP ' + res.status);
        }
        return res;
    });
}

function updatePushButtonVisibility() {
    const btn = document.getElementById('pushEnableBtn');
    if (!btn) return;

    const supported = 'Notification' in window;
    const shouldShow = supported && Notification.permission !== 'granted';

    btn.classList.toggle('hidden', !shouldShow);
    btn.classList.toggle('flex', shouldShow);
}

async function enablePushNotifications() {
    if (!('Notification' in window)) {
        alert('Trình duyệt của bạn không hỗ trợ thông báo đẩy.');
        return;
    }

    const permission = await Notification.requestPermission();
    updatePushButtonVisibility();

    if (permission !== 'granted') {
        alert('Bạn đã từ chối quyền thông báo. Vào cài đặt trình duyệt (biểu tượng khoá cạnh URL) để cấp lại quyền, sau đó thử lại.');
        return;
    }

    await activatePush(true);
}

/**
 * notifyOnError: chỉ true khi được gọi trực tiếp từ nút bấm (enablePushNotifications) — lần tự
 * động chạy lại khi load trang (đã cấp quyền từ trước) không nên làm phiền người dùng bằng alert
 * mỗi lần vào trang nếu server tạm thời lỗi.
 */
async function activatePush(notifyOnError = false) {
    try {
        const messaging = await getMessagingInstance();
        if (!messaging) {
            if (notifyOnError) {
                alert('Trình duyệt không hỗ trợ Firebase Messaging, hoặc thiếu cấu hình FIREBASE_API_KEY trên server.');
            }
            console.error('Firebase messaging không khởi tạo được — kiểm tra window.FIREBASE_CONFIG.apiKey và isSupported().');
            return;
        }

        const registration = await navigator.serviceWorker.register('/firebase-messaging-sw.js');
        const token = await getToken(messaging, {
            vapidKey: window.FIREBASE_CONFIG?.vapidKey,
            serviceWorkerRegistration: registration,
        });

        if (!token) {
            if (notifyOnError) {
                alert('Không lấy được token từ Firebase. Kiểm tra lại VAPID key (FIREBASE_VAPID_KEY) và domain hiện tại có nằm trong danh sách Authorized domains của Firebase project không.');
            }
            console.error('getToken() trả về rỗng — thường do sai VAPID key hoặc domain chưa được whitelist trên Firebase Console.');
            return;
        }

        await registerTokenOnServer(token);

        if (notifyOnError) {
            alert('Đã bật thông báo đẩy thành công cho trình duyệt này!');
        }

        // Hiển thị thông báo khi tab đang mở/foreground — Firebase không tự hiển thị trong trường hợp này.
        onMessage(messaging, (payload) => {
            const title = payload.notification?.title || 'Thông báo mới';
            const body = payload.notification?.body || '';
            const notif = new Notification(title, { body, icon: '/assets/images/ton-capital-logo.png' });
            notif.onclick = () => {
                window.focus();
                const link = payload.fcmOptions?.link || payload.data?.url;
                if (link) window.location.href = link;
            };
        });
    } catch (e) {
        console.error('Không thể bật thông báo đẩy:', e);
        if (notifyOnError) {
            alert('Bật thông báo đẩy thất bại: ' + (e?.message || e));
        }
    }
}

/**
 * Xoá token FCM đang cache trong trình duyệt (IndexedDB) rồi đăng ký lại token mới — dùng khi
 * server phát hiện token hiện tại đã bị Firebase từ chối (unregistered/stale, ví dụ lỗi
 * "Requested entity was not found") nhưng chỉ gọi lại getToken() thì SDK vẫn trả về đúng token
 * cũ đó từ cache, không tự nhận ra token đã chết.
 */
async function refreshPushToken() {
    const messaging = await getMessagingInstance();
    if (!messaging) return false;

    try {
        await deleteToken(messaging);
    } catch (e) {
        // Token có thể đã không còn tồn tại phía Firebase — bỏ qua, vẫn tiếp tục lấy token mới.
        console.warn('Xoá token FCM cũ thất bại (bỏ qua, vẫn thử lấy token mới):', e);
    }

    await activatePush(false);
    return true;
}

window.enablePushNotifications = enablePushNotifications;
window.refreshPushToken = refreshPushToken;

document.addEventListener('DOMContentLoaded', () => {
    updatePushButtonVisibility();

    // Đã cấp quyền từ trước (lần trước đã bấm nút) — tự đăng ký lại token mỗi lần load trang
    // (không cần hỏi quyền lại), để token luôn tồn tại/được làm mới trên server.
    if ('Notification' in window && Notification.permission === 'granted') {
        activatePush();
    }
});
