<?php

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActOnApprovalStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['approved', 'rejected'])],
            'remarks' => [
                Rule::requiredIf(fn () => $this->input('action') === 'rejected'),
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'remarks.required_if' => 'Remarks are required when rejecting a leave request.',
        ];
    }
}
