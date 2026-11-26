<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'account_name' => $this->whenLoaded('account', fn () => $this->account?->name),
            'category_id' => $this->category_id,
            'category_name' => $this->whenLoaded('category', fn () => $this->category?->name),
            'category_color' => $this->whenLoaded('category', fn () => $this->category?->color),
            'category_icon' => $this->whenLoaded('category', fn () => $this->category?->icon),
            'subcategory_id' => $this->subcategory_id,
            'subcategory_name' => $this->whenLoaded('subcategory', fn () => $this->subcategory?->name),
            'type' => $this->type,
            'amount' => (string) $this->amount,
            'description' => $this->description,
            'transaction_date' => $this->transaction_date?->format('Y-m-d'),
            'transaction_time' => $this->transaction_time,
            'source' => $this->source,
            'client_uuid' => $this->client_uuid,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
