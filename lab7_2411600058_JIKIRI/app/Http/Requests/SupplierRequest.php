<?php

namespace App\Http\Requests;

use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-stock') ?? false;
    }

    /** An unchecked checkbox sends nothing, so turn it into a real false. */
    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    public function rules(): array
    {
        $ignoreId = $this->route('supplier')?->id;   // null on create, the supplier on edit

        return [
            // Unique among NON-deleted suppliers (the database has no unique key because of soft deletes).
            'name' => ['required', 'string', 'max:255',
                Rule::unique('suppliers', 'name')->ignore($ignoreId)->whereNull('deleted_at')],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255',
                Rule::unique('suppliers', 'email')->ignore($ignoreId)->whereNull('deleted_at')],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'address' => ['nullable', 'string', 'max:500'],
            'payment_terms' => ['required', Rule::in(Supplier::PAYMENT_TERMS)],
            'lead_time_days' => ['required', 'integer', 'min:0', 'max:365'],
            'minimum_order_amount' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A supplier with this name already exists.',
            'email.unique' => 'Another supplier already uses this email address.',
            'phone.regex' => 'The phone number may only contain digits, spaces, +, -, ( and ).',
            'lead_time_days.max' => 'Lead time cannot be more than 365 days.',
        ];
    }
}