<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSmokingProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quit_date' => ['required', 'date_format:Y-m-d'],
            'packs_per_week' => ['required', 'numeric', 'gt:0', 'max:200'],
            'price_per_pack' => ['required', 'numeric', 'gt:0', 'max:10000000'],
            'cigarettes_per_pack' => ['required', 'integer', 'min:1', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
