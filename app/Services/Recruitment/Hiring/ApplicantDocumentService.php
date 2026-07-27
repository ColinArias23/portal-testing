<?php

namespace App\Services\Recruitment\Hiring;

use App\Models\ActivityLog;
use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Repositories\Recruitment\Hiring\ApplicantDocumentRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ApplicantDocumentService
{
    public function __construct(
        private readonly ApplicantDocumentRepository $documents,
    ) {
    }

    public function index(Applicant $applicant): Collection
    {
        return $this->documents->forApplicant($applicant);
    }

    public function assertBelongsToApplicant(ApplicantDocument $document, Applicant $applicant): bool
    {
        return $document->applicant_id === $applicant->id;
    }

    public function store(Request $request, Applicant $applicant, array $data): ApplicantDocument
    {
        $doc = $this->documents->create($applicant, [
            'name'   => $data['name'],
            'status' => $data['status'] ?? 'Pending',
        ]);

        ActivityLog::record(
            action:       'applicant.document_added',
            userId:       $request->user()?->id,
            subjectType:  Applicant::class,
            subjectId:    $applicant->id,
            subjectLabel: $applicant->full_name,
            payload:      [
                'document_id'   => $doc->id,
                'document_name' => $doc->name,
                'status'        => $doc->status,
            ],
            ip:        $request->ip(),
            userAgent: $request->userAgent(),
        );

        return $doc;
    }

    public function update(Request $request, Applicant $applicant, ApplicantDocument $document, array $data): ApplicantDocument
    {
        $before = ['status' => $document->status, 'name' => $document->name];
        $document = $this->documents->update($document, $data);

        ActivityLog::record(
            action:       'applicant.document_updated',
            userId:       $request->user()?->id,
            subjectType:  Applicant::class,
            subjectId:    $applicant->id,
            subjectLabel: $applicant->full_name,
            payload:      [
                'document_id'   => $document->id,
                'document_name' => $document->name,
                'before'        => $before,
                'after'         => ['status' => $document->status, 'name' => $document->name],
            ],
            ip:        $request->ip(),
            userAgent: $request->userAgent(),
        );

        return $document;
    }

    public function destroy(Request $request, Applicant $applicant, ApplicantDocument $document): void
    {
        $docId   = $document->id;
        $docName = $document->name;

        $this->documents->delete($document);

        ActivityLog::record(
            action:       'applicant.document_deleted',
            userId:       $request->user()?->id,
            subjectType:  Applicant::class,
            subjectId:    $applicant->id,
            subjectLabel: $applicant->full_name,
            payload:      [
                'document_id'   => $docId,
                'document_name' => $docName,
            ],
            ip:        $request->ip(),
            userAgent: $request->userAgent(),
        );
    }
}
