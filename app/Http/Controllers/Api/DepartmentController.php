<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index()
    {
        return Department::with(['head', 'division'])
            ->orderBy('name')
            ->get();
    }
    public function store(Request $request)
    {
        $data = $request->validate([
            'division_id'      => ['required', 'exists:divisions,id'],
            'code'             => ['required', 'string', 'max:50', 'unique:departments,code'],
            'name'             => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string'],
            'head_employee_id' => ['nullable', 'exists:employees,id'],
        ]);

        return Department::create($data)->load(['head', 'division']);
    }
    public function show(Department $department)
    {
        return $department->load(['head', 'division']);
    }
    public function update(Request $request, Department $department)
    {
        $data = $request->validate([
            'division_id'      => ['sometimes', 'exists:divisions,id'],
            'code'             => ['sometimes', 'string', 'max:50', 'unique:departments,code,' . $department->id],
            'name'             => ['sometimes', 'string', 'max:255'],
            'description'      => ['nullable', 'string'],
            'head_employee_id' => ['nullable', 'exists:employees,id'],
        ]);
        $department->update($data);
        return $department->fresh(['head', 'division']);
    }
    public function destroy(Department $department)
    {
        $deptId = $department->id;
        Employee::select('id', 'department_ids')
            ->get()
            ->each(function ($emp) use ($deptId) {
                $ids = $emp->department_ids ?? [];
                if (!in_array($deptId, $ids)) return; 
                $emp->department_ids = array_values(
                    array_filter($ids, fn($id) => $id !== $deptId)
                );
                $emp->save();
            });
        $department->delete();
        return response()->json(['message' => 'Department deleted.']);
    }
}