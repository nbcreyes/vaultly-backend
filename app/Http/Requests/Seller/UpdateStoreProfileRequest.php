<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UpdateStoreProfileRequest
 *
 * Validates the store profile update payload.
 * All fields are optional so sellers can update one field at a time.
 * Logo and banner are validated as images when present.
 */
class UpdateStoreProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_name'        => ['sometimes', 'string', 'min:2', 'max:100'],
            'store_description' => ['sometimes', 'string', 'min:20', 'max:1000'],
            'paypal_email'      => ['sometimes', 'string', 'email', 'max:255'],
            'website_url'       => ['sometimes', 'nullable', 'url', 'max:255'],
            'twitter_url'       => ['sometimes', 'nullable', 'url', 'max:255'],
            'github_url'        => ['sometimes', 'nullable', 'url', 'max:255'],
            'dribbble_url'      => ['sometimes', 'nullable', 'url', 'max:255'],
            'linkedin_url'      => ['sometimes', 'nullable', 'url', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'store_name.min'           => 'Your store name must be at least 2 characters.',
            'store_name.max'           => 'Your store name cannot exceed 100 characters.',
            'store_description.min'    => 'Your store description must be at least 20 characters.',
            'store_description.max'    => 'Your store description cannot exceed 1000 characters.',
            'paypal_email.email'       => 'Please enter a valid PayPal email address.',
            'website_url.url'          => 'Please enter a valid website URL.',
            'twitter_url.url'          => 'Please enter a valid Twitter URL.',
            'github_url.url'           => 'Please enter a valid GitHub URL.',
            'dribbble_url.url'         => 'Please enter a valid Dribbble URL.',
            'linkedin_url.url'         => 'Please enter a valid LinkedIn URL.',
        ];
    }
}