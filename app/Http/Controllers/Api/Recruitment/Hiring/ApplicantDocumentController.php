<?php

namespace App\Http\Controllers\Api\Recruitment\Hiring;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Services\Recruitment\Hiring\ApplicantDocumentService;
use Illuminate\Http\Request;

class ApplicantDocumentController extends Controller
{
    public function __construct(
        private readonly ApplicantDocumentService $documents,
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:hiring.nonplantilla.applicants.view')->only(['index']);
        $this->middleware('permission:hiring.nonplantilla.applicants.manage')->only(['store', 'update', 'destroy']);
    }

    // ─── Index ─────────────────────────────────────────────────────
    public function index(Applicant $applicant)
    {
        return response()->json($this->documents->index($applicant));
    }

    // ─── Store ─────────────────────────────────────────────────────
    public function store(Request $request, Applicant $applicant)
    {
        $data = $request->validate([
            'name'   => ['required', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:30'],
        ]);

        return response()->json($this->documents->store($request, $applicant, $data), 201);
    }

    // ─── Update ─────────────────────────────────────────────────────
    public function update(Request $request, Applicant $applicant, ApplicantDocument $document)
    {
        if (! $this->documents->assertBelongsToApplicant($document, $applicant)) {
            return response()->json(['message' => 'Document does not belong to this applicant.'], 404);
        }

        $data = $request->validate([
            'name'   => ['sometimes', 'string', 'max:100'],
            'status' => ['sometimes', 'string', 'max:30'],
        ]);

        return response()->json($this->documents->update($request, $applicant, $document, $data));
    }

    // ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(Request $request, Applicant $applicant, ApplicantDocument $document)
    {
        if (! $this->documents->assertBelongsToApplicant($document, $applicant)) {
            return response()->json(['message' => 'Document does not belong to this applicant.'], 404);
        }

        $this->documents->destroy($request, $applicant, $document);

        return response()->json(['message' => 'Document removed.']);
    }
}
