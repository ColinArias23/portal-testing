<?php

namespace App\Http\Requests\AiChat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:1', 'max:4000'],

            'conversation_history' => ['sometimes', 'array', 'max:20'],
            'conversation_history.*.role' => [
                'required_with:conversation_history',
                Rule::in(['user', 'assistant']), 
            ],
            'conversation_history.*.content' => ['required_with:conversation_history', 'string', 'max:4000'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'Please enter a message.',
            'message.max' => 'Your message is too long (max 4000 characters).',
            'conversation_history.max' => 'This conversation is too long — please start a new chat.',
            'conversation_history.*.role.in' => 'Invalid conversation role.',
        ];
    }
}
