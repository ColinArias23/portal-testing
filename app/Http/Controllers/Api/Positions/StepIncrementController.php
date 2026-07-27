<?php

namespace App\Http\Controllers\Api\Positions;

use App\Http\Controllers\Controller;
use App\Models\StepIncrement;
use App\Services\Positions\StepIncrementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StepIncrementController extends Controller
{
    public function __construct(
        private readonly StepIncrementService $steps,
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:compensation.view')->only(['index', 'show']);
        $this->middleware('permission:compensation.manage')->only(['store', 'update', 'destroy']);
    }

    // ─── Index ─────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $salaryGradeId = $request->filled('salary_grade_id') ? $request->integer('salary_grade_id') : null;

        return response()->json($this->steps->index($salaryGradeId));
    }

    // ─── Store ─────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'salary_grade_id' => ['required', 'exists:salary_grades,id'],
            'step'            => ['required', 'integer', 'min:1', 'max:8'],
            'monthly_salary'  => ['nullable', 'numeric', 'min:0'],
            'annual_salary'   => ['nullable', 'numeric', 'min:0'],
        ]);

        $result = $this->steps->store($data);

        return response()->json($result['body'], $result['status']);
    }

    // ─── Show ─────────────────────────────────────────────────────
    public function show(StepIncrement $stepIncrement): JsonResponse
    {
        return response()->json($this->steps->show($stepIncrement));
    }

    // ─── Update ─────────────────────────────────────────────────────
    public function update(Request $request, StepIncrement $stepIncrement): JsonResponse
    {
        $data = $request->validate([
            'salary_grade_id' => ['sometimes', 'exists:salary_grades,id'],
            'step'            => ['sometimes', 'integer', 'min:1', 'max:8'],
            'monthly_salary'  => ['nullable', 'numeric', 'min:0'],
            'annual_salary'   => ['nullable', 'numeric', 'min:0'],
        ]);

        $result = $this->steps->update($stepIncrement, $data);

        return response()->json($result['body'], $result['status']);
    }

    // ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(StepIncrement $stepIncrement): JsonResponse
    {
        $this->steps->destroy($stepIncrement);

        return response()->json(['message' => 'Step increment deleted.']);
    }
}
