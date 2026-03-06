<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RegisterRequest
 *
 * Validates the buyer registration payload.
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Anyone can attempt to register
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'min:2', 'max:100'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'      => 'Your name is required.',
            'name.min'           => 'Your name must be at least 2 characters.',
            'email.required'     => 'An email address is required.',
            'email.email'        => 'Please enter a valid email address.',
            'email.unique'       => 'An account with this email address already exists.',
            'password.required'  => 'A password is required.',
            'password.min'       => 'Your password must be at least 8 characters.',
            'password.confirmed' => 'The password confirmation does not match.',
        ];
    }
}