<?php

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('leave.holidays.manage') ?? false;
    }

    public function rules(): array
    {
        $holiday = $this->route('holiday');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'date' => [
                'sometimes',
                'required',
                'date',
                Rule::unique('holidays', 'date')
                    ->where('name', $this->input('name'))
                    ->ignore($holiday?->id),
            ],
            'type' => ['sometimes', 'required', Rule::in(['public', 'local', 'company'])],
            'is_recurring' => ['sometimes', 'boolean'],
        ];
    }
}
