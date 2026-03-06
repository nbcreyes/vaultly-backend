<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UpdateProfileRequest
 *
 * Validates a user's profile update payload.
 * All fields are optional — only provided fields are updated.
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.min' => 'Your name must be at least 2 characters.',
            'name.max' => 'Your name cannot exceed 100 characters.',
        ];
    }
}