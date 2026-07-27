<?php

namespace App\Repositories\Recruitment\Hiring;

use App\Models\Interview;
use Illuminate\Pagination\LengthAwarePaginator;

class InterviewRepository
{
    public function paginate(int $perPage): LengthAwarePaginator
    {
        return Interview::with(['applicant', 'plantillaPostingApplication.employee'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function findForApplicant(int $applicantId): Interview
    {
        return Interview::where('applicant_id', $applicantId)->firstOrFail();
    }

    public function findExistingForApplicant(int $applicantId): ?Interview
    {
        return Interview::where('applicant_id', $applicantId)->first();
    }

    public function updateOrCreateForApplicant(int $applicantId, array $data): Interview
    {
        return Interview::updateOrCreate(['applicant_id' => $applicantId], $data);
    }
}
