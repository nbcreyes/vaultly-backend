<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SendMessageRequest
 *
 * Validates an outgoing message payload.
 */
class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'Message body is required.',
            'body.max'      => 'Messages cannot exceed 2000 characters.',
        ];
    }
}