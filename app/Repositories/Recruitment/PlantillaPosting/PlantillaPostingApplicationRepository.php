<?php

namespace App\Repositories\Recruitment\PlantillaPosting;

use App\Models\PlantillaPostingApplication;
use App\Models\PlantillaPostingApplicationDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PlantillaPostingApplicationRepository
{
    public function existsFor(int $postingId, int $employeeId): bool
    {
        return PlantillaPostingApplication::where('plantilla_posting_id', $postingId)
            ->where('employee_id', $employeeId)
            ->exists();
    }

    public function create(array $attributes): PlantillaPostingApplication
    {
        return PlantillaPostingApplication::create($attributes);
    }

    public function createDocument(array $attributes): PlantillaPostingApplicationDocument
    {
        return PlantillaPostingApplicationDocument::create($attributes);
    }

    public function findExistingDocument(int $applicationId, string $key): ?PlantillaPostingApplicationDocument
    {
        return PlantillaPostingApplicationDocument::where('plantilla_posting_application_id', $applicationId)
            ->where('document_key', $key)
            ->first();
    }

    public function forEmployee(int $employeeId): Collection
    {
        return PlantillaPostingApplication::where('employee_id', $employeeId)
            ->with(['posting.department', 'posting.division', 'documents', 'interview'])
            ->latest('submitted_at')
            ->get();
    }

    public function loadApplicationRelations(PlantillaPostingApplication $application): PlantillaPostingApplication
    {
        return $application->fresh(['posting.department', 'posting.division', 'documents', 'interview']);
    }

    public function forPosting($posting): Collection
    {
        return $posting->applications()
            ->with(['employee', 'documents', 'interview'])
            ->latest('submitted_at')
            ->get();
    }

    public function newAllQuery(): Builder
    {
        return PlantillaPostingApplication::with(['posting.department', 'employee', 'documents', 'interview'])
            ->latest('submitted_at');
    }

    public function findWithReviewRelations(int $id): PlantillaPostingApplication
    {
        return PlantillaPostingApplication::with(['posting.department', 'employee.info', 'employee.user', 'interview'])
            ->findOrFail($id);
    }

    public function updateReview(PlantillaPostingApplication $application, array $data): PlantillaPostingApplication
    {
        $application->update($data);

        return $application->fresh(['employee', 'documents', 'interview']);
    }

    public function findDocumentWithApplication(int $documentId): PlantillaPostingApplicationDocument
    {
        return PlantillaPostingApplicationDocument::with('application')->findOrFail($documentId);
    }

    public function cancelForPosting(int $postingId, array $excludedStatuses, string $remarks, ?int $reviewedByUserId): Collection
    {
        $applications = PlantillaPostingApplication::where('plantilla_posting_id', $postingId)
            ->whereNotIn('status', $excludedStatuses)
            ->with(['employee.user', 'posting'])
            ->get();

        $applications->each(function (PlantillaPostingApplication $application) use ($remarks, $reviewedByUserId) {
            $application->update([
                'status'      => PlantillaPostingApplication::STATUS_POSTING_CANCELLED,
                'remarks'     => $remarks,
                'reviewed_by' => $reviewedByUserId,
                'reviewed_at' => now(),
            ]);
        });

        return $applications;
    }
}