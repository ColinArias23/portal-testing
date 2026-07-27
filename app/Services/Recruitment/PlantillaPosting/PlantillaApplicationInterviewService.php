<?php

namespace App\Services\Recruitment\PlantillaPosting;

use App\Events\Recruitment\PlantillaPosting\PlantillaApplicationInterviewUpdated;
use App\Models\ActivityLog;
use App\Models\PlantillaPostingApplication;
use App\Models\PlantillaPostingApplicationInterview;
use App\Repositories\Recruitment\PlantillaPosting\PlantillaApplicationInterviewRepository;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PlantillaApplicationInterviewService
{
    private const STATUS_FIELDS = ['hr_status', 'head_status', 'final_status', 'overall_status'];

    public function __construct(
        private readonly PlantillaApplicationInterviewRepository $interviews,
    ) {
    }

    public function index(Request $request, int $perPage): LengthAwarePaginator
    {
        $query = $this->interviews->newQuery();

        if ($overallStatus = $request->get('overall_status')) {
            $query->where('overall_status', strtoupper($overallStatus));
        }

        if ($postingId = $request->get('plantilla_posting_id')) {
            $query->whereHas('plantillaPostingApplication', fn ($q) => $q->where('plantilla_posting_id', (int) $postingId));
        }

        return $this->interviews->paginate($query, $perPage);
    }

    public function show(PlantillaPostingApplication $application): PlantillaPostingApplicationInterview
    {
        return $this->interviews->findForApplication($application->id);
    }

    public function update(Request $request, PlantillaPostingApplication $application, array $data): PlantillaPostingApplicationInterview
    {
        foreach (self::STATUS_FIELDS as $field) {
            if (isset($data[$field])) {
                $data[$field] = strtoupper($data[$field]);
            }
        }

        [$interview, $before] = DB::transaction(function () use ($application, $data) {
            $existing = $this->interviews->lockExistingForApplication($application->id);
            $before   = $this->snapshotStatuses($existing);

            if ($existing) {
                $existing->update($data);

                return [$existing, $before];
            }

            $created = $this->interviews->create([
                ...$data,
                'plantilla_posting_application_id' => $application->id,
            ]);

            return [$created, $before];
        });

        $this->interviews->loadRelations($interview);

        broadcast(new PlantillaApplicationInterviewUpdated($interview))->toOthers();

        ActivityLog::record(
            action: 'plantilla_application_interview.updated',
            userId: $request->user()?->id,
            subjectType: PlantillaPostingApplication::class,
            subjectId: $application->id,
            subjectLabel: optional($application->employee)->full_name ?? "Application #{$application->id}",
            payload: [
                'before' => $before,
                'after'  => $this->snapshotStatuses($interview),
            ],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return $interview;
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
}
