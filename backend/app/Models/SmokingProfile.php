<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmokingProfile extends Model
{
    protected $fillable = [
        'user_id',
        'quit_date',
        'packs_per_week',
        'price_per_pack',
        'cigarettes_per_pack',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'quit_date' => 'date:Y-m-d',
            'packs_per_week' => 'decimal:2',
            'price_per_pack' => 'decimal:2',
            'cigarettes_per_pack' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function savings(): HasMany
    {
        return $this->hasMany(SmokingSaving::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SmokingEvent::class);
    }
}
