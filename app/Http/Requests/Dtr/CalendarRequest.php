<?php

namespace App\Http\Requests\Dtr;

use Illuminate\Foundation\Http\FormRequest;

class CalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year'  => ['nullable', 'integer', 'between:2000,2100'],
        ];
    }

    public function month(): int
    {
        return (int) ($this->validated('month') ?? now(config('dtr.timezone'))->month);
    }

    public function year(): int
    {
        return (int) ($this->validated('year') ?? now(config('dtr.timezone'))->year);
    }
}
