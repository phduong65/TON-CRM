<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Gửi cho chính người đăng ký khi Admin từ chối yêu cầu đăng ký của họ.
 * Xem UsersController::reject().
 */
class AccountRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $rejectedUser)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[' . config('app.name') . '] Yêu cầu đăng ký tài khoản chưa được duyệt',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account-rejected',
            with: [
                'rejectedUser' => $this->rejectedUser,
            ],
        );
    }
}
