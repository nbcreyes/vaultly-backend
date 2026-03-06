<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UploadStoreBannerRequest
 *
 * Validates the store banner upload.
 * Accepts JPEG, PNG, and WebP up to 4MB.
 */
class UploadStoreBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'banner' => [
                'required',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:4096', // 4MB in kilobytes
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'banner.required' => 'A banner image is required.',
            'banner.image'    => 'The banner must be an image file.',
            'banner.mimes'    => 'The banner must be a JPEG, PNG, or WebP image.',
            'banner.max'      => 'The banner must not exceed 4MB.',
        ];
    }
}