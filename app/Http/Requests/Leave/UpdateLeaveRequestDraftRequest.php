<?php

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeaveRequestDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'leave_type_id' => ['sometimes', 'integer', Rule::exists('leave_types', 'id')->where('is_active', true)],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'is_half_day' => ['sometimes', 'boolean'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
            'details' => ['sometimes', 'array'],
            'documents' => ['sometimes', 'array'],
            'documents.*' => [
                'file',
                'max:' . config('leave.uploads.max_size_kb'),
                'mimes:' . implode(',', config('leave.uploads.allowed_mimes')),
            ],
            'document_keys' => ['sometimes', 'array'],
            'document_keys.*' => ['required_with:documents', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'leave_type_id.exists' => 'Selected leave type is invalid or inactive.',
            'end_date.after_or_equal' => 'End date cannot be before start date.',
        ];
    }
}