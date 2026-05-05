<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StepIncrement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StepIncrementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = StepIncrement::query()->with('salaryGrade');

        if ($request->filled('salary_grade_id')) {
            $query->where('salary_grade_id', $request->integer('salary_grade_id'));
        }

        return response()->json(
            $query->orderBy('salary_grade_id')
                  ->orderBy('step')
                  ->get(['id', 'salary_grade_id', 'step', 'monthly_salary', 'annual_salary'])
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'salary_grade_id' => ['required', 'exists:salary_grades,id'],
            'step'            => ['required', 'integer', 'min:1', 'max:8'],
            'monthly_salary'  => ['nullable', 'numeric', 'min:0'],
            'annual_salary'   => ['nullable', 'numeric', 'min:0'],
        ]);

        $exists = StepIncrement::where('salary_grade_id', $data['salary_grade_id'])
            ->where('step', $data['step'])
            ->exists();

        if ($exists) {
            return response()->json(
                ['message' => 'Step already exists for this salary grade.'],
                422
            );
        }

        return response()->json(
            StepIncrement::create($data)->load('salaryGrade'),
            201
        );
    }

    public function show(StepIncrement $stepIncrement): JsonResponse
    {
        return response()->json($stepIncrement->load('salaryGrade'));
    }

    public function update(Request $request, StepIncrement $stepIncrement): JsonResponse
    {
        $data = $request->validate([
            'salary_grade_id' => ['sometimes', 'exists:salary_grades,id'],
            'step'            => ['sometimes', 'integer', 'min:1', 'max:8'],
            'monthly_salary'  => ['nullable', 'numeric', 'min:0'],
            'annual_salary'   => ['nullable', 'numeric', 'min:0'],
        ]);

        $salaryGradeId = $data['salary_grade_id'] ?? $stepIncrement->salary_grade_id;
        $step          = $data['step']            ?? $stepIncrement->step;

        $duplicate = StepIncrement::where('salary_grade_id', $salaryGradeId)
            ->where('step', $step)
            ->where('id', '!=', $stepIncrement->id)
            ->exists();

        if ($duplicate) {
            return response()->json(
                ['message' => 'Step already exists for this salary grade.'],
                422
            );
        }

        $stepIncrement->update($data);

        return response()->json($stepIncrement->fresh('salaryGrade'));
    }

    public function destroy(StepIncrement $stepIncrement): JsonResponse
    {
        $stepIncrement->delete();

        return response()->json(['message' => 'Step increment deleted.']);
    }
}