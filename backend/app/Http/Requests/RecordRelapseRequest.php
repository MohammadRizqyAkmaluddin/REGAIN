<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordRelapseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_date' => ['nullable', 'date_format:Y-m-d'],
            'event_time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'packs_smoked' => ['required', 'numeric', 'gt:0', 'max:20'],
            'trigger' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
