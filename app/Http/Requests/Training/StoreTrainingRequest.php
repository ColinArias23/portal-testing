<?php

namespace App\Http\Requests\Training;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrainingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'            => 'required|string|max:255',
            'description'      => 'nullable|string',
            'department'       => 'nullable|string|max:255',
            'instructor'       => 'nullable|string|max:255',
            'category'         => 'nullable|string|max:255',
            'event_address'    => 'nullable|string|max:500',
            'training_mode'    => 'nullable|in:online,face-to-face',
            'start_date'       => 'nullable|date',
            'end_date'         => 'nullable|date|after_or_equal:start_date',
            'duration'         => 'nullable|string|max:50',
            'status'           => 'nullable|in:active,upcoming,ongoing,finished,cancelled,draft',
            'progress'         => 'nullable|integer|min:0|max:100',
            'max_participants' => 'nullable|integer|min:0',
        ];
    }
}