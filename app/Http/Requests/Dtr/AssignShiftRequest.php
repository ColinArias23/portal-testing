<?php

namespace App\Http\Requests\Dtr;

use Illuminate\Foundation\Http\FormRequest;

class AssignShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_ids'   => ['required', 'array', 'min:1', 'max:500'],
            'employee_ids.*' => ['integer', 'distinct', 'exists:employees,id'],
            'shift_id'       => ['nullable', 'integer', 'exists:dtr_shifts,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_ids.required' => 'Select at least one employee.',
            'employee_ids.max'      => 'You can assign up to 500 employees at a time.',
            'shift_id.exists'       => 'The selected shift does not exist.',
        ];
    }
}
