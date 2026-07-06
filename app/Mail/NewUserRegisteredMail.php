<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Gửi cho các user có quyền `manage-users` (admin) khi có tài khoản tự đăng ký mới —
 * nhắc admin vào Quản lý người dùng để Duyệt (gán vai trò + kích hoạt) hoặc Từ chối.
 * Xem RegisterController::register().
 */
class NewUserRegisteredMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $registeredUser)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[' . config('app.name') . '] Tài khoản mới chờ duyệt — ' . $this->registeredUser->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-user-registered',
            with: [
                'registeredUser' => $this->registeredUser,
                'usersUrl'       => route('users.index', ['status' => 'pending']),
            ],
        );
    }
}
