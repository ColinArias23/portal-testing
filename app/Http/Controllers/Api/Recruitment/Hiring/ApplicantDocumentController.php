<?php

namespace App\Http\Controllers\Api\Recruitment\Hiring;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Applicant;
use App\Models\ApplicantDocument;
use Illuminate\Http\Request;

class ApplicantDocumentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:hiring.nonplantilla.applicants.view')->only(['index']);
        $this->middleware('permission:hiring.nonplantilla.applicants.manage')->only(['store', 'update', 'destroy']);
    }
// ─── Index ─────────────────────────────────────────────────────
    public function index(Applicant $applicant)
    {
        return response()->json($applicant->documents);
    }

    public function store(Request $request, Applicant $applicant)
    {
        $data = $request->validate([
            'name'   => ['required', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:30'],
        ]);

        $doc = $applicant->documents()->create([
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

        return response()->json($doc, 201);
    }
// ─── Update ─────────────────────────────────────────────────────
    public function update(Request $request, Applicant $applicant, ApplicantDocument $document)
    {
        $data = $request->validate([
            'name'   => ['sometimes', 'string', 'max:100'],
            'status' => ['sometimes', 'string', 'max:30'],
        ]);

        $before = ['status' => $document->status, 'name' => $document->name];
        $document->update($data);

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

        return response()->json($document);
    }
// ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(Request $request, Applicant $applicant, ApplicantDocument $document)
    {
        $docId   = $document->id;
        $docName = $document->name;

        $document->delete();

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

        return response()->json(['message' => 'Document removed.']);
    }
}