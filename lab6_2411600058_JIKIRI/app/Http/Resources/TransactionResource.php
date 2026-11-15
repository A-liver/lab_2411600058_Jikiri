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
            'date' => $this->created_at->format('Y-m-d H:i'),
            'type' => $this->type,
            'type_label' => $this->type_label,
            'type_color' => $this->type_color,
            'change' => $this->delta(),
            'balance_after' => $this->balance_after,
            'product' => ['id' => $this->product->id, 'name' => $this->product->name],
            'user' => $this->user?->name,
            'url' => route('transactions.show', $this->id, false),
        ];
    }
}