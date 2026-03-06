<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ReplyToReviewRequest
 *
 * Validates a seller's reply to a buyer review.
 */
class ReplyToReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reply' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reply.required' => 'A reply is required.',
            'reply.min'      => 'Your reply must be at least 10 characters.',
            'reply.max'      => 'Your reply cannot exceed 1000 characters.',
        ];
    }
}