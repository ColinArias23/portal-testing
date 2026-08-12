<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'contact_number' => [
                'required',
                'string',
                'regex:' . config('account.profile.mobile_no_regex'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'contact_number.regex' => 'Enter a valid contact number.',
        ];
    }
}
