<?php

namespace App\Repositories\Recruitment\Hiring;

use App\Models\HiringEvent;
use Illuminate\Support\Collection;

class HiringEventRepository
{
    public function allOrderedByStart(): Collection
    {
        return HiringEvent::with(['interviewer', 'applicants'])->orderBy('start_date')->get();
    }

    public function create(array $attributes): HiringEvent
    {
        return HiringEvent::create($attributes);
    }

    public function syncApplicants(HiringEvent $event, array $applicantIds): void
    {
        $event->applicants()->sync($applicantIds);
    }

    public function update(HiringEvent $event, array $attributes): void
    {
        $event->update($attributes);
    }

    public function loadRelations(HiringEvent $event): HiringEvent
    {
        return $event->load(['interviewer', 'applicants']);
    }

    public function delete(HiringEvent $event): void
    {
        $event->delete();
    }
}
