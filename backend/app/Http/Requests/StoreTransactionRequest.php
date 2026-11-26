<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
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
            'type' => ['required', Rule::in(['income', 'expense'])],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where('user_id', $userId),
            ],
            'subcategory_id' => [
                'nullable',
                'integer',
                Rule::exists('subcategories', 'id')->where('user_id', $userId),
            ],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'description' => ['nullable', 'string', 'max:255'],
            'transaction_date' => ['required', 'date_format:Y-m-d'],
            'transaction_time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'client_uuid' => ['nullable', 'uuid'],
        ];
    }
}
