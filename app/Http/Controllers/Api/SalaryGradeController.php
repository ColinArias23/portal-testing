<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalaryGrade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalaryGradeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:compensation.view')->only(['index', 'show']);
        $this->middleware('permission:compensation.manage')->only(['store', 'update', 'destroy']);
    }
// ─── Index ─────────────────────────────────────────────────────
    public function index(): JsonResponse
    {
        return response()->json(
            SalaryGrade::orderBy('salary_grade')
                ->get(['id', 'salary_grade', 'monthly_salary', 'annual_salary'])
        );
    }
// ─── Store ─────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'salary_grade'   => ['required', 'integer', 'min:1', 'max:99', 'unique:salary_grades,salary_grade'],
            'monthly_salary' => ['nullable', 'numeric', 'min:0'],
            'annual_salary'  => ['nullable', 'numeric', 'min:0'],
        ]);

        return response()->json(SalaryGrade::create($data), 201);
    }
// ─── Show ─────────────────────────────────────────────────────
    public function show(SalaryGrade $salaryGrade): JsonResponse
    {
        return response()->json($salaryGrade->load('stepIncrements'));
    }
// ─── Update ─────────────────────────────────────────────────────
    public function update(Request $request, SalaryGrade $salaryGrade): JsonResponse
    {
        $data = $request->validate([
            'salary_grade'   => ['sometimes', 'integer', 'min:1', 'max:99', 'unique:salary_grades,salary_grade,' . $salaryGrade->id],
            'monthly_salary' => ['nullable', 'numeric', 'min:0'],
            'annual_salary'  => ['nullable', 'numeric', 'min:0'],
        ]);

        $salaryGrade->update($data);

        return response()->json($salaryGrade->fresh());
    }
// ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(SalaryGrade $salaryGrade): JsonResponse
    {
        if ($salaryGrade->plantillaItems()->exists()) {
            return response()->json(
                ['message' => 'Cannot delete a salary grade that is in use by plantilla items.'],
                422
            );
        }

        $salaryGrade->delete();

        return response()->json(['message' => 'Salary grade deleted.']);
    }
}