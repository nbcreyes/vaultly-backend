<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

/**
 * AddProductImagesRequest
 *
 * Validates additional images being added to an existing product.
 * The total image count including existing ones is validated in the controller.
 */
class AddProductImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'images'   => ['required', 'array', 'min:1'],
            'images.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'images.required'  => 'At least one image is required.',
            'images.*.image'   => 'Each file must be an image.',
            'images.*.mimes'   => 'Images must be JPEG, PNG, or WebP.',
            'images.*.max'     => 'Each image cannot exceed 4MB.',
        ];
    }
}