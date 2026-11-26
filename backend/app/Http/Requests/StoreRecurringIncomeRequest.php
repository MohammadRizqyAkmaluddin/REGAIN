<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecurringIncomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'account_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')->where('user_id', $userId),
            ],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('user_id', $userId)->where('type', 'income'),
            ],
            'subcategory_id' => [
                'nullable',
                'integer',
                Rule::exists('subcategories', 'id')->where('user_id', $userId),
            ],
            'name' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'day_of_month' => ['required', 'integer', 'min:1', 'max:31'],
            'description' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
