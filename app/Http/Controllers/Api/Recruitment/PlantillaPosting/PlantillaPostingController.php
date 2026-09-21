<?php

namespace App\Http\Controllers\Api\Recruitment\PlantillaPosting;

use App\Http\Controllers\Controller;
use App\Http\Resources\Recruitment\PlantillaPosting\ApplicantQualificationResource;
use App\Models\PlantillaPosting;
use App\Models\PlantillaPostingApplication;
use App\Services\Recruitment\PlantillaPosting\PlantillaPostingApplicationService;
use App\Services\Recruitment\PlantillaPosting\PlantillaPostingService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PlantillaPostingController extends Controller
{
    private const CLOSING_DATE_ROLES = ['HR', 'SuperAdmin'];

    public function __construct(
        private readonly PlantillaPostingService $postings,
        private readonly PlantillaPostingApplicationService $applications,
    ) {
        $this->middleware('auth:sanctum');

        $this->middleware('permission:hiring.plantilla.postings.view')->only(['index']);
        $this->middleware('permission:hiring.plantilla.postings.manage')->only(['store', 'update', 'destroy']);
        $this->middleware('permission:hiring.plantilla.applications.view')->only(['applications', 'qualifications']);
        $this->middleware('permission:hiring.plantilla.applications.manage')->only(['reviewApplication']);
    }

    // ─── Index ─────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $postings = $this->postings->index($request);
        $this->hideClosingDateUnlessAuthorized($request, $postings);

        return response()->json($postings);
    }

    // ─── Available ─────────────────────────────────────────────────
    public function available(Request $request)
    {
        $postings = $this->postings->available($request);
        $this->hideClosingDateUnlessAuthorized($request, $postings);

        return response()->json($postings);
    }

    // ─── VacantItems ───────────────────────────────────────────────
    public function vacantItems()
    {
        return response()->json($this->postings->vacantItems());
    }

    // ─── Store ─────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $data = $this->postings->validatePosting($request);

        return response()->json($this->postings->store($request, $data), 201);
    }

    // ─── Show ─────────────────────────────────────────────────────
    public function show(Request $request, $id)
    {
        $posting = $this->postings->show((int) $id);
        $this->hideClosingDateUnlessAuthorized($request, $posting);

        return response()->json($posting);
    }
    // ─── hideClosingDateUnlessAuthorized ─────────────────────────────────────────────────────
    private function hideClosingDateUnlessAuthorized(Request $request, LengthAwarePaginator|PlantillaPosting $postings): void
    {
        if ($request->user()?->hasAnyRole(self::CLOSING_DATE_ROLES)) {
            return;
        }

        $models = $postings instanceof LengthAwarePaginator
            ? $postings->getCollection()
            : Collection::make([$postings]);

        $models->each(fn (PlantillaPosting $posting) => $posting->makeHidden('closing_date'));
    }

    // ─── Update ─────────────────────────────────────────────────────
    public function update(Request $request, $id)
    {
        return response()->json($this->postings->update($request, (int) $id));
    }

    // ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(Request $request, $id)
    {
        $this->postings->destroy($request, (int) $id);

        return response()->json(['message' => 'Posting deleted.']);
    }

    // ─── Apply ─────────────────────────────────────────────────────
    public function apply(Request $request, $id)
    {
        $validated = $request->validate($this->applications->applyValidationRules());

        return response()->json($this->applications->apply($request, (int) $id, $validated), 201);
    }

    // ─── MyApplications ─────────────────────────────────────────────
    public function myApplications(Request $request)
    {
        return response()->json($this->applications->myApplications($request));
    }

    // ─── UploadDocuments ─────────────────────────────────────────────
    public function uploadDocuments(Request $request, PlantillaPostingApplication $application)
    {
        $validated = $request->validate($this->applications->uploadDocumentsRules());

        return response()->json($this->applications->uploadDocuments($request, $application, $validated));
    }

    // ─── Applications ─────────────────────────────────────────────────
    public function applications($postingId)
    {
        return response()->json($this->applications->applications((int) $postingId));
    }

    // ─── AllApplications ─────────────────────────────────────────────
    public function allApplications(Request $request)
    {
        return response()->json($this->applications->allApplications($request));
    }

    // ─── Qualifications (PSB Comparative Assessment reference) ─────────
    public function qualifications($applicationId)
    {
        $application = $this->applications->qualifications((int) $applicationId);

        return new ApplicantQualificationResource($application);
    }

    // ─── ReviewApplication ─────────────────────────────────────────────
    public function reviewApplication(Request $request, $applicationId)
    {
        $validated = $request->validate($this->applications->reviewValidationRules());

        return response()->json($this->applications->reviewApplication($request, (int) $applicationId, $validated));
    }

    // ─── DownloadDocument ─────────────────────────────────────────────
    public function downloadDocument(Request $request, $documentId)
    {
        return $this->applications->downloadDocument($request, (int) $documentId);
    }
}