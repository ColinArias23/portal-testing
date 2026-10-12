<?php

namespace App\Http\Requests\Dtr;

use App\Models\DtrShift;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $times = [];

        foreach (['start_time', 'end_time'] as $field) {
            $value = $this->input($field);

            if (is_string($value) && preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
                $times[$field] = substr($value, 0, 5);
            }
        }

        $this->merge($times);
    }

    public function rules(): array
    {
        $shift = $this->route('shift');

        return [
            'name'          => [
                'required', 'string', 'max:60',
                Rule::unique('dtr_shifts', 'name')->ignore($shift instanceof DtrShift ? $shift->id : null),
            ],
            'start_time'    => ['required', 'date_format:H:i'],
            'end_time'      => ['required', 'date_format:H:i', 'different:start_time'],
            'grace_minutes' => ['nullable', 'integer', 'between:0,120'],
            'is_default'    => ['nullable', 'boolean'],
            'is_active'     => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'       => 'Shift name is required.',
            'name.unique'         => 'A shift with that name already exists.',
            'start_time.required' => 'Start time is required.',
            'end_time.required'   => 'End time is required.',
            'end_time.different'  => 'End time must be different from start time.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($this->boolean('is_default') && $this->has('is_active') && ! $this->boolean('is_active')) {
                $v->errors()->add('is_default', 'The default shift must be active.');
            }
        });
    }
}
