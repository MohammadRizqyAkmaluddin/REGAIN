<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmokingEvent extends Model
{
    protected $fillable = [
        'user_id',
        'smoking_profile_id',
        'event_type',
        'event_date',
        'event_time',
        'packs_smoked',
        'estimated_cost',
        'trigger',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date:Y-m-d',
            'packs_smoked' => 'decimal:2',
            'estimated_cost' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function smokingProfile(): BelongsTo
    {
        return $this->belongsTo(SmokingProfile::class);
    }
}
