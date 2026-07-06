<?php

namespace App\Http\Controllers\Api;

use App\Events\PlantillaApplicationInterviewUpdated;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PlantillaPostingApplication;
use App\Models\PlantillaPostingApplicationInterview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlantillaApplicationInterviewController extends Controller
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

    private const EAGER_LOAD = ['plantillaPostingApplication.employee', 'plantillaPostingApplication.posting'];

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

        $query = PlantillaPostingApplicationInterview::query()->with(self::EAGER_LOAD);

        if ($overallStatus = $request->get('overall_status')) {
            $query->where('overall_status', strtoupper($overallStatus));
        }

        if ($postingId = $request->get('plantilla_posting_id')) {
            $query->whereHas('plantillaPostingApplication', function ($q) use ($postingId) {
                $q->where('plantilla_posting_id', (int) $postingId);
            });
        }

        $interviews = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($interviews);
    }

    public function show(PlantillaPostingApplication $application)
    {
        $interview = PlantillaPostingApplicationInterview::where('plantilla_posting_application_id', $application->id)
            ->with(self::EAGER_LOAD)
            ->firstOrFail();

        return response()->json($interview);
    }

    public function update(Request $request, PlantillaPostingApplication $application)
    {
        $data = $this->validateAndUppercase($request);

        [$interview, $before] = DB::transaction(function () use ($application, $data) {
            $existing = PlantillaPostingApplicationInterview::where('plantilla_posting_application_id', $application->id)
                ->lockForUpdate()
                ->first();

            $before = $this->snapshotStatuses($existing);

            if ($existing) {
                $existing->update($data);

                return [$existing, $before];
            }

            $created = PlantillaPostingApplicationInterview::create(array_merge($data, [
                'plantilla_posting_application_id' => $application->id,
            ]));

            return [$created, $before];
        });

        $interview->load(self::EAGER_LOAD);

        broadcast(new PlantillaApplicationInterviewUpdated($interview))->toOthers();

        $this->logStatusChange(
            $request,
            $application->id,
            optional($application->employee)->full_name ?? "Application #{$application->id}",
            $before,
            $interview
        );

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

    private function snapshotStatuses(?PlantillaPostingApplicationInterview $interview): ?array
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
        int $applicationId,
        string $subjectLabel,
        ?array $before,
        PlantillaPostingApplicationInterview $interview
    ): void {
        ActivityLog::record(
            action: 'plantilla_application_interview.updated',
            userId: $request->user()?->id,
            subjectType: PlantillaPostingApplication::class,
            subjectId: $applicationId,
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