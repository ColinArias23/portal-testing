<?php

namespace App\Http\Controllers\Api\Recruitment\PlantillaPosting;

use App\Http\Controllers\Controller;
use App\Models\PlantillaPostingApplication;
use App\Services\Recruitment\PlantillaPosting\PlantillaPostingApplicationService;
use App\Services\Recruitment\PlantillaPosting\PlantillaPostingService;
use Illuminate\Http\Request;

class PlantillaPostingController extends Controller
{
    public function __construct(
        private readonly PlantillaPostingService $postings,
        private readonly PlantillaPostingApplicationService $applications,
    ) {
        $this->middleware('auth:sanctum');

        $this->middleware('permission:hiring.plantilla.postings.view')->only(['index']);
        $this->middleware('permission:hiring.plantilla.postings.manage')->only(['store', 'update', 'destroy']);
        $this->middleware('permission:hiring.plantilla.applications.view')->only(['applications']);
        $this->middleware('permission:hiring.plantilla.applications.manage')->only(['reviewApplication']);
    }

    // ─── Index ─────────────────────────────────────────────────────
    public function index(Request $request)
    {
        return response()->json($this->postings->index($request));
    }

    // ─── Available ─────────────────────────────────────────────────
    public function available(Request $request)
    {
        return response()->json($this->postings->available($request));
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
    public function show($id)
    {
        return response()->json($this->postings->show((int) $id));
    }

    // ─── Update ─────────────────────────────────────────────────────
    public function update(Request $request, $id)
    {
        return response()->json($this->postings->update($request, (int) $id));
    }

    // ─── Destroy ─────────────────────────────────────────────────────
    public function destroy($id)
    {
        $this->postings->destroy((int) $id);

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
