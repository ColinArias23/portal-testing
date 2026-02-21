<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StepIncrement;
use Illuminate\Http\Request;

class StepIncrementController extends Controller
{
    public function index(Request $request)
    {
        $q = StepIncrement::query()->with('salaryGrade');

        if ($request->filled('salary_grade_id')) {
            $q->where('salary_grade_id', $request->integer('salary_grade_id'));
        }

        return $q->orderBy('salary_grade_id')->orderBy('step')->paginate(50);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'salary_grade_id' => ['required','exists:salary_grades,id'],
            'step' => ['required','integer','min:1','max:8'],
            'description' => ['nullable','string'],
            'increment_amount' => ['nullable','numeric'],
        ]);

        // unique(salary_grade_id, step)
        $exists = StepIncrement::where('salary_grade_id', $data['salary_grade_id'])
            ->where('step', $data['step'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Step already exists for this Salary Grade.'], 422);
        }

        return StepIncrement::create($data)->load('salaryGrade');
    }

    public function show(StepIncrement $stepIncrement)
    {
        return $stepIncrement->load('salaryGrade');
    }

    public function update(Request $request, StepIncrement $stepIncrement)
    {
        $data = $request->validate([
            'salary_grade_id' => ['sometimes','exists:salary_grades,id'],
            'step' => ['sometimes','integer','min:1','max:8'],
            'description' => ['nullable','string'],
            'increment_amount' => ['nullable','numeric'],
        ]);

        $salaryGradeId = $data['salary_grade_id'] ?? $stepIncrement->salary_grade_id;
        $step          = $data['step'] ?? $stepIncrement->step;

        $exists = StepIncrement::where('salary_grade_id', $salaryGradeId)
            ->where('step', $step)
            ->where('id', '!=', $stepIncrement->id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Step already exists for this Salary Grade.'], 422);
        }

        $stepIncrement->update($data);

        return $stepIncrement->fresh('salaryGrade');
    }

    public function destroy(StepIncrement $stepIncrement)
    {
        $stepIncrement->delete();
        return response()->json(['message' => 'Step increment deleted.']);
    }
}
