<?php

namespace App\Http\Requests;

use App\Models\InventoryTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-stock') ?? false;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'physical_count' => ['required', 'integer', 'min:0', 'max:1000000'],
            'reason' => ['required', Rule::in(array_keys(InventoryTransaction::ADJUSTMENT_REASONS))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'physical_count.min' => 'A physical count cannot be negative.',
            'reason.in' => 'Please choose a valid reason.',
        ];
    }
}