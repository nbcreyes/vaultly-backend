<?php

namespace App\Http\Requests\Buyer;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RequestRefundRequest
 *
 * Validates a buyer's refund request.
 */
class RequestRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'reason'        => ['required', 'string', 'min:20', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_item_id.required' => 'An order item reference is required.',
            'order_item_id.exists'   => 'The referenced order item does not exist.',
            'reason.required'        => 'A reason for the refund is required.',
            'reason.min'             => 'Please provide at least 20 characters explaining your reason.',
            'reason.max'             => 'Reason cannot exceed 1000 characters.',
        ];
    }
}