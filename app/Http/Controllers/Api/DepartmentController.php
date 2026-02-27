<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index()
    {
        return Department::with(['head:id,first_name,last_name', 'division'])->orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required','string','max:50','unique:departments,code'],
            'name' => ['required','string','max:255'],
            'description' => ['nullable','string'],
            'head_employee_id' => ['nullable','exists:employees,id'],
        ]);

        return Department::create($data);
    }

    public function show(Department $department)
    {
        return $department->load(['head', 'division']);
    }

    public function update(Request $request, Department $department)
    {
        $data = $request->validate([
            'code' => ['sometimes','string','max:50','unique:departments,code,'.$department->id],
            'name' => ['sometimes','string','max:255'],
            'description' => ['nullable','string'],
            'head_employee_id' => ['nullable','exists:employees,id'],
        ]);

        $department->update($data);

        return $department->fresh(['head','division']);
    }

    public function destroy(Department $department)
    {
        $department->delete();
        return response()->json(['message' => 'Department deleted.']);
    }
}
