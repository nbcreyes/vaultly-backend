<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RequestPayoutRequest
 *
 * Validates a seller's payout request.
 * The amount must not exceed the seller's available balance.
 * That check is done in the controller after validation.
 */
class RequestPayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:10.00'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'A payout amount is required.',
            'amount.numeric'  => 'The payout amount must be a number.',
            'amount.min'      => 'The minimum payout amount is $10.00.',
        ];
    }
}