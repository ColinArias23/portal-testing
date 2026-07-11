<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:departments.view')->only(['index', 'show']);
        $this->middleware('permission:departments.manage')->only(['store', 'update', 'destroy']);
    }
// ─── Index ─────────────────────────────────────────────────────
    public function index()
    {
        $departments = Department::with(['head', 'division'])
            ->orderBy('name')
            ->get();

        $allEmployees = Employee::select('id', 'department_ids')
            ->whereNotNull('department_ids')
            ->get();

        return $departments->map(function ($dept) use ($allEmployees) {
            $employeeIds = $allEmployees
                ->filter(fn($emp) => in_array($dept->id, array_map('intval', $emp->department_ids ?? [])))
                ->pluck('id')
                ->values()
                ->toArray();

            return array_merge($dept->toArray(), [
                'employee_ids' => $employeeIds,
            ]);
        });
    }
// ─── Store ─────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $data = $request->validate([
            'division_id'      => ['required', 'exists:divisions,id'],
            'parent_id'        => ['nullable', 'exists:departments,id'],
            'parent_division_id' => ['nullable', 'exists:divisions,id'],
            'type'             => ['required', 'in:DEPARTMENT,SECTION,UNIT'],
            'code'             => ['required', 'string', 'max:50', 'unique:departments,code'],
            'name'             => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string'],
            'head_employee_id' => ['nullable', 'exists:employees,id'],
        ]);

        return Department::create($data)->load(['head', 'division', 'parent', 'parentDivision']);
    }
// ─── Show ─────────────────────────────────────────────────────
    public function show(Department $department)
    {
        return $department->load(['head', 'division', 'parent']);
    }

    public function update(Request $request, Department $department)
    {
        $data = $request->validate([
            'division_id'      => ['sometimes', 'exists:divisions,id'],
            'parent_id'        => ['nullable', 'exists:departments,id'],
            'parent_division_id' => ['nullable', 'exists:divisions,id'],
            'type'             => ['required', 'in:DEPARTMENT,SECTION,UNIT'],
            'code'             => ['sometimes', 'string', 'max:50', 'unique:departments,code,' . $department->id],
            'name'             => ['sometimes', 'string', 'max:255'],
            'description'      => ['nullable', 'string'],
            'head_employee_id' => ['nullable', 'exists:employees,id'],
            'employee_ids'     => ['nullable', 'array'],
            'employee_ids.*'   => ['exists:employees,id'],
        ]);

        $department->update(collect($data)->except('employee_ids')->toArray());

        if ($request->has('employee_ids')) {
            $deptId    = (int) $department->id;
            $newEmpIds = collect($data['employee_ids'] ?? [])
                ->map(fn($id) => (int) $id);

            $currentEmps = Employee::whereRaw(
                'JSON_CONTAINS(department_ids, ?)',
                [json_encode($deptId)]
            )->get();

            $currentEmps->each(function ($emp) use ($deptId, $newEmpIds) {
                if (!$newEmpIds->contains($emp->id)) {
                    $emp->department_ids = array_values(
                        array_filter(
                            $emp->department_ids ?? [],
                            fn($id) => (int) $id !== $deptId
                        )
                    );
                    $emp->save();
                }
            });

            $newEmpIds->each(function ($empId) use ($deptId) {
                $emp = Employee::find($empId);
                if (!$emp) return;
                $ids = array_map('intval', $emp->department_ids ?? []);
                if (!in_array($deptId, $ids)) {
                    $emp->department_ids = [...$ids, $deptId];
                    $emp->save();
                }
            });
        }

        return $department->fresh(['head', 'division', 'parent', 'parentDivision']);
    }
// ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(Department $department)
    {
        $deptId = (int) $department->id;

        Employee::whereRaw(
            'JSON_CONTAINS(department_ids, ?)',
            [json_encode($deptId)]
        )
        ->get()
        ->each(function ($emp) use ($deptId) {
            $emp->department_ids = array_values(
                array_filter(
                    $emp->department_ids ?? [],
                    fn($id) => (int) $id !== $deptId
                )
            );
            $emp->save();
        });

        $department->delete();
        return response()->json(['message' => 'Department deleted.']);
    }
}