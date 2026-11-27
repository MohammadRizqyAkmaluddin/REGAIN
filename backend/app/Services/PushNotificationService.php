<?php

namespace App\Services;

use App\Jobs\SendMotivationalPushJob;
use App\Models\NotificationLog;
use App\Models\PushSubscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushNotificationService
{
    public function __construct(
        private readonly SmokingTrackerService $smokingTracker,
    ) {}

    public function templates(): array
    {
        return [
            [
                'key' => 'daily_savings_reminder',
                'title' => 'Setiap Batang Adalah Masa Depanmu',
                'body' => 'Kamu sudah bebas rokok selama :streak hari dengan estimasi hemat Rp:saved. Terus pertahankan!',
            ],
            [
                'key' => 'packs_avoided_milestone',
                'title' => 'Paru-Paru & Dompet Lebih Sehat',
                'body' => 'Sudah :packs bungkus rokok berhasil kamu hindari. Uang itu tetap aman untuk tujuanmu.',
            ],
            [
                'key' => 'financial_freedom',
                'title' => 'Keputusan Kecil, Dampak Besar',
                'body' => 'Setiap rokok yang tidak kamu beli hari ini adalah uang yang kamu simpan untuk masa depan.',
            ],
            [
                'key' => 'mindful_check_in',
                'title' => 'Tetap Fokus pada Targetmu',
                'body' => 'Sudah :cigs batang rokok tidak terbakar. Cek progres target keuanganmu di Deep Pockets.',
            ],
            [
                'key' => 'gentle_encouragement',
                'title' => 'Kamu Sedang Melakukan Hal Hebat',
                'body' => 'Hari ke-:streak tanpa rokok! Estimasi penghematanmu mencapai Rp:saved.',
            ],
        ];
    }

    /**
     * Check whether a user is eligible to receive a motivational notification at $now.
     */
    public function evaluateEligibility(User $user, ?Carbon $now = null): array
    {
        $settings = $user->appSetting;
        if (! $settings || ! $settings->notifications_enabled) {
            return ['eligible' => false, 'reason' => 'notifications_disabled'];
        }

        $tz = $settings->timezone ?: 'Asia/Jakarta';
        $localNow = ($now ?? Carbon::now($tz))->copy()->setTimezone($tz);

        if (! $this->isWithinActiveHours($localNow, $settings->active_hours_start, $settings->active_hours_end)) {
            return ['eligible' => false, 'reason' => 'outside_active_hours'];
        }

        $dayStartUtc = $localNow->copy()->startOfDay()->setTimezone(config('app.timezone'));
        $dayEndUtc = $localNow->copy()->endOfDay()->setTimezone(config('app.timezone'));

        $sentToday = $user->notificationLogs()
            ->whereIn('status', ['queued', 'sent'])
            ->where(function ($q) use ($dayStartUtc, $dayEndUtc) {
                $q->whereBetween('scheduled_for', [$dayStartUtc, $dayEndUtc])
                    ->orWhere(function ($q2) use ($dayStartUtc, $dayEndUtc) {
                        $q2->whereNull('scheduled_for')
                            ->whereBetween('created_at', [$dayStartUtc, $dayEndUtc]);
                    });
            })
            ->count();

        if ($sentToday >= $settings->max_daily_notifications) {
            return ['eligible' => false, 'reason' => 'daily_limit_reached'];
        }

        $lastLog = $user->notificationLogs()
            ->whereIn('status', ['queued', 'sent'])
            ->latest('created_at')
            ->first();

        if ($lastLog) {
            $lastTime = $lastLog->sent_at ?? $lastLog->created_at;
            $minutesSinceLast = $lastTime->diffInMinutes($localNow);
            if ($minutesSinceLast < $settings->notification_cooldown_minutes) {
                return ['eligible' => false, 'reason' => 'cooldown_active'];
            }
        }

        if (! $user->pushSubscriptions()->where('is_active', true)->exists()) {
            return ['eligible' => false, 'reason' => 'no_active_subscription'];
        }

        return ['eligible' => true, 'reason' => 'ok', 'local_now' => $localNow];
    }

    public function isWithinActiveHours(Carbon $localNow, string $start, string $end): bool
    {
        $currentMinutes = ($localNow->hour * 60) + $localNow->minute;
        [$startH, $startM] = array_map('intval', explode(':', $start));
        [$endH, $endM] = array_map('intval', explode(':', $end));

        $startMinutes = ($startH * 60) + $startM;
        $endMinutes = ($endH * 60) + $endM;

        if ($startMinutes <= $endMinutes) {
            return $currentMinutes >= $startMinutes && $currentMinutes <= $endMinutes;
        }

        // Handles overnight windows if ever configured
        return $currentMinutes >= $startMinutes || $currentMinutes <= $endMinutes;
    }

    /**
     * Queue a motivational notification if eligible (or forced for test notifications).
     */
    public function queueMotivationalNotification(User $user, ?Carbon $now = null, bool $force = false): ?NotificationLog
    {
        $tz = $user->appSetting?->timezone ?? 'Asia/Jakarta';
        $localNow = ($now ?? Carbon::now($tz))->copy()->setTimezone($tz);

        if (! $force) {
            $check = $this->evaluateEligibility($user, $localNow);
            if (! $check['eligible']) {
                return null;
            }
        }

        $templates = $this->templates();
        // Avoid repeating the exact same template used last time
        $lastTemplateKey = $user->notificationLogs()->latest('id')->value('template_key');
        $candidates = array_values(array_filter($templates, fn ($t) => $t['key'] !== $lastTemplateKey));
        $chosen = $candidates[array_rand($candidates)] ?? $templates[0];

        $summary = $this->smokingTracker->getSummary($user, $localNow);
        $streak = $summary['progress']['current_streak_days'];
        $saved = number_format((float) $summary['progress']['estimated_money_saved'], 0, ',', '.');
        $packs = number_format((float) $summary['progress']['estimated_packs_avoided'], 1, ',', '.');
        $cigs = number_format((int) $summary['progress']['estimated_cigarettes_avoided'], 0, ',', '.');

        $body = str_replace(
            [':streak', ':saved', ':packs', ':cigs'],
            [(string) $streak, $saved, $packs, $cigs],
            $chosen['body']
        );

        // Dedupe bucket: prevents duplicate notifications in the same 30-minute slot
        $slot = intdiv($localNow->minute, 30);
        $dedupeKey = $force
            ? sprintf('test:%d:%s:%s', $user->id, $localNow->format('YmdHis'), uniqid())
            : sprintf('moti:%d:%s:%02d:%d', $user->id, $localNow->format('Y-m-d'), $localNow->hour, $slot);

        if (NotificationLog::where('dedupe_key', $dedupeKey)->exists()) {
            return null;
        }

        $log = NotificationLog::create([
            'user_id' => $user->id,
            'template_key' => $chosen['key'],
            'title' => $chosen['title'],
            'body' => $body,
            'channel' => 'web_push',
            'status' => 'queued',
            'dedupe_key' => $dedupeKey,
            'scheduled_for' => $localNow,
            'metadata' => [
                'streak_days' => $streak,
                'estimated_money_saved' => $summary['progress']['estimated_money_saved'],
                'url' => '/smoke-free',
            ],
        ]);

        SendMotivationalPushJob::dispatch($log->id);

        return $log;
    }

    /**
     * Actually deliver a NotificationLog via Minishlink Web Push PHP.
     */
    public function deliverLog(NotificationLog $log): void
    {
        $subscriptions = PushSubscription::query()
            ->where('user_id', $log->user_id)
            ->where('is_active', true)
            ->get();

        if ($subscriptions->isEmpty()) {
            $log->update([
                'status' => 'skipped',
                'error_message' => 'Tidak ada langganan push aktif.',
            ]);

            return;
        }

        $publicKey = (string) config('webpush.vapid.public_key');
        $privateKey = (string) config('webpush.vapid.private_key');
        $subject = (string) config('webpush.vapid.subject');

        if ($publicKey === '' || $privateKey === '') {
            $log->update([
                'status' => 'failed',
                'error_message' => 'VAPID keys belum dikonfigurasi pada server.',
            ]);

            return;
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => $subject,
                    'publicKey' => $publicKey,
                    'privateKey' => $privateKey,
                ],
            ]);

            $payload = json_encode([
                'title' => $log->title,
                'body' => $log->body,
                'tag' => $log->dedupe_key,
                'url' => $log->metadata['url'] ?? '/smoke-free',
                'icon' => '/icons/icon-192.png',
                'badge' => '/icons/icon-192.png',
            ], JSON_THROW_ON_ERROR);

            foreach ($subscriptions as $sub) {
                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint' => $sub->endpoint,
                        'publicKey' => $sub->public_key,
                        'authToken' => $sub->auth_token,
                        'contentEncoding' => $sub->content_encoding ?: 'aes128gcm',
                    ]),
                    $payload
                );
            }

            $anySuccess = false;
            $errors = [];

            foreach ($webPush->flush() as $report) {
                $endpoint = $report->getRequest()->getUri()->__toString();
                $endpointHash = hash('sha256', $endpoint);

                if ($report->isSuccess()) {
                    $anySuccess = true;
                    PushSubscription::where('user_id', $log->user_id)
                        ->where('endpoint_hash', $endpointHash)
                        ->update(['last_used_at' => now()]);
                } else {
                    $errors[] = $report->getReason();
                    if ($report->isSubscriptionExpired()) {
                        PushSubscription::where('user_id', $log->user_id)
                            ->where('endpoint_hash', $endpointHash)
                            ->update(['is_active' => false]);
                    }
                }
            }

            $log->update([
                'status' => $anySuccess ? 'sent' : 'failed',
                'sent_at' => $anySuccess ? now() : null,
                'error_message' => $anySuccess ? null : implode('; ', array_unique($errors)),
            ]);
        } catch (\Throwable $e) {
            Log::error('Web Push delivery error', [
                'notification_log_id' => $log->id,
                'error' => $e->getMessage(),
            ]);

            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
