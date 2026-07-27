<?php

namespace App\Http\Controllers\Api\Recruitment\Hiring;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Services\Recruitment\Hiring\InterviewService;
use Illuminate\Http\Request;

class InterviewController extends Controller
{
    private const VALIDATION_RULES = [
        'hr_status'          => ['nullable', 'string', 'max:30'],
        'head_status'        => ['nullable', 'string', 'max:30'],
        'final_status'       => ['nullable', 'string', 'max:30'],
        'overall_status'     => ['nullable', 'string', 'max:60'],
        'hr_scheduled_at'    => ['nullable', 'date'],
        'head_scheduled_at'  => ['nullable', 'date'],
        'final_scheduled_at' => ['nullable', 'date'],
        'hr_notes'           => ['nullable', 'string'],
        'head_notes'         => ['nullable', 'string'],
        'final_notes'        => ['nullable', 'string'],
    ];

    private const DEFAULT_PER_PAGE = 25;
    private const MAX_PER_PAGE = 100;

    public function __construct(
        private readonly InterviewService $interviews,
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:hiring.nonplantilla.applications.view')->only(['index', 'show']);
        $this->middleware('permission:hiring.nonplantilla.applications.manage')->only(['update']);
    }

    // ─── Index ─────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $perPage = min((int) $request->integer('per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE);
        $perPage = $perPage > 0 ? $perPage : self::DEFAULT_PER_PAGE;

        return response()->json($this->interviews->index($perPage));
    }

    // ─── Show ─────────────────────────────────────────────────────
    public function show(Applicant $applicant)
    {
        return response()->json($this->interviews->show($applicant));
    }

    // ─── Update ─────────────────────────────────────────────────────
    public function update(Request $request, Applicant $applicant)
    {
        $data = $request->validate(self::VALIDATION_RULES);

        return response()->json($this->interviews->update($request, $applicant, $data));
    }
}
