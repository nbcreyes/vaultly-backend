<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ProcessPayoutRequest
 *
 * Validates the admin payout processing payload.
 */
class ProcessPayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision'         => ['required', 'string', 'in:paid,rejected'],
            'paypal_payout_id' => ['required_if:decision,paid', 'nullable', 'string', 'max:100'],
            'admin_note'       => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'decision.required'            => 'A decision of paid or rejected is required.',
            'decision.in'                  => 'Decision must be either paid or rejected.',
            'paypal_payout_id.required_if' => 'A PayPal payout transaction ID is required when marking as paid.',
        ];
    }
}