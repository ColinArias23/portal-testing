<?php

namespace App\Services\Training;

use App\Models\Training;

class TrainingFormatterService
{
    public function format(Training $training, ?int $currentEmployeeId): array
    {
        return [
            'id'              => $training->id,
            'title'           => $training->title,
            'description'     => $training->description,
            'department'      => $training->department,
            'instructor'      => $training->instructor,
            'category'        => $training->category,
            'eventAddress'    => $training->event_address,
            'trainingMode'    => $training->training_mode,
            'startDate'       => $training->start_date?->toISOString(),
            'endDate'         => $training->end_date?->toISOString(),
            'duration'        => $training->duration,
            'status'          => $training->resolveStatus(),
            'progress'        => $training->progress,
            'maxParticipants' => $training->max_participants ?? 0,
            'isEnrolled'      => $currentEmployeeId
                ? $training->participants->contains('id', $currentEmployeeId)
                : false,
            'participants'    => $training->participants->map(fn ($e) => [
                'id' => $e->id,
                'name' => trim(collect([
                    $e->prefix,
                    $e->first_name,
                    $e->middle_name ? $e->middle_name[0] . '.' : null,
                    $e->last_name,
                    $e->suffix ? ', ' . $e->suffix : null,
                ])->filter()->implode(' ')),
                'employeeNumber'    => $e->employee_number,
                'department'        => $e->department,
                'avatar_url'        => $e->getRawOriginal('avatar_url')
                    ? rtrim(config('app.url'), '/') . '/storage/' . $e->getRawOriginal('avatar_url')
                    : null,
                'status'            => $e->pivot->status,
                'certificateIssued' => $e->pivot->certificate_issued,
                'attendedAt'        => $e->pivot->attended_at?->format('Y-m-d\TH:i:s'),
            ])->values()->toArray(),
            'createdAt' => $training->created_at?->toISOString(),
            'updatedAt' => $training->updated_at?->toISOString(),
        ];
    }
}
