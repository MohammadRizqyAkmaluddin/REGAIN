<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringIncome extends Model
{
    protected $fillable = [
        'user_id',
        'account_id',
        'category_id',
        'subcategory_id',
        'name',
        'amount',
        'day_of_month',
        'description',
        'start_date',
        'next_run_date',
        'last_executed_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'day_of_month' => 'integer',
            'start_date' => 'date:Y-m-d',
            'next_run_date' => 'date:Y-m-d',
            'last_executed_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function executions(): HasMany
    {
        return $this->hasMany(RecurringExecution::class);
    }
}
