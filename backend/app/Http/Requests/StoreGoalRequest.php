<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
            'target_amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'current_actual_allocation' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'allocated_smoking_savings' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'target_date' => ['nullable', 'date_format:Y-m-d'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:20'],
            'status' => ['sometimes', Rule::in(['active', 'completed', 'paused'])],
        ];
    }
}
