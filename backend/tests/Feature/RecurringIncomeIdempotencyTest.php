<?php

namespace Tests\Feature;

use App\Models\RecurringExecution;
use App\Models\RecurringIncome;
use App\Models\Transaction;
use App\Models\User;
use App\Services\RecurringIncomeService;
use App\Services\UserOnboardingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringIncomeIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_recurring_monthly_salary_executes_once_per_period_idempotently(): void
    {
        $user = User::factory()->create();
        app(UserOnboardingService::class)->initializeUser($user, '1000000.00');

        $account = $user->accounts()->firstOrFail();
        $salaryCat = $user->categories()->where('type', 'income')->firstOrFail();

        $recurring = RecurringIncome::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $salaryCat->id,
            'name' => 'Gaji Bulanan',
            'amount' => '3000000.00',
            'day_of_month' => 25,
            'description' => 'Gaji rutin tanggal 25',
            'start_date' => '2026-11-01',
            'next_run_date' => '2026-11-25',
            'is_active' => true,
        ]);

        $service = app(RecurringIncomeService::class);
        $payday = Carbon::create(2026, 11, 25, 8, 0, 0, 'Asia/Jakarta');

        // First run creates exactly 1 transaction
        $firstRun = $service->processDueIncomes($payday, $user->id);
        $this->assertSame(1, $firstRun);
        $this->assertSame(1, Transaction::where('user_id', $user->id)->where('source', 'recurring')->count());
        $this->assertSame(1, RecurringExecution::where('recurring_income_id', $recurring->id)->count());

        // Second run on the same day creates 0
        $secondRun = $service->processDueIncomes($payday, $user->id);
        $this->assertSame(0, $secondRun);

        // Even if next_run_date were manually reset to 2026-11-25, unique execution_period prevents duplicates
        $recurring->update(['next_run_date' => '2026-11-25']);
        $thirdRun = $service->processDueIncomes($payday, $user->id);
        $this->assertSame(0, $thirdRun);
        $this->assertSame(1, Transaction::where('user_id', $user->id)->where('source', 'recurring')->count());

        // Next month (2026-12-25) executes cleanly
        $decPayday = Carbon::create(2026, 12, 25, 8, 0, 0, 'Asia/Jakarta');
        $fourthRun = $service->processDueIncomes($decPayday, $user->id);
        $this->assertSame(1, $fourthRun);
        $this->assertSame(2, Transaction::where('user_id', $user->id)->where('source', 'recurring')->count());
    }

    public function test_short_month_day_clamping_in_jakarta_timezone(): void
    {
        $service = app(RecurringIncomeService::class);
        $febDate = $service->computeScheduledDateForMonth(2026, 2, 31, 'Asia/Jakarta');

        $this->assertSame('2026-02-28', $febDate->toDateString());
        $this->assertSame('Asia/Jakarta', $febDate->getTimezone()->getName());
    }
}
