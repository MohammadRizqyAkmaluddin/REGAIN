<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmokingSaving extends Model
{
    protected $fillable = [
        'user_id',
        'smoking_profile_id',
        'saving_date',
        'estimated_amount',
        'packs_avoided',
        'cigarettes_avoided',
        'is_smoke_free_day',
    ];

    protected function casts(): array
    {
        return [
            'saving_date' => 'date:Y-m-d',
            'estimated_amount' => 'decimal:2',
            'packs_avoided' => 'decimal:2',
            'cigarettes_avoided' => 'integer',
            'is_smoke_free_day' => 'boolean',
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
