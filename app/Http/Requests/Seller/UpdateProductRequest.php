<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UpdateProductRequest
 *
 * Validates the product update payload.
 * All fields are optional. Only provided fields are updated.
 * A new product file can optionally replace the existing one.
 * Images are managed separately via dedicated endpoints.
 */
class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'             => ['sometimes', 'string', 'min:5', 'max:150'],
            'short_description' => ['sometimes', 'string', 'min:20', 'max:300'],
            'description'       => ['sometimes', 'string', 'min:50'],
            'category_id'       => ['sometimes', 'integer', 'exists:categories,id'],
            'price'             => ['sometimes', 'numeric', 'min:0.99', 'max:999.99'],
            'license_type'      => ['sometimes', 'string', 'in:personal,commercial'],
            'version'           => ['sometimes', 'string', 'max:20'],
            'changelog'         => ['sometimes', 'nullable', 'string', 'max:2000'],
            'tags'              => ['sometimes', 'array', 'max:10'],
            'tags.*'            => ['string', 'max:50'],

            // Optional file replacement
            'product_file' => [
                'sometimes',
                'file',
                'max:204800',
                'mimes:zip,pdf,mp3,mp4,otf,ttf,woff,woff2,7z,rar',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'title.min'                  => 'The product title must be at least 5 characters.',
            'short_description.min'      => 'The short description must be at least 20 characters.',
            'short_description.max'      => 'The short description cannot exceed 300 characters.',
            'description.min'            => 'The description must be at least 50 characters.',
            'category_id.exists'         => 'The selected category does not exist.',
            'price.min'                  => 'The minimum price is $0.99.',
            'price.max'                  => 'The maximum price is $999.99.',
            'license_type.in'            => 'License type must be personal or commercial.',
            'tags.max'                   => 'You can add up to 10 tags.',
            'tags.*.max'                 => 'Each tag cannot exceed 50 characters.',
            'product_file.max'           => 'The product file cannot exceed 200MB.',
            'product_file.mimes'         => 'Allowed file types: ZIP, PDF, MP3, MP4, OTF, TTF, WOFF, WOFF2, 7Z, RAR.',
        ];
    }
}