<?php

namespace App\Http\Controllers\Api\Recruitment\Hiring;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\HiringEvent;
use Illuminate\Http\Request;

class HiringEventController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');

        $this->middleware('permission:hiring.view')->only(['index']);
        $this->middleware('permission:hiring.manage')->only(['store', 'update','destroy']);
    }
// ─── Format ─────────────────────────────────────────────────────
    private function format(HiringEvent $event): array
    {
        return [
            'id'           => $event->id,
            'title'        => $event->title,
            'description'  => $event->description,
            'location'     => $event->location,
            'color'        => $event->color,
            'all_day'      => $event->all_day,
            'start_date'   => $event->start_date?->toISOString(),
            'end_date'     => $event->end_date?->toISOString(),
            'start_time'   => $event->start_time,
            'interviewer'  => $event->interviewer?->only(['id', 'full_name']),
            'applicants'   => $event->applicants->map(fn($a) => [
                'id'        => $a->id,
                'full_name' => $a->full_name,
                'position'  => $a->position,
            ])->values(),
            'created_at'   => $event->created_at,
        ];
    }
// ─── Index ─────────────────────────────────────────────────────
    public function index()
    {
        $events = HiringEvent::with(['interviewer', 'applicants'])
            ->orderBy('start_date')
            ->get()
            ->map(fn($e) => $this->format($e));

        return response()->json($events);
    }
// ─── Store ─────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $data = $request->validate([
            'title'          => ['required', 'string', 'max:100'],
            'description'    => ['nullable', 'string'],
            'location'       => ['nullable', 'string', 'max:150'],
            'color'          => ['nullable', 'string', 'max:30'],
            'all_day'        => ['boolean'],
            'start_date'     => ['required', 'date'],
            'end_date'       => ['nullable', 'date'],
            'start_time'     => ['nullable', 'string', 'max:20'],
            'interviewer_id' => ['nullable', 'exists:employees,id'],
            'applicant_ids'  => ['nullable', 'array'],
            'applicant_ids.*'=> ['exists:applicants,id'],
        ]);

        $event = HiringEvent::create(
            collect($data)->except('applicant_ids')->toArray()
        );

        if (!empty($data['applicant_ids'])) {
            $event->applicants()->sync($data['applicant_ids']);
        }

        $event->load(['interviewer', 'applicants']);

        ActivityLog::record(
            action:       'hiring_event.created',
            userId:       $request->user()?->id,
            subjectType:  HiringEvent::class,
            subjectId:    $event->id,
            subjectLabel: $event->title,
            ip:           $request->ip(),
            userAgent:    $request->userAgent(),
        );

        return response()->json($this->format($event), 201);
    }
// ─── Update ─────────────────────────────────────────────────────
    public function update(Request $request, HiringEvent $hiringEvent)
    {
        $data = $request->validate([
            'title'          => ['sometimes', 'string', 'max:100'],
            'description'    => ['nullable', 'string'],
            'location'       => ['nullable', 'string', 'max:150'],
            'color'          => ['nullable', 'string', 'max:30'],
            'all_day'        => ['boolean'],
            'start_date'     => ['sometimes', 'date'],
            'end_date'       => ['nullable', 'date'],
            'start_time'     => ['nullable', 'string', 'max:20'],
            'interviewer_id' => ['nullable', 'exists:employees,id'],
            'applicant_ids'  => ['nullable', 'array'],
            'applicant_ids.*'=> ['exists:applicants,id'],
        ]);

        $hiringEvent->update(collect($data)->except('applicant_ids')->toArray());

        if ($request->has('applicant_ids')) {
            $hiringEvent->applicants()->sync($data['applicant_ids'] ?? []);
        }

        $hiringEvent->load(['interviewer', 'applicants']);

        ActivityLog::record(
            action:       'hiring_event.updated',
            userId:       $request->user()?->id,
            subjectType:  HiringEvent::class,
            subjectId:    $hiringEvent->id,
            subjectLabel: $hiringEvent->title,
            ip:           $request->ip(),
            userAgent:    $request->userAgent(),
        );

        return response()->json($this->format($hiringEvent));
    }
// ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(Request $request, HiringEvent $hiringEvent)
    {
        $id    = $hiringEvent->id;
        $label = $hiringEvent->title;

        $hiringEvent->delete();

        ActivityLog::record(
            action:       'hiring_event.deleted',
            userId:       $request->user()?->id,
            subjectType:  HiringEvent::class,
            subjectId:    $id,
            subjectLabel: $label,
            ip:           $request->ip(),
            userAgent:    $request->userAgent(),
        );

        return response()->json(['message' => 'Event deleted successfully.']);
    }
}