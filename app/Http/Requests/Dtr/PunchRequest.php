<?php

namespace App\Http\Requests\Dtr;

class PunchRequest extends FramesRequest
{
    public function rules(): array
    {
        return [
            'ticket' => ['required', 'string', 'max:2000'],
            'image'  => ['nullable', 'string', 'regex:'.self::IMAGE_REGEX, 'max:'.(int) (config('dtr.images.max_bytes') * 1.4)],
        ];
    }
}
