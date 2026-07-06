<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Gửi cho chính người đăng ký khi Admin duyệt (gán vai trò + kích hoạt) tài khoản của họ —
 * báo họ có thể đăng nhập được rồi. Xem UsersController::update().
 */
class AccountApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $approvedUser)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[' . config('app.name') . '] Tài khoản của bạn đã được duyệt',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account-approved',
            with: [
                'approvedUser' => $this->approvedUser,
                'loginUrl'     => route('login'),
            ],
        );
    }
}
