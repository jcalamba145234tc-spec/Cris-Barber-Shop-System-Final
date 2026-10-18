<?php

namespace App\Http\Requests;

use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        $token = $this->input('checkout_token');
        $original = is_string($token) && Str::isUuid($token)
            ? Transaction::withTrashed()->where('checkout_token', $token)->first() : null;
        $referenceRule = Rule::unique('payments', 'gcash_reference_no');
        if ($original?->payment && $original->payment->gcash_reference_no === $this->input('gcash_reference_no')) {
            $referenceRule->ignore($original->payment->id);
        }

        return [
            'checkout_token' => ['required', 'uuid'],
            'service_id' => ['required_without:service_ids', 'exclude_with:service_ids', 'exists:services,id'],
            'service_ids' => ['required_without:service_id', 'array', 'min:1', 'max:50'],
            'service_ids.*' => ['required', 'uuid', 'distinct', 'exists:services,id'],
            'barber_id' => ['required', 'exists:barbers,id'],
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'quantity' => ['required_without:service_quantities', 'integer', 'min:1', 'max:100'],
            'service_quantities' => ['required_without:quantity', 'array', 'min:1', 'max:50'],
            'service_quantities.*' => ['required', 'integer', 'min:1', 'max:100'],
            'payment_method' => ['required', 'in:cash,gcash'],
            'cash_received' => ['exclude_unless:payment_method,cash', 'required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/'],
            'change_amount' => ['prohibited'],
            'gcash_reference_no' => ['exclude_unless:payment_method,gcash', 'required', 'regex:/^[0-9]{6,30}$/', $referenceRule],
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
