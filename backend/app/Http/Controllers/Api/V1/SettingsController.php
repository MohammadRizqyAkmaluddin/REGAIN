<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $settings = AppSetting::firstOrCreate(
            ['user_id' => $request->user()->id],
            [
                'theme' => 'dark',
                'locale' => 'id',
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
                'notifications_enabled' => true,
                'active_hours_start' => '08:00',
                'active_hours_end' => '21:00',
                'max_daily_notifications' => 3,
                'notification_cooldown_minutes' => 180,
            ]
        );

        return response()->json(['data' => $settings]);
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $settings = AppSetting::firstOrCreate(['user_id' => $request->user()->id]);
        $settings->update($request->validated());

        return response()->json(['data' => $settings->fresh()]);
    }
}
