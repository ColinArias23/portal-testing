<?php

namespace App\Http\Requests;

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
            'status' => ['required', 'string', Rule::in(Report::STATUSES)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->replace($this->only(['status']));
    }
}