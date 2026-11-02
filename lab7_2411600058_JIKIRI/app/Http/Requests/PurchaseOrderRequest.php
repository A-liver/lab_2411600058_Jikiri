<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('purchase_order');      // the bound model on edit, null on create

        return $order
            ? ($this->user()?->can('update', $order) ?? false)
            : ($this->user()?->can('create', \App\Models\PurchaseOrder::class) ?? false);
    }

    public function rules(): array
    {
        return [
            // The supplier must exist, be active and not be soft-deleted.
            'supplier_id' => ['required', 'integer',
                Rule::exists('suppliers', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'order_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'terms' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'action' => ['nullable', Rule::in(['draft', 'submit'])],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.exists' => 'Please choose an active supplier.',
            'items.required' => 'Add at least one line item.',
            'items.min' => 'Add at least one line item.',
            'items.*.product_id.distinct' => 'A product can only appear once per order.',
            'items.*.product_id.required' => 'Choose a product for every line.',
            'items.*.quantity.min' => 'Each quantity must be at least 1.',
            'expected_delivery_date.after_or_equal' => 'The expected delivery cannot be before the order date.',
        ];
    }

    /** Business rules that need the database, run only after the basic rules pass. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $supplier = Supplier::find($this->input('supplier_id'));
                $items = collect($this->input('items'));
                $productIds = $items->pluck('product_id')->map(fn ($id) => (int) $id);

                // 1. Every product must be in this supplier's catalog.
                $linked = $supplier->products()->whereIn('products.id', $productIds)->pluck('products.id');
                $missing = $productIds->diff($linked);

                if ($missing->isNotEmpty()) {
                    $names = Product::whereIn('id', $missing)->pluck('name')->implode(', ');
                    $validator->errors()->add('items', "{$supplier->name} does not supply: {$names}.");
                    return;
                }

                // 2. Submitting for approval must meet the supplier's minimum order amount.
                if ($this->input('action') === 'submit') {
                    $subtotal = $items->sum(fn ($i) => (int) $i['quantity'] * (float) $i['unit_price']);

                    if ($subtotal < (float) $supplier->minimum_order_amount) {
                        $validator->errors()->add('items',
                            'The subtotal ($' . number_format($subtotal, 2) . ') is below this supplier\'s minimum order of $'
                            . number_format($supplier->minimum_order_amount, 2) . '. Add more items or save it as a draft.');
                    }
                }
            },
        ];
    }
}