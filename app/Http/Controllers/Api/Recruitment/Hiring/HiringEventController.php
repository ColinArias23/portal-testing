<?php

namespace App\Http\Controllers\Api\Recruitment\Hiring;

use App\Http\Controllers\Controller;
use App\Models\HiringEvent;
use App\Services\Recruitment\Hiring\HiringEventService;
use Illuminate\Http\Request;

class HiringEventController extends Controller
{
    public function __construct(
        private readonly HiringEventService $events,
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:hiring.view')->only(['index']);
        $this->middleware('permission:hiring.manage')->only(['store', 'update', 'destroy']);
    }

    // ─── Index ─────────────────────────────────────────────────────
    public function index()
    {
        return response()->json($this->events->index());
    }

    // ─── Store ─────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $data = $request->validate([
            'title'            => ['required', 'string', 'max:100'],
            'description'      => ['nullable', 'string'],
            'location'         => ['nullable', 'string', 'max:150'],
            'color'            => ['nullable', 'string', 'max:30'],
            'all_day'          => ['boolean'],
            'start_date'       => ['required', 'date'],
            'end_date'         => ['nullable', 'date'],
            'start_time'       => ['nullable', 'string', 'max:20'],
            'interviewer_id'   => ['nullable', 'exists:employees,id'],
            'applicant_ids'    => ['nullable', 'array'],
            'applicant_ids.*'  => ['exists:applicants,id'],
        ]);

        return response()->json($this->events->store($request, $data), 201);
    }

    // ─── Update ─────────────────────────────────────────────────────
    public function update(Request $request, HiringEvent $hiringEvent)
    {
        $data = $request->validate([
            'title'            => ['sometimes', 'string', 'max:100'],
            'description'      => ['nullable', 'string'],
            'location'         => ['nullable', 'string', 'max:150'],
            'color'            => ['nullable', 'string', 'max:30'],
            'all_day'          => ['boolean'],
            'start_date'       => ['sometimes', 'date'],
            'end_date'         => ['nullable', 'date'],
            'start_time'       => ['nullable', 'string', 'max:20'],
            'interviewer_id'   => ['nullable', 'exists:employees,id'],
            'applicant_ids'    => ['nullable', 'array'],
            'applicant_ids.*'  => ['exists:applicants,id'],
        ]);

        return response()->json($this->events->update($request, $hiringEvent, $data));
    }

    // ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(Request $request, HiringEvent $hiringEvent)
    {
        $this->events->destroy($request, $hiringEvent);

        return response()->json(['message' => 'Event deleted successfully.']);
    }
}
