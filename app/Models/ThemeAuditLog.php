<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThemeAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'theme_id',
        'actor_id',
        'action',
        'reason',
        'before',
        'after',
        'ip_address',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'before'      => 'array',
            'after'       => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
