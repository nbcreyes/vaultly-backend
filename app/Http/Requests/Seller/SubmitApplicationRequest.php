<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SubmitApplicationRequest
 *
 * Validates the seller application submission payload.
 */
class SubmitApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name'         => ['required', 'string', 'min:2', 'max:100'],
            'store_name'        => ['required', 'string', 'min:2', 'max:100'],
            'store_description' => ['required', 'string', 'min:20', 'max:1000'],
            'category_focus'    => ['required', 'string', 'max:100'],
            'paypal_email'      => ['required', 'string', 'email', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required'         => 'Your full name is required.',
            'full_name.min'              => 'Your full name must be at least 2 characters.',
            'store_name.required'        => 'A store name is required.',
            'store_name.min'             => 'Your store name must be at least 2 characters.',
            'store_description.required' => 'A store description is required.',
            'store_description.min'      => 'Your store description must be at least 20 characters.',
            'category_focus.required'    => 'Please specify your primary product category.',
            'paypal_email.required'      => 'A PayPal email address is required for payouts.',
            'paypal_email.email'         => 'Please enter a valid PayPal email address.',
        ];
    }
}