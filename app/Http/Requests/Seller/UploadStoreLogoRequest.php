<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UploadStoreLogoRequest
 *
 * Validates the store logo upload.
 * Accepts JPEG, PNG, and WebP up to 2MB.
 */
class UploadStoreLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'logo' => [
                'required',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:2048', // 2MB in kilobytes
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'logo.required' => 'A logo image is required.',
            'logo.image'    => 'The logo must be an image file.',
            'logo.mimes'    => 'The logo must be a JPEG, PNG, or WebP image.',
            'logo.max'      => 'The logo must not exceed 2MB.',
        ];
    }
}