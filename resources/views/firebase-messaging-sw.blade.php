importScripts('https://www.gstatic.com/firebasejs/10.13.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.13.0/firebase-messaging-compat.js');

firebase.initializeApp(@json($config));

const messaging = firebase.messaging();

// Firebase không tự hiển thị notification khi tab đang đóng/nền — phải tự showNotification().
messaging.onBackgroundMessage((payload) => {
    const title = payload.notification?.title || 'Thông báo mới';
    const body = payload.notification?.body || '';
    const link = payload.fcmOptions?.link || payload.data?.url || '/';

    self.registration.showNotification(title, {
        body: body,
        icon: '/assets/images/ton-capital-logo.png',
        badge: '/assets/images/ton-capital-logo.png',
        data: link,
    });
});

// Click vào notification → mở đúng trang liên quan (hoặc focus tab đang mở sẵn).
self.addEventListener('notificationclick', (event) => {
    const link = event.notification.data || '/';
    event.notification.close();

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            for (const client of windowClients) {
                if (client.url === link && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(link);
            }
        })
    );
});
