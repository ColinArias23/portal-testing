<?php

namespace App\Http\Requests\Dtr;

use App\Models\Dtr\DtrAttendance;
use App\Services\Dtr\EmployeeResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $times = [];

        foreach (['time_in', 'time_out'] as $field) {
            $value = $this->input($field);

            if (is_string($value) && preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
                $times[$field] = substr($value, 0, 5);
            }
        }

        $this->merge($times);

        $existing = $this->existing();

        if ($existing) {
            if ((int) $this->input('employee_id') === $existing->employee_id) {
                $this->getInputSource()->remove('employee_id');
            }

            return;
        }

        $number = $this->input('employee_number');

        if (is_string($number) && ! $this->filled('employee_id')) {
            $employee = app(EmployeeResolver::class)->byNumber($number);

            if ($employee) {
                $this->merge(['employee_id' => $employee->id]);
            }
        }
    }

    public function rules(): array
    {
        $tz    = (string) config('dtr.timezone');
        $today = CarbonImmutable::now($tz)->toDateString();
        $maxKb = (int) ceil(config('dtr.images.max_bytes') / 1024);
        $photo = ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:'.$maxKb];

        return [
            'employee_id'    => $this->existing() ? ['prohibited'] : ['required', 'integer', 'exists:employees,id'],
            'work_date'      => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$today],
            'time_in'        => ['required', 'date_format:H:i'],
            'time_out'       => ['nullable', 'date_format:H:i'],
            'time_out_date'  => ['nullable', 'date_format:Y-m-d'],
            'time_in_image'  => $photo,
            'time_out_image' => $photo,
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required'         => 'Enter a valid employee number.',
            'employee_id.exists'           => 'No employee found with that employee number.',
            'employee_id.prohibited'       => 'The employee of an existing record cannot be changed.',
            'work_date.required'           => 'Date is required.',
            'work_date.before_or_equal'    => 'Date cannot be in the future.',
            'time_in.required'             => 'Time in is required.',
            'time_in.date_format'          => 'Time in must use the HH:MM format.',
            'time_out.date_format'         => 'Time out must use the HH:MM format.',
            'time_in_image.mimes'          => 'Time in photo must be a JPG or PNG image.',
            'time_in_image.max'            => 'Time in photo is too large.',
            'time_out_image.mimes'         => 'Time out photo must be a JPG or PNG image.',
            'time_out_image.max'           => 'Time out photo is too large.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $this->validateTimeOut($v);
        });
    }

    private function validateTimeOut(Validator $v): void
    {
        $tz      = (string) config('dtr.timezone');
        $data    = $v->getData();
        $timeOut = $data['time_out'] ?? null;
        $existing = $this->existing();

        if (! $timeOut) {
            if (! empty($data['time_out_image'])) {
                $v->errors()->add('time_out', 'Time out is required when a time out photo is attached.');
            }
            if ($existing?->time_out) {
                $v->errors()->add('time_out', 'Time out cannot be removed once it has been recorded.');
            }

            return;
        }

        $workDate = CarbonImmutable::parse($data['work_date'], $tz);
        $outDate  = CarbonImmutable::parse($data['time_out_date'] ?? $data['work_date'], $tz);

        if (! $outDate->isSameDay($workDate) && ! $outDate->isSameDay($workDate->addDay())) {
            $v->errors()->add('time_out_date', 'Time out must fall on the same day or the next day.');

            return;
        }

        $timeIn  = CarbonImmutable::parse($workDate->toDateString().' '.$data['time_in'], $tz);
        $timeOut = CarbonImmutable::parse($outDate->toDateString().' '.$timeOut, $tz);

        if ($timeOut->lessThanOrEqualTo($timeIn)) {
            $v->errors()->add('time_out', 'Time out must be later than time in.');

            return;
        }

        $maxHours = (int) config('dtr.punch.max_shift_hours');
        if ($timeOut->diffInSeconds($timeIn, true) > $maxHours * 3600) {
            $v->errors()->add('time_out', "A shift cannot be longer than {$maxHours} hours.");
        }
    }

    private function existing(): ?DtrAttendance
    {
        $record = $this->route('attendance');

        return $record instanceof DtrAttendance ? $record : null;
    }
}