<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\FinancialCalculationService;
use App\Services\UserOnboardingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_actual_net_worth_excludes_smoking_savings_and_goal_virtual_allocations(): void
    {
        $user = User::factory()->create();
        app(UserOnboardingService::class)->initializeUser(
            $user,
            '5000000.00',
            Carbon::now('Asia/Jakarta')->subDays(30)->toDateString()
        );

        $account = $user->accounts()->firstOrFail();
        $salaryCat = $user->categories()->where('type', 'income')->firstOrFail();
        $foodCat = $user->categories()->where('type', 'expense')->firstOrFail();

        // Add actual income Rp3,000,000.50
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/transactions', [
            'account_id' => $account->id,
            'type' => 'income',
            'category_id' => $salaryCat->id,
            'amount' => '3000000.50',
            'description' => 'Gaji Bulanan',
            'transaction_date' => Carbon::now('Asia/Jakarta')->toDateString(),
            'transaction_time' => '09:00',
        ])->assertCreated();

        // Add actual expense Rp450,000.25
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/transactions', [
            'account_id' => $account->id,
            'type' => 'expense',
            'category_id' => $foodCat->id,
            'amount' => '450000.25',
            'description' => 'Belanja Mingguan',
            'transaction_date' => Carbon::now('Asia/Jakarta')->toDateString(),
            'transaction_time' => '12:30',
        ])->assertCreated();

        // Create goal and virtually allocate Rp500,000 from smoking savings
        $goalRes = $this->actingAs($user, 'sanctum')->postJson('/api/v1/goals', [
            'name' => 'Dana Darurat',
            'category' => 'emergency_fund',
            'target_amount' => 10000000,
            'allocated_smoking_savings' => 500000,
        ])->assertCreated();

        $goalId = $goalRes->json('data.id');

        // Over-allocating beyond smoking savings must fail with 422
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/goals/{$goalId}/allocate", [
            'allocated_smoking_savings' => 99999999,
        ])->assertStatus(422);

        // Actual Net Worth = 5,000,000.00 + 3,000,000.50 - 450,000.25 = 7,550,000.25
        $summary = app(FinancialCalculationService::class)->getNetWorthSummary($user);
        $this->assertSame('5000000.00', $summary['opening_balance']);
        $this->assertSame('3000000.50', $summary['total_actual_income']);
        $this->assertSame('450000.25', $summary['total_actual_expenses']);
        $this->assertSame('7550000.25', $summary['actual_net_worth']);

        // Dashboard reflects exact same actual net worth separately from smoking savings
        $dashboard = $this->actingAs($user, 'sanctum')->getJson('/api/v1/dashboard')->assertOk();
        $this->assertSame('7550000.25', $dashboard->json('finance.actual_net_worth'));
        $this->assertGreaterThan(0, (float) $dashboard->json('smoking.estimated_money_saved'));
    }
}
