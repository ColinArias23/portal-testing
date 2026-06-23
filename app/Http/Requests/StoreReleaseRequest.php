<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Contracts\Validation\ValidationRule;

class StoreReleaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'version' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9.\-+]+$/',
                'unique:releases,version',
            ],
            'title' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'report_ids' => ['required', 'array', 'min:1', 'max:200'],
            'report_ids.*' => ['integer', 'distinct', 'exists:reports,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'version.regex' => 'Version may only contain letters, numbers, dots, hyphens and plus signs.',
            'version.unique' => 'A release with this version already exists.',
            'report_ids.required' => 'At least one report must be selected for the release.',
            'report_ids.*.exists' => 'One or more selected reports do not exist.',
            'report_ids.*.distinct' => 'Duplicate report IDs are not allowed.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $reportIds = $this->input('report_ids', []);

            if (! is_array($reportIds) || empty($reportIds)) {
                return;
            }

            $invalidIds = \App\Models\Report::query()
                ->whereIn('id', $reportIds)
                ->where(function ($query) {
                    $query->where('status', '!=', 'resolved')
                        ->orWhereHas('releases');
                })
                ->pluck('id');

            if ($invalidIds->isNotEmpty()) {
                $validator->errors()->add(
                    'report_ids',
                    'The following report IDs are not eligible (must be resolved and unassigned): '
                        . $invalidIds->implode(', ')
                );
            }
        });
    }
}