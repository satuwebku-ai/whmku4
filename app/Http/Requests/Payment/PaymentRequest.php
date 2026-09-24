<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('client')->check();
    }

    public function rules(): array
    {
        return [
            'payment_gateway_id' => ['required', 'integer', 'exists:payment_gateways,id'],
            'method_code' => ['nullable', 'string', 'max:8'],
        ];
    }
}