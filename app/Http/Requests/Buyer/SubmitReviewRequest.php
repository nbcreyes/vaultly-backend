<?php

namespace App\Http\Requests\Buyer;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SubmitReviewRequest
 *
 * Validates a buyer's product review submission.
 */
class SubmitReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'rating'        => ['required', 'integer', 'min:1', 'max:5'],
            'body'          => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_item_id.required' => 'An order item reference is required.',
            'order_item_id.exists'   => 'The referenced order item does not exist.',
            'rating.required'        => 'A rating is required.',
            'rating.min'             => 'Rating must be at least 1 star.',
            'rating.max'             => 'Rating cannot exceed 5 stars.',
            'body.required'          => 'A written review is required.',
            'body.min'               => 'Your review must be at least 10 characters.',
            'body.max'               => 'Your review cannot exceed 2000 characters.',
        ];
    }
}