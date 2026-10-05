<?php

namespace App\Services;

use App\Models\FcmToken;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\Notification as FcmNotification;

/**
 * Gửi thông báo đẩy Chrome (Web Push) qua Firebase Cloud Messaging tới các trình duyệt đã
 * đăng ký (bảng fcm_tokens) của 1 user — được NotificationService gọi song song với việc tạo
 * App\Models\Notification, để mọi loại thông báo trong app đều có thêm bản Chrome notification.
 *
 * Tự động no-op (không throw) khi chưa cấu hình service account JSON — cho phép app chạy bình
 * thường ở môi trường dev/test chưa thiết lập Firebase (xem config('services.firebase')).
 */
class PushNotificationService
{
    private bool $enabled;
    private ?Messaging $messaging = null;

    public function __construct()
    {
        $credentials = config('services.firebase.credentials');
        $this->enabled = is_string($credentials) && $credentials !== '' && file_exists($credentials);
    }

    /**
     * Gửi push tới toàn bộ trình duyệt/thiết bị đã đăng ký của 1 user. Không throw ra ngoài —
     * lỗi gửi push không được phép làm hỏng luồng nghiệp vụ chính (tạo phiếu phạt, duyệt đơn...).
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = [], ?string $url = null): void
    {
        if (!$this->enabled) {
            return;
        }

        $tokens = FcmToken::where('user_id', $userId)->pluck('token');
        if ($tokens->isEmpty()) {
            return;
        }

        try {
            $message = CloudMessage::new()
                ->withNotification(FcmNotification::create($title, $body))
                ->withData($this->stringifyData($data) + ['url' => $url ?? '/'])
                ->withWebPushConfig(['fcm_options' => ['link' => $url ?? '/']]);

            $report = $this->messaging()->sendMulticast($message, $tokens->all());

            $this->pruneInvalidTokens($report);
        } catch (\Throwable $e) {
            Log::warning('Gửi thông báo đẩy Firebase thất bại', ['user_id' => $userId, 'error' => $e->getMessage()]);
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Gửi 1 thông báo test tới toàn bộ trình duyệt đã đăng ký của 1 user và trả về kết quả chi
     * tiết — dùng cho nút "Kiểm tra kết nối" ở trang Cài đặt. Khác với sendToUser() (vốn no-op
     * im lặng khi lỗi để không làm hỏng luồng nghiệp vụ chính), hàm này CẦN báo lỗi cụ thể cho
     * người dùng biết kết nối Firebase có thực sự hoạt động hay không.
     */
    public function testConnection(int $userId): array
    {
        if (!$this->enabled) {
            return ['ok' => false, 'reason' => 'not_configured'];
        }

        $tokens = FcmToken::where('user_id', $userId)->pluck('token');
        if ($tokens->isEmpty()) {
            return ['ok' => false, 'reason' => 'no_tokens'];
        }

        try {
            $message = CloudMessage::new()
                ->withNotification(FcmNotification::create(
                    'Kiểm tra kết nối Firebase',
                    'Nếu bạn thấy thông báo này, kết nối Firebase Cloud Messaging đang hoạt động tốt!'
                ))
                ->withData(['url' => '/settings'])
                ->withWebPushConfig(['fcm_options' => ['link' => '/settings']]);

            $report = $this->messaging()->sendMulticast($message, $tokens->all());
            $this->pruneInvalidTokens($report);

            $failures = $report->failures();
            $firstError = $failures->count() > 0 ? $failures->getItems()[0]->error()?->getMessage() : null;

            if ($firstError) {
                Log::warning('Kiểm tra kết nối Firebase: 1 số token gửi thất bại', ['user_id' => $userId, 'error' => $firstError]);
            }

            return [
                'ok'            => $report->successes()->count() > 0,
                'reason'        => $failures->count() > 0 ? 'send_failed' : null,
                'success_count' => $report->successes()->count(),
                'failure_count' => $failures->count(),
                'token_count'   => $tokens->count(),
                'error'         => $firstError,
            ];
        } catch (\Throwable $e) {
            Log::warning('Kiểm tra kết nối Firebase thất bại', ['user_id' => $userId, 'error' => $e->getMessage()]);

            return ['ok' => false, 'reason' => 'exception', 'error' => $e->getMessage()];
        }
    }

    private function messaging(): Messaging
    {
        if ($this->messaging) {
            return $this->messaging;
        }

        $factory = (new Factory())->withServiceAccount(config('services.firebase.credentials'));

        return $this->messaging = $factory->createMessaging();
    }

    /**
     * Xoá các token Firebase báo không còn tồn tại/không hợp lệ (app đã gỡ, quyền bị thu hồi,
     * hoặc trình duyệt hết hạn token) — tránh gửi lặp lại vào những trình duyệt không còn nhận được.
     */
    private function pruneInvalidTokens(MulticastSendReport $report): void
    {
        $staleTokens = array_merge($report->unknownTokens(), $report->invalidTokens());

        if ($staleTokens !== []) {
            FcmToken::whereIn('token', $staleTokens)->delete();
        }
    }

    /**
     * FCM data payload chỉ chấp nhận string => string.
     */
    private function stringifyData(array $data): array
    {
        return array_map(static fn($value) => is_scalar($value) ? (string) $value : json_encode($value), $data);
    }
}
