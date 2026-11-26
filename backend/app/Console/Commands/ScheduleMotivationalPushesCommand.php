<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Console\Command;

class ScheduleMotivationalPushesCommand extends Command
{
    protected $signature = 'regain:schedule-motivational-pushes {--force : Skip random roll and send to all eligible users}';

    protected $description = 'Randomly schedule motivational push notifications respecting active hours, daily cap, and cooldown';

    public function handle(PushNotificationService $pushService): int
    {
        $force = (bool) $this->option('force');
        $queued = 0;

        User::query()
            ->whereHas('appSetting', fn ($q) => $q->where('notifications_enabled', true))
            ->whereHas('pushSubscriptions', fn ($q) => $q->where('is_active', true))
            ->with(['appSetting', 'smokingProfile'])
            ->chunkById(100, function ($users) use ($pushService, $force, &$queued) {
                foreach ($users as $user) {
                    $eligibility = $pushService->evaluateEligibility($user);
                    if (! $eligibility['eligible']) {
                        continue;
                    }

                    // Random trigger (~25% probability per 15-min tick when eligible)
                    if (! $force && random_int(1, 100) > 25) {
                        continue;
                    }

                    $log = $pushService->queueMotivationalNotification($user);
                    if ($log !== null) {
                        $queued++;
                    }
                }
            });

        $this->info("Queued {$queued} motivational push notification(s).");

        return self::SUCCESS;
    }
}
