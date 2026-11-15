<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'quantity' => $this->quantity,
            'reorder_level' => $this->reorder_level,
            'status' => $this->stock_status,
            'status_label' => $this->stock_status_label,
            'url' => route('products.show', $this->id, false),
        ];
    }
}