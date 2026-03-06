<?php

namespace App\Http\Requests\Buyer;

use Illuminate\Foundation\Http\FormRequest;

/**
 * CaptureCheckoutOrderRequest
 *
 * Validates the payment capture payload.
 * The frontend sends the PayPal order ID after the buyer approves payment.
 */
class CaptureCheckoutOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'paypal_order_id' => ['required', 'string'],
            'order_id'        => ['required', 'integer', 'exists:orders,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'paypal_order_id.required' => 'A PayPal order ID is required.',
            'order_id.required'        => 'An internal order ID is required.',
            'order_id.exists'          => 'The specified order does not exist.',
        ];
    }
}