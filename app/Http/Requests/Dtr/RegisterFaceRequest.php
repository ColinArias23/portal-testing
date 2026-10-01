<?php

namespace App\Http\Requests\Dtr;

class RegisterFaceRequest extends FramesRequest
{
    public function rules(): array
    {
        return [
            'employee_number' => ['required', 'string', 'max:50'],
        ] + $this->frameRules('images', (int) config('dtr.frames.register_min'), (int) config('dtr.frames.register_max'));
    }
}
