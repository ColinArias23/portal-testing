<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Applicant;
use App\Models\Interview;
use Illuminate\Http\Request;

class InterviewController extends Controller
{
    private const STATUS_FIELDS = ['hr_status', 'head_status', 'final_status', 'overall_status'];

    private const VALIDATION_RULES = [
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
    ];

    private const DEFAULT_PER_PAGE = 25;
    private const MAX_PER_PAGE = 100;

    public function __construct()
    {
        $this->middleware('auth:sanctum');

        $this->middleware('role:SuperAdmin|HR')->only([
            'index',
            'show',
            'update',
        ]);
    }

    public function index(Request $request)
    {
        $perPage = min((int) $request->integer('per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE);
        $perPage = $perPage > 0 ? $perPage : self::DEFAULT_PER_PAGE;

        $interviews = Interview::with(['applicant', 'plantillaPostingApplication.employee'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json($interviews);
    }

    public function show(Applicant $applicant)
    {
        $interview = Interview::where('applicant_id', $applicant->id)->firstOrFail();
        return response()->json($interview);
    }

    public function update(Request $request, Applicant $applicant)
    {
        $data = $this->validateAndUppercase($request);

        $existing = Interview::where('applicant_id', $applicant->id)->first();
        $before = $this->snapshotStatuses($existing);

        $interview = Interview::updateOrCreate(
            ['applicant_id' => $applicant->id],
            $data
        );

        broadcast(new \App\Events\InterviewUpdated($interview->load('applicant')))->toOthers();

        $this->logStatusChange($request, $applicant->id, Applicant::class, $applicant->full_name, $before, $interview);

        return response()->json($interview);
    }

    private function validateAndUppercase(Request $request): array
    {
        $data = $request->validate(self::VALIDATION_RULES);

        foreach (self::STATUS_FIELDS as $field) {
            if (isset($data[$field])) {
                $data[$field] = strtoupper($data[$field]);
            }
        }

        return $data;
    }

    private function snapshotStatuses(?Interview $interview): ?array
    {
        if (! $interview) {
            return null;
        }

        return collect(self::STATUS_FIELDS)
            ->mapWithKeys(fn ($field) => [$field => $interview->$field])
            ->toArray();
    }

    private function logStatusChange(
        Request $request,
        int $subjectId,
        string $subjectType,
        string $subjectLabel,
        ?array $before,
        Interview $interview
    ): void {
        ActivityLog::record(
            action: 'interview.updated',
            userId: $request->user()?->id,
            subjectType: $subjectType,
            subjectId: $subjectId,
            subjectLabel: $subjectLabel,
            payload: [
                'before' => $before,
                'after' => $this->snapshotStatuses($interview),
            ],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );
    }
}