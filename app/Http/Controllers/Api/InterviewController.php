<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Applicant;
use App\Models\Interview;
use Illuminate\Http\Request;

class InterviewController extends Controller
{
    public function index()
    {
        $interviews = Interview::with('applicant')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($interviews);
    }

    public function show(Applicant $applicant)
    {
        $interview = Interview::where('applicant_id', $applicant->id)->firstOrFail();
        return response()->json($interview);
    }

    public function update(Request $request, Applicant $applicant)
    {
        $data = $request->validate([
            'hr_status'          => ['nullable', 'string', 'max:30'],
            'head_status'        => ['nullable', 'string', 'max:30'],
            'final_status'       => ['nullable', 'string', 'max:30'],
            'overall_status'     => ['nullable', 'string', 'max:60'],
            'hr_scheduled_at'    => ['nullable', 'date'],
            'head_scheduled_at'  => ['nullable', 'date'],
            'final_scheduled_at' => ['nullable', 'date'],
            'hr_notes'           => ['nullable', 'string'],
            'head_notes'         => ['nullable', 'string'],
            'final_notes'        => ['nullable', 'string'],
        ]);

        // Uppercase the status fields before saving
        foreach (['hr_status', 'head_status', 'final_status', 'overall_status'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = strtoupper($data[$field]);
            }
        }

        $existing = Interview::where('applicant_id', $applicant->id)->first();

        $before = $existing ? [
            'hr_status'      => $existing->hr_status,
            'head_status'    => $existing->head_status,
            'final_status'   => $existing->final_status,
            'overall_status' => $existing->overall_status,
        ] : null;

        $interview = Interview::updateOrCreate(
            ['applicant_id' => $applicant->id],
            $data
        );

        // Broadcast to Reverb
        broadcast(new \App\Events\InterviewUpdated($interview->load('applicant')))->toOthers();

        ActivityLog::record(
            action:       'interview.updated',
            userId:       $request->user()?->id,
            subjectType:  Applicant::class,
            subjectId:    $applicant->id,
            subjectLabel: $applicant->full_name,
            payload:      [
                'before' => $before,
                'after'  => [
                    'hr_status'      => $interview->hr_status,
                    'head_status'    => $interview->head_status,
                    'final_status'   => $interview->final_status,
                    'overall_status' => $interview->overall_status,
                ],
            ],
            ip:        $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json($interview);
    }
}