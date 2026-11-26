<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SmokingTrackerService;
use Illuminate\Console\Command;

class SnapshotSmokingSavingsCommand extends Command
{
    protected $signature = 'regain:snapshot-smoking-savings';

    protected $description = 'Record daily estimated smoking savings snapshots for active users';

    public function handle(SmokingTrackerService $service): int
    {
        $count = 0;

        User::query()
            ->whereHas('smokingProfile', fn ($q) => $q->where('is_active', true))
            ->with(['smokingProfile', 'appSetting'])
            ->chunkById(100, function ($users) use ($service, &$count) {
                foreach ($users as $user) {
                    if ($service->snapshotDailySavings($user) !== null) {
                        $count++;
                    }
                }
            });

        $this->info("Recorded {$count} daily smoking savings snapshot(s).");

        return self::SUCCESS;
    }
}
