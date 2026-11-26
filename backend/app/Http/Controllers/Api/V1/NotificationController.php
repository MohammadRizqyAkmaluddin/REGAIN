<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private readonly PushNotificationService $pushService,
    ) {}

    public function vapidPublicKey(): JsonResponse
    {
        return response()->json([
            'public_key' => config('webpush.vapid.public_key', ''),
            'configured' => ! empty(config('webpush.vapid.public_key')),
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:2000'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'content_encoding' => ['nullable', 'string', 'max:30'],
        ]);

        $endpointHash = hash('sha256', $data['endpoint']);

        $subscription = PushSubscription::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'endpoint_hash' => $endpointHash,
            ],
            [
                'endpoint' => $data['endpoint'],
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['content_encoding'] ?? 'aes128gcm',
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'data' => $subscription->only(['id', 'is_active', 'created_at']),
            'message' => 'Notifikasi push berhasil diaktifkan pada perangkat ini.',
        ], 201);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string'],
        ]);

        $endpointHash = hash('sha256', $data['endpoint']);

        $request->user()
            ->pushSubscriptions()
            ->where('endpoint_hash', $endpointHash)
            ->update(['is_active' => false]);

        return response()->json(['message' => 'Langganan notifikasi dinonaktifkan.']);
    }

    public function logs(Request $request): JsonResponse
    {
        $logs = $request->user()
            ->notificationLogs()
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        $activeSubscriptions = $request->user()
            ->pushSubscriptions()
            ->where('is_active', true)
            ->count();

        return response()->json([
            'data' => $logs,
            'active_subscriptions_count' => $activeSubscriptions,
        ]);
    }

    public function sendTest(Request $request): JsonResponse
    {
        $log = $this->pushService->queueMotivationalNotification($request->user(), null, true);

        return response()->json([
            'data' => $log?->fresh(),
            'message' => 'Notifikasi uji coba telah dikirim ke antrean.',
        ]);
    }
}
