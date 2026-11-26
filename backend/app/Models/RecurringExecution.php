<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringExecution extends Model
{
    protected $fillable = [
        'user_id',
        'recurring_income_id',
        'transaction_id',
        'execution_period',
        'scheduled_date',
        'executed_at',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date:Y-m-d',
            'executed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recurringIncome(): BelongsTo
    {
        return $this->belongsTo(RecurringIncome::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
