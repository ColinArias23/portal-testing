<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use App\Models\Report;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            'subject' => ['sometimes', 'string', 'max:150'],

            'description' => ['sometimes', 'string', 'min:20'],

            'type' => ['sometimes', 'string', Rule::in(Report::TYPES)],

            'category' => ['sometimes', 'string', Rule::in(Report::CATEGORIES)],

            'severity' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in(Report::SEVERITIES),
            ],

            'status' => ['sometimes', 'string', Rule::in(Report::STATUSES)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->request->remove('resolved_at');

        $this->replace($this->only([
            'subject',
            'description',
            'type',
            'category',
            'severity',
            'status',
        ]));
    }
}