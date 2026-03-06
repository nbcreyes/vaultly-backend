<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UploadAvatarRequest
 *
 * Validates an avatar image upload.
 */
class UploadAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'avatar' => [
                'required',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:2048',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'avatar.required' => 'An avatar image is required.',
            'avatar.image'    => 'The avatar must be an image file.',
            'avatar.mimes'    => 'The avatar must be a JPEG, PNG, or WebP image.',
            'avatar.max'      => 'The avatar must not exceed 2MB.',
        ];
    }
}