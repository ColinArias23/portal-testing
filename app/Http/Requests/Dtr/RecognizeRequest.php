<?php

namespace App\Http\Requests\Dtr;

class RecognizeRequest extends FramesRequest
{
    public function rules(): array
    {
        return $this->frameRules('images', (int) config('dtr.frames.recognize_min'), (int) config('dtr.frames.recognize_max'));
    }
}
