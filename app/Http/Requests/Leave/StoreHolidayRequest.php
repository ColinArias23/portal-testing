<?php

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('leave.holidays.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'date' => [
                'required',
                'date',
                Rule::unique('holidays', 'date')->where('name', $this->input('name')),
            ],
            'type' => ['required', Rule::in(['public', 'local', 'company'])],
            'is_recurring' => ['sometimes', 'boolean'],
        ];
    }
}
