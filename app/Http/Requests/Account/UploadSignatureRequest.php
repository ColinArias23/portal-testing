<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class UploadSignatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $mimes = implode(',', config('account.signatures.allowed_mimes'));
        $maxKb = config('account.signatures.max_size_kb');

        return [
            'signature' => [
                'required',
                'file',
                "mimes:{$mimes}",
                "max:{$maxKb}",
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'signature.mimes' => 'The signature must be a PNG image.',
            'signature.max' => 'The signature must not be larger than 2 MB.',
        ];
    }
}
