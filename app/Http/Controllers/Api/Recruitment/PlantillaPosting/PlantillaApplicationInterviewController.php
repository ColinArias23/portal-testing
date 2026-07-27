<?php

namespace App\Http\Controllers\Api\Recruitment\PlantillaPosting;

use App\Http\Controllers\Controller;
use App\Models\PlantillaPostingApplication;
use App\Services\Recruitment\PlantillaPosting\PlantillaApplicationInterviewService;
use Illuminate\Http\Request;

class PlantillaApplicationInterviewController extends Controller
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
        private readonly PlantillaApplicationInterviewService $interviews,
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:hiring.plantilla.applications.view')->only(['index', 'show']);
        $this->middleware('permission:hiring.plantilla.applications.manage')->only(['update']);
    }

    // ─── Index ─────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $perPage = min((int) $request->integer('per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE);
        $perPage = $perPage > 0 ? $perPage : self::DEFAULT_PER_PAGE;

        return response()->json($this->interviews->index($request, $perPage));
    }

    // ─── Show ─────────────────────────────────────────────────────
    public function show(PlantillaPostingApplication $application)
    {
        return response()->json($this->interviews->show($application));
    }

    // ─── Update ─────────────────────────────────────────────────────
    public function update(Request $request, PlantillaPostingApplication $application)
    {
        $data = $request->validate(self::VALIDATION_RULES);

        return response()->json($this->interviews->update($request, $application, $data));
    }
}
