<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'theme' => ['sometimes', Rule::in(['dark', 'light', 'system'])],
            'locale' => ['sometimes', Rule::in(['id', 'en'])],
            'timezone' => ['sometimes', 'timezone:all'],
            'notifications_enabled' => ['sometimes', 'boolean'],
            'active_hours_start' => ['sometimes', 'regex:/^\d{2}:\d{2}$/'],
            'active_hours_end' => ['sometimes', 'regex:/^\d{2}:\d{2}$/'],
            'max_daily_notifications' => ['sometimes', 'integer', 'min:0', 'max:10'],
            'notification_cooldown_minutes' => ['sometimes', 'integer', 'min:15', 'max:1440'],
        ];
    }
}
