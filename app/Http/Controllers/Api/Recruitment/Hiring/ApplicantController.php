<?php

namespace App\Http\Controllers\Api\Recruitment\Hiring;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Services\Recruitment\Hiring\ApplicantService;
use Illuminate\Http\Request;

class ApplicantController extends Controller
{
    public function __construct(
        private readonly ApplicantService $applicants,
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:hiring.nonplantilla.applicants.view')->only(['index', 'show', 'summary']);
        $this->middleware('permission:hiring.nonplantilla.applicants.manage')->only(['store', 'update', 'destroy', 'restore', 'moveToOnboarding']);
    }

    // ─── Index ─────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $paginated = $this->applicants->index($request);

        return response()->json([
            'data' => $paginated->getCollection()->map(fn ($a) => $this->applicants->format($a)),
            'pagination' => [
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
            ],
        ]);
    }

    // ─── Store ─────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name'    => ['required', 'string', 'max:100'],
            'last_name'     => ['required', 'string', 'max:100'],
            'email'         => ['nullable', 'email', 'max:100'],
            'age'           => ['nullable', 'string', 'max:5'],
            'phone'         => ['nullable', 'string', 'max:30'],
            'address'       => ['nullable', 'string'],
            'birthdate'     => ['nullable', 'date'],
            'source'        => ['nullable', 'string', 'max:60'],
            'position'      => ['nullable', 'string', 'max:100'],
            'department'    => ['nullable', 'string', 'max:100'],
            'date_applied'  => ['nullable', 'date'],
            'submission'    => ['nullable', 'string', 'max:100'],
            'status'        => ['nullable', 'string', 'max:60'],
            'remarks'       => ['nullable', 'string'],
            'documents'     => ['nullable', 'array'],
            'documents.*'   => ['string', 'max:255'],
        ]);

        return response()->json($this->applicants->format($this->applicants->store($request, $data)), 201);
    }

    // ─── Show ─────────────────────────────────────────────────────
    public function show(Applicant $applicant)
    {
        return response()->json($this->applicants->format($this->applicants->show($applicant)));
    }

    // ─── Update ─────────────────────────────────────────────────────
    public function update(Request $request, Applicant $applicant)
    {
        $data = $request->validate([
            'first_name'   => ['sometimes', 'string', 'max:100'],
            'last_name'    => ['sometimes', 'string', 'max:100'],
            'email'        => ['nullable', 'email', 'max:100'],
            'age'          => ['nullable', 'string', 'max:5'],
            'phone'        => ['nullable', 'string', 'max:30'],
            'address'      => ['nullable', 'string'],
            'birthdate'    => ['nullable', 'date'],
            'source'       => ['nullable', 'string', 'max:60'],
            'position'     => ['nullable', 'string', 'max:100'],
            'department'   => ['nullable', 'string', 'max:100'],
            'date_applied' => ['nullable', 'date'],
            'submission'   => ['nullable', 'string', 'max:100'],
            'status'       => ['nullable', 'string', 'max:60'],
            'remarks'      => ['nullable', 'string'],
        ]);

        return response()->json($this->applicants->format($this->applicants->update($request, $applicant, $data)));
    }

    // ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(Request $request, Applicant $applicant)
    {
        $this->applicants->destroy($request, $applicant);

        return response()->json(['message' => 'Applicant deleted successfully.']);
    }

    // ─── Restore ─────────────────────────────────────────────────────
    public function restore(Request $request, $id)
    {
        $result = $this->applicants->restore($request, (int) $id);

        return response()->json($result['body'], $result['status']);
    }

    // ─── Summary ─────────────────────────────────────────────────────
    public function summary()
    {
        return response()->json($this->applicants->summary());
    }

    // ─── MoveToOnboarding ─────────────────────────────────────────────────────
    public function moveToOnboarding(Request $request, Applicant $applicant)
    {
        $result = $this->applicants->moveToOnboarding($request, $applicant);

        return response()->json($result['body'], $result['status']);
    }
}
