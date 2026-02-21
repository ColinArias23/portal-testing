<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalaryGrade;
use Illuminate\Http\Request;

class SalaryGradeController extends Controller
{
    public function index()
    {
        return SalaryGrade::orderBy('salary_grade')->paginate(50);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'salary_grade' => ['required','integer','min:1','max:99','unique:salary_grades,salary_grade'],
            'monthly_salary' => ['nullable','numeric'],
            'annual_salary' => ['nullable','numeric'],
        ]);

        return SalaryGrade::create($data);
    }

    public function show(SalaryGrade $salaryGrade)
    {
        return $salaryGrade->load('stepIncrements');
    }

    public function update(Request $request, SalaryGrade $salaryGrade)
    {
        $data = $request->validate([
            'salary_grade' => ['sometimes','integer','min:1','max:99','unique:salary_grades,salary_grade,'.$salaryGrade->id],
            'monthly_salary' => ['nullable','numeric'],
            'annual_salary' => ['nullable','numeric'],
        ]);

        $salaryGrade->update($data);

        return $salaryGrade->fresh();
    }

    public function destroy(SalaryGrade $salaryGrade)
    {
        $salaryGrade->delete();
        return response()->json(['message' => 'Salary grade deleted.']);
    }
}
