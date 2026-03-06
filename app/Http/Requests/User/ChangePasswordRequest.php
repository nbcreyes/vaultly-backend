<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ChangePasswordRequest
 *
 * Validates a password change request.
 * Current password must be correct before the new one is accepted.
 */
class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'new_password'     => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Your current password is required.',
            'new_password.required'     => 'A new password is required.',
            'new_password.min'          => 'Your new password must be at least 8 characters.',
            'new_password.confirmed'    => 'The new password confirmation does not match.',
        ];
    }
}