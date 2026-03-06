<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ProcessRefundRequest
 *
 * Validates the admin refund processing payload.
 */
class ProcessRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision'   => ['required', 'string', 'in:approved,rejected'],
            'admin_note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'decision.required' => 'A decision of approved or rejected is required.',
            'decision.in'       => 'Decision must be either approved or rejected.',
        ];
    }
}