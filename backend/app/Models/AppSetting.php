<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppSetting extends Model
{
    protected $fillable = [
        'user_id',
        'theme',
        'locale',
        'timezone',
        'currency',
        'notifications_enabled',
        'active_hours_start',
        'active_hours_end',
        'max_daily_notifications',
        'notification_cooldown_minutes',
    ];

    protected function casts(): array
    {
        return [
            'notifications_enabled' => 'boolean',
            'max_daily_notifications' => 'integer',
            'notification_cooldown_minutes' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
