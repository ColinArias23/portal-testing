<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\Request;

class MappingController extends Controller
{
    // filter by department -> list divisions + employee
    public function index(Request $request)
    {
        $request->validate([
            'department_id' => ['nullable','exists:departments,id'],
        ]);

        $deptId = $request->integer('department_id');

        $employee = Employee::query()
            ->with(['division.department', 'plantillaItem.salaryGrade', 'stepIncrement'])
            ->when($deptId, function ($q) use ($deptId) {
                $q->whereHas('division', fn($qq) => $qq->where('department_id', $deptId));
            })
            ->orderBy('last_name')
            ->paginate(50);

        return [
            'departments' => Department::select('id','code','name')->orderBy('name')->get(),
            'employee' => $employee,
        ];
    }

    // generate report (you can change output later to excel/pdf)
    public function report(Request $request)
    {
        $request->validate([
            'department_id' => ['required','exists:departments,id'],
        ]);

        $deptId = $request->integer('department_id');

        $rows = Employee::query()
            ->with(['division.department', 'plantillaItem.salaryGrade', 'stepIncrement'])
            ->whereHas('division', fn($qq) => $qq->where('department_id', $deptId))
            ->get();

        return [
            'department_id' => $deptId,
            'count' => $rows->count(),
            'data' => $rows,
        ];
    }
}
