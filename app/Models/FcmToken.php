<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registration token của Firebase Cloud Messaging cho 1 trình duyệt/thiết bị đã cấp quyền
 * nhận thông báo đẩy (Web Push). Một user có thể có nhiều token (nhiều trình duyệt/máy).
 */
class FcmToken extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'user_agent',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
