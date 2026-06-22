<?php

namespace App\Http\Requests;

use App\Models\Report;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:150'],

            'description' => ['required', 'string', 'min:20'],

            'type' => ['required', 'string', Rule::in(Report::TYPES)],

            'category' => ['required', 'string', Rule::in(Report::CATEGORIES)],

            'severity' => [
                'nullable',
                'string',
                Rule::in(Report::SEVERITIES),
                Rule::requiredIf(fn () => $this->input('type') === 'bug'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'severity.required' => 'Severity is required when submitting a bug report.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->replace($this->only([
            'subject',
            'description',
            'type',
            'category',
            'severity',
        ]));
    }
}