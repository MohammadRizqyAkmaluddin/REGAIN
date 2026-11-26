<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialGoal extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'category',
        'description',
        'target_amount',
        'current_actual_allocation',
        'allocated_smoking_savings',
        'target_date',
        'icon',
        'color',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:2',
            'current_actual_allocation' => 'decimal:2',
            'allocated_smoking_savings' => 'decimal:2',
            'target_date' => 'date:Y-m-d',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
