<?php

namespace App\Http\Requests\Leave;

use App\Models\LeaveType;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $rules = [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'leave_type_id' => ['required', 'integer', Rule::exists('leave_types', 'id')->where('is_active', true)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_half_day' => ['sometimes', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
            'details' => ['sometimes', 'array'],
            'documents' => ['sometimes', 'array'],
            'documents.*' => [
                'file',
                'max:' . config('leave.uploads.max_size_kb'),
                'mimes:' . implode(',', config('leave.uploads.allowed_mimes')),
            ],
            'document_keys' => ['sometimes', 'array'],
            'document_keys.*' => ['required_with:documents', 'string'],
        ];

        // Merge per-leave-type field rules, namespaced under details.*
        $leaveTypeCode = $this->resolveLeaveTypeCode();
        $typeConfig = $leaveTypeCode ? config("leave.types.{$leaveTypeCode}") : null;

        foreach ($typeConfig['fields'] ?? [] as $field => $rule) {
            $rules["details.{$field}"] = explode('|', $rule);
        }

        return $rules;
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function ($validator) {
            $leaveTypeId = $this->input('leave_type_id');
            $employeeId = $this->input('employee_id');

            if ($leaveTypeId && $employeeId) {
                $leaveType = LeaveType::find($leaveTypeId);
                $employee = \App\Models\Employee::find($employeeId);

                if ($leaveType && $employee && ! $leaveType->isEligibleFor($employee->employment_type)) {
                    $validator->errors()->add(
                        'leave_type_id',
                        "{$leaveType->name} is not available for {$employee->employment_type} employees."
                    );
                }
            }

            $leaveTypeCode = $this->resolveLeaveTypeCode();
            if (! $leaveTypeCode) {
                return;
            }

            $typeConfig = config("leave.types.{$leaveTypeCode}", []);
            $uploadedKeys = collect($this->input('document_keys', []));

            // Named required documents (one file each).
            foreach ($typeConfig['required_documents'] ?? [] as $key => $required) {
                if ($required && ! $uploadedKeys->contains($key)) {
                    $validator->errors()->add("documents.{$key}", "The {$key} document is required for this leave type.");
                }
            }

            // "Any of" requirement (e.g. VAWC: at least one supporting document).
            if (! empty($typeConfig['required_documents_any_of'])) {
                $hasAny = $uploadedKeys->intersect($typeConfig['required_documents_any_of'])->isNotEmpty();
                if (! $hasAny) {
                    $validator->errors()->add('documents', 'At least one supporting document is required for this leave type.');
                }
            }

            // Sick leave: medical certificate becomes mandatory if filed in advance or > 5 days.
            if ($leaveTypeCode === 'sick' && $this->filled(['start_date', 'end_date'])) {
                $start = \Carbon\Carbon::parse($this->input('start_date'));
                $end = \Carbon\Carbon::parse($this->input('end_date'));
                $days = $start->diffInDays($end) + 1;
                $filedInAdvance = $start->isAfter(now());

                if (($days > 5 || $filedInAdvance) && ! $uploadedKeys->contains('medical_certificate')) {
                    $validator->errors()->add(
                        'documents.medical_certificate',
                        'A medical certificate is required when filing in advance or for more than 5 days.'
                    );
                }
            }
        });
    }

    private function resolveLeaveTypeCode(): ?string
    {
        $id = $this->input('leave_type_id');
        if (! $id) {
            return null;
        }

        return LeaveType::query()->whereKey($id)->value('code');
    }

    public function messages(): array
    {
        return [
            'employee_id.exists' => 'Selected employee was not found.',
            'leave_type_id.exists' => 'Selected leave type is invalid or inactive.',
            'end_date.after_or_equal' => 'End date cannot be before start date.',
        ];
    }
}