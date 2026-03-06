<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

/**
 * CreateProductRequest
 *
 * Validates the product creation payload.
 * The product file and at least one preview image are required on creation.
 */
class CreateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Core fields
            'title'             => ['required', 'string', 'min:5', 'max:150'],
            'short_description' => ['required', 'string', 'min:20', 'max:300'],
            'description'       => ['required', 'string', 'min:50'],
            'category_id'       => ['required', 'integer', 'exists:categories,id'],
            'price'             => ['required', 'numeric', 'min:0.99', 'max:999.99'],
            'license_type'      => ['required', 'string', 'in:personal,commercial'],
            'version'           => ['required', 'string', 'max:20'],
            'changelog'         => ['sometimes', 'nullable', 'string', 'max:2000'],
            'tags'              => ['sometimes', 'array', 'max:10'],
            'tags.*'            => ['string', 'max:50'],

            // Product file — required on creation
            // Allowed types: zip, pdf, mp3, mp4, font files
            'product_file' => [
                'required',
                'file',
                'max:204800', // 200MB in kilobytes
                'mimes:zip,pdf,mp3,mp4,otf,ttf,woff,woff2,7z,rar',
            ],

            // Preview images — at least 1 required, max 5
            'images'   => ['required', 'array', 'min:1', 'max:5'],
            'images.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required'             => 'A product title is required.',
            'title.min'                  => 'The product title must be at least 5 characters.',
            'short_description.required' => 'A short description is required.',
            'short_description.min'      => 'The short description must be at least 20 characters.',
            'short_description.max'      => 'The short description cannot exceed 300 characters.',
            'description.required'       => 'A full description is required.',
            'description.min'            => 'The description must be at least 50 characters.',
            'category_id.required'       => 'A category is required.',
            'category_id.exists'         => 'The selected category does not exist.',
            'price.required'             => 'A price is required.',
            'price.min'                  => 'The minimum price is $0.99.',
            'price.max'                  => 'The maximum price is $999.99.',
            'license_type.required'      => 'A license type is required.',
            'license_type.in'            => 'License type must be personal or commercial.',
            'version.required'           => 'A version number is required.',
            'tags.max'                   => 'You can add up to 10 tags.',
            'tags.*.max'                 => 'Each tag cannot exceed 50 characters.',
            'product_file.required'      => 'A product file is required.',
            'product_file.max'           => 'The product file cannot exceed 200MB.',
            'product_file.mimes'         => 'Allowed file types: ZIP, PDF, MP3, MP4, OTF, TTF, WOFF, WOFF2, 7Z, RAR.',
            'images.required'            => 'At least one preview image is required.',
            'images.min'                 => 'At least one preview image is required.',
            'images.max'                 => 'You can upload up to 5 preview images.',
            'images.*.image'             => 'Each preview must be an image file.',
            'images.*.mimes'             => 'Preview images must be JPEG, PNG, or WebP.',
            'images.*.max'               => 'Each preview image cannot exceed 4MB.',
        ];
    }
}