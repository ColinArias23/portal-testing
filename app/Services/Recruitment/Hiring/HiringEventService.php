<?php

namespace App\Services\Recruitment\Hiring;

use App\Models\ActivityLog;
use App\Models\HiringEvent;
use App\Repositories\Recruitment\Hiring\HiringEventRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class HiringEventService
{
    public function __construct(
        private readonly HiringEventRepository $events,
    ) {
    }

    public function format(HiringEvent $event): array
    {
        return [
            'id'          => $event->id,
            'title'       => $event->title,
            'description' => $event->description,
            'location'    => $event->location,
            'color'       => $event->color,
            'all_day'     => $event->all_day,
            'start_date'  => $event->start_date?->toISOString(),
            'end_date'    => $event->end_date?->toISOString(),
            'start_time'  => $event->start_time,
            'interviewer' => $event->interviewer?->only(['id', 'full_name']),
            'applicants'  => $event->applicants->map(fn ($a) => [
                'id'        => $a->id,
                'full_name' => $a->full_name,
                'position'  => $a->position,
            ])->values(),
            'created_at'  => $event->created_at,
        ];
    }

    public function index(): Collection
    {
        return $this->events->allOrderedByStart()->map(fn ($e) => $this->format($e));
    }

    public function store(Request $request, array $data): array
    {
        $event = $this->events->create(collect($data)->except('applicant_ids')->toArray());

        if (! empty($data['applicant_ids'])) {
            $this->events->syncApplicants($event, $data['applicant_ids']);
        }

        $this->events->loadRelations($event);

        ActivityLog::record(
            action:       'hiring_event.created',
            userId:       $request->user()?->id,
            subjectType:  HiringEvent::class,
            subjectId:    $event->id,
            subjectLabel: $event->title,
            ip:           $request->ip(),
            userAgent:    $request->userAgent(),
        );

        return $this->format($event);
    }

    public function update(Request $request, HiringEvent $event, array $data): array
    {
        $this->events->update($event, collect($data)->except('applicant_ids')->toArray());

        if ($request->has('applicant_ids')) {
            $this->events->syncApplicants($event, $data['applicant_ids'] ?? []);
        }

        $this->events->loadRelations($event);

        ActivityLog::record(
            action:       'hiring_event.updated',
            userId:       $request->user()?->id,
            subjectType:  HiringEvent::class,
            subjectId:    $event->id,
            subjectLabel: $event->title,
            ip:           $request->ip(),
            userAgent:    $request->userAgent(),
        );

        return $this->format($event);
    }

    public function destroy(Request $request, HiringEvent $event): void
    {
        $id    = $event->id;
        $label = $event->title;

        $this->events->delete($event);

        ActivityLog::record(
            action:       'hiring_event.deleted',
            userId:       $request->user()?->id,
            subjectType:  HiringEvent::class,
            subjectId:    $id,
            subjectLabel: $label,
            ip:           $request->ip(),
            userAgent:    $request->userAgent(),
        );
    }
}
