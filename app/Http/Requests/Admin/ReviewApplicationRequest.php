<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ReviewApplicationRequest
 *
 * Validates the admin decision payload when approving or rejecting
 * a seller application.
 */
class ReviewApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision'         => ['required', 'string', 'in:approved,rejected'],
            'rejection_reason' => ['required_if:decision,rejected', 'nullable', 'string', 'min:10', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'decision.required'              => 'A decision of approved or rejected is required.',
            'decision.in'                    => 'Decision must be either approved or rejected.',
            'rejection_reason.required_if'   => 'A rejection reason is required when rejecting an application.',
            'rejection_reason.min'           => 'The rejection reason must be at least 10 characters.',
        ];
    }
}