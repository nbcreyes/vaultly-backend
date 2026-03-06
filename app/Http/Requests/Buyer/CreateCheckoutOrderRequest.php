<?php

namespace App\Http\Requests\Buyer;

use Illuminate\Foundation\Http\FormRequest;

/**
 * CreateCheckoutOrderRequest
 *
 * Validates the checkout initiation payload.
 * The buyer sends an array of product IDs they want to purchase.
 * At this stage we only support purchasing one product at a time,
 * but the architecture accepts an array for future cart support.
 */
class CreateCheckoutOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_ids'   => ['required', 'array', 'min:1', 'max:10'],
            'product_ids.*' => ['required', 'integer', 'exists:products,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_ids.required'   => 'At least one product is required.',
            'product_ids.min'        => 'At least one product is required.',
            'product_ids.max'        => 'You cannot purchase more than 10 products at once.',
            'product_ids.*.exists'   => 'One or more selected products do not exist.',
        ];
    }
}