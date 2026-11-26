<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Services\PushNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendMotivationalPushJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly int $notificationLogId
    ) {}

    public function handle(PushNotificationService $pushService): void
    {
        $log = NotificationLog::find($this->notificationLogId);
        if (! $log || $log->status !== 'queued') {
            return;
        }

        $pushService->deliverLog($log);
    }
}
