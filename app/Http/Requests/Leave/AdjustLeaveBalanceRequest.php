<?php

namespace App\Http\Requests\Leave;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustLeaveBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('leave.balance.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'leave_type_id' => ['required', 'integer', Rule::exists('leave_types', 'id')],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'amount' => ['required', 'numeric', 'not_in:0'],
            'remarks' => ['required', 'string', 'max:500'],
        ];
    }
}
