<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function subcategories(): HasMany
    {
        return $this->hasMany(Subcategory::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function recurringIncomes(): HasMany
    {
        return $this->hasMany(RecurringIncome::class);
    }

    public function recurringExecutions(): HasMany
    {
        return $this->hasMany(RecurringExecution::class);
    }

    public function smokingProfile(): HasOne
    {
        return $this->hasOne(SmokingProfile::class);
    }

    public function smokingSavings(): HasMany
    {
        return $this->hasMany(SmokingSaving::class);
    }

    public function smokingEvents(): HasMany
    {
        return $this->hasMany(SmokingEvent::class);
    }

    public function financialGoals(): HasMany
    {
        return $this->hasMany(FinancialGoal::class);
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }

    public function appSetting(): HasOne
    {
        return $this->hasOne(AppSetting::class);
    }
}
