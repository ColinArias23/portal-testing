<?php

namespace App\Repositories\Recruitment\Hiring;

use App\Models\Applicant;
use App\Models\ApplicantDocument;
use Illuminate\Support\Collection;

class ApplicantDocumentRepository
{
    public function forApplicant(Applicant $applicant): Collection
    {
        return $applicant->documents;
    }

    public function create(Applicant $applicant, array $data): ApplicantDocument
    {
        return $applicant->documents()->create($data);
    }

    public function update(ApplicantDocument $document, array $data): ApplicantDocument
    {
        $document->update($data);

        return $document;
    }

    public function delete(ApplicantDocument $document): void
    {
        $document->delete();
    }
}
