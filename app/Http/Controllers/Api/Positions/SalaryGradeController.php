<?php

namespace App\Http\Controllers\Api\Positions;

use App\Http\Controllers\Controller;
use App\Models\SalaryGrade;
use App\Services\Positions\SalaryGradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalaryGradeController extends Controller
{
    public function __construct(
        private readonly SalaryGradeService $grades,
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:compensation.view')->only(['index', 'show']);
        $this->middleware('permission:compensation.manage')->only(['store', 'update', 'destroy']);
    }

    // ─── Index ─────────────────────────────────────────────────────
    public function index(): JsonResponse
    {
        return response()->json($this->grades->index());
    }

    // ─── Options (lightweight, for dropdowns — any authenticated user) ─────
    public function options(): JsonResponse
    {
        return response()->json($this->grades->options());
    }

    // ─── Store ─────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'salary_grade'   => ['required', 'integer', 'min:1', 'max:99', 'unique:salary_grades,salary_grade'],
            'monthly_salary' => ['nullable', 'numeric', 'min:0'],
            'annual_salary'  => ['nullable', 'numeric', 'min:0'],
        ]);

        return response()->json($this->grades->store($data), 201);
    }

    // ─── Show ─────────────────────────────────────────────────────
    public function show(SalaryGrade $salaryGrade): JsonResponse
    {
        return response()->json($this->grades->show($salaryGrade));
    }

    // ─── Update ─────────────────────────────────────────────────────
    public function update(Request $request, SalaryGrade $salaryGrade): JsonResponse
    {
        $data = $request->validate([
            'salary_grade'   => ['sometimes', 'integer', 'min:1', 'max:99', 'unique:salary_grades,salary_grade,' . $salaryGrade->id],
            'monthly_salary' => ['nullable', 'numeric', 'min:0'],
            'annual_salary'  => ['nullable', 'numeric', 'min:0'],
        ]);

        return response()->json($this->grades->update($salaryGrade, $data));
    }

    // ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(SalaryGrade $salaryGrade): JsonResponse
    {
        $result = $this->grades->destroy($salaryGrade);

        return response()->json($result['body'], $result['status']);
    }
}
