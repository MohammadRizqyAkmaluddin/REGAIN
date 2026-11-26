<?php

namespace Tests\Feature;

use App\Models\NotificationLog;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\PushNotificationService;
use App\Services\UserOnboardingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationSchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_eligibility_respects_active_hours_cooldown_daily_cap_and_dedupe(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        app(UserOnboardingService::class)->initializeUser($user);

        PushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => 'https://push.example.test/sub-1',
            'endpoint_hash' => hash('sha256', 'https://push.example.test/sub-1'),
            'public_key' => 'test-public-key',
            'auth_token' => 'test-auth-token',
            'content_encoding' => 'aesgcm',
            'is_active' => true,
        ]);

        $service = app(PushNotificationService::class);

        // 06:30 Asia/Jakarta is outside default 08:00-21:00 active hours
        $earlyMorning = Carbon::create(2026, 11, 25, 6, 30, 0, 'Asia/Jakarta');
        $this->assertFalse($service->evaluateEligibility($user, $earlyMorning)['eligible']);
        $this->assertSame('outside_active_hours', $service->evaluateEligibility($user, $earlyMorning)['reason']);

        // 10:00 Asia/Jakarta is eligible
        $morning = Carbon::create(2026, 11, 25, 10, 0, 0, 'Asia/Jakarta');
        $this->assertTrue($service->evaluateEligibility($user, $morning)['eligible']);

        $firstLog = $service->queueMotivationalNotification($user, $morning);
        $this->assertNotNull($firstLog);
        $firstLog->update(['sent_at' => $morning]);

        // 10:30 (only 30 mins later) is blocked by 180-minute cooldown
        $tooSoon = Carbon::create(2026, 11, 25, 10, 30, 0, 'Asia/Jakarta');
        $this->assertSame('cooldown_active', $service->evaluateEligibility($user, $tooSoon)['reason']);

        // After 3 notifications in one day, daily cap blocks further notifications
        NotificationLog::create([
            'user_id' => $user->id,
            'template_key' => 'financial_freedom',
            'title' => 'T2',
            'body' => 'B2',
            'status' => 'sent',
            'dedupe_key' => 'test-cap-2',
            'scheduled_for' => $morning->copy()->addHours(3),
            'sent_at' => $morning->copy()->addHours(3),
        ]);
        NotificationLog::create([
            'user_id' => $user->id,
            'template_key' => 'mindful_check_in',
            'title' => 'T3',
            'body' => 'B3',
            'status' => 'sent',
            'dedupe_key' => 'test-cap-3',
            'scheduled_for' => $morning->copy()->addHours(6),
            'sent_at' => $morning->copy()->addHours(6),
        ]);

        $evening = Carbon::create(2026, 11, 25, 20, 0, 0, 'Asia/Jakarta');
        $this->assertSame('daily_limit_reached', $service->evaluateEligibility($user, $evening)['reason']);

        // Disabling notifications immediately blocks eligibility
        $user->appSetting->update(['notifications_enabled' => false]);
        $user->refresh();
        $this->assertSame('notifications_disabled', $service->evaluateEligibility($user, $morning)['reason']);
    }
}
