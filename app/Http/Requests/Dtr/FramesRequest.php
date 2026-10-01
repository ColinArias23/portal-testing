<?php

namespace App\Http\Requests\Dtr;

use Illuminate\Foundation\Http\FormRequest;

abstract class FramesRequest extends FormRequest
{
    public const IMAGE_REGEX = '/^data:image\/(jpeg|png);base64,/';

    public function authorize(): bool
    {
        return true;
    }

    protected function frameRules(string $key, int $min, int $max): array
    {
        return [
            $key         => ['required', 'array', "min:$min", "max:$max"],
            "$key.*"     => ['required', 'string', 'regex:'.self::IMAGE_REGEX, 'max:'.(int) (config('dtr.images.max_bytes') * 1.4)],
        ];
    }
}
