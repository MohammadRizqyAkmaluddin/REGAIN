<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use App\Services\SmokingTrackerService;
use App\Services\UserOnboardingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmokingTrackerTest extends TestCase
{
    use RefreshDatabase;

    public function test_smoking_baseline_and_relapse_preserves_history_without_touching_transactions(): void
    {
        $user = User::factory()->create();
        $quitDate = Carbon::now('Asia/Jakarta')->subDays(14)->toDateString();
        app(UserOnboardingService::class)->initializeUser($user, '0', $quitDate);

        $service = app(SmokingTrackerService::class);
        $summary = $service->getSummary($user);

        // 10 packs/week * Rp30,000 = Rp300,000/week -> Rp15,600,000/year -> Rp1,300,000/month
        $this->assertSame('300000.00', $summary['baseline']['weekly_expenditure']);
        $this->assertSame('1300000.00', $summary['baseline']['monthly_expenditure']);
        $this->assertSame('15600000.00', $summary['baseline']['yearly_expenditure']);
        $this->assertSame(14, $summary['progress']['current_streak_days']);
        $this->assertSame(14, $summary['progress']['total_smoke_free_days']);
        $this->assertSame('600000.00', $summary['progress']['estimated_money_saved']);

        // Record a relapse 2 days ago
        $relapseDate = Carbon::now('Asia/Jakarta')->subDays(2)->toDateString();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/smoking/relapse', [
            'event_date' => $relapseDate,
            'packs_smoked' => 1,
            'trigger' => 'Stres pekerjaan',
            'notes' => 'Lanjut lagi besok.',
        ])->assertCreated();

        $afterRelapse = $service->getSummary($user);

        // Current streak resets to 2 days, but total smoke-free days (13) is preserved!
        $this->assertSame(2, $afterRelapse['progress']['current_streak_days']);
        $this->assertSame(13, $afterRelapse['progress']['total_smoke_free_days']);
        $this->assertSame('570000.00', $afterRelapse['progress']['estimated_money_saved']);

        // Zero financial transactions created by smoking tracker or relapse
        $this->assertSame(0, Transaction::where('user_id', $user->id)->count());
    }
}
