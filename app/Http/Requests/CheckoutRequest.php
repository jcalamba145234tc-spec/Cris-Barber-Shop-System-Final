<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'checkout_token' => ['required', 'uuid'],
            'service_id' => ['required', 'exists:services,id'],
            'barber_id' => ['required', 'exists:barbers,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'payment_method' => ['required', 'in:cash,gcash'],
            'cash_received' => ['exclude_unless:payment_method,cash', 'required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/'],
            'change_amount' => ['prohibited'],
            'gcash_reference_no' => ['exclude_unless:payment_method,gcash', 'required', 'regex:/^[0-9]{6,30}$/', Rule::unique('payments', 'gcash_reference_no')],
            // Ignore browser-supplied dates; the workflow timestamps new sales on the server.
            'transaction_date' => ['exclude'],
            'status' => ['required', 'in:queued,completed'],
            'total_amount' => ['prohibited'],
            'barber_share' => ['prohibited'],
            'shop_share' => ['prohibited'],
            'unit_price' => ['prohibited'],
            'amount' => ['prohibited'],
        ];
    }
}
