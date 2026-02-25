<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeHierarchy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $query = Employee::with([
            'info',
            'primaryAssignment.plantillaItem.department',
            'primaryAssignment.plantillaItem.division'
        ]);

        if ($request->filled('search')) {
            $s = trim($request->search);

            $query->where(function ($q) use ($s) {
                $q->where('employee_number', 'like', "%{$s}%")
                  ->orWhere('role_position', 'like', "%{$s}%")
                  ->orWhere('first_name', 'like', "%{$s}%")
                  ->orWhere('last_name', 'like', "%{$s}%")
                  ->orWhere('position_designation', 'like', "%{$s}%")
                  ->orWhere('title', 'like', "%{$s}%");
            });
        }

        return $query->orderBy('last_name')->paginate(10);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_number' => ['required','unique:employees,employee_number'],
            'role_position' => ['required','string'],
            'first_name' => ['required'],
            'last_name' => ['required'],
            'parent_ids' => ['nullable','array'],
            'parent_ids.*' => ['exists:employees,id'],
        ]);

        return DB::transaction(function () use ($data) {

            $employee = Employee::create(
                collect($data)->except('parent_ids')->toArray()
            );

            if (!empty($data['parent_ids'])) {
                foreach ($data['parent_ids'] as $parentId) {
                    EmployeeHierarchy::create([
                        'employee_id' => $employee->id,
                        'parent_id' => $parentId,
                    ]);
                }
            }

            return $employee->load([
                'info',
                'hierarchy.parent',
                'parentHierarchy.employee'
            ]);
        });
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'employee_number' => [
                'sometimes',
                Rule::unique('employees','employee_number')->ignore($employee->id)
            ],
            'role_position' => ['sometimes','string'],
            'first_name' => ['sometimes'],
            'last_name' => ['sometimes'],
            'parent_ids' => ['nullable','array'],
            'parent_ids.*' => ['exists:employees,id'],
        ]);

        return DB::transaction(function () use ($employee, $data) {

            $employee->update(
                collect($data)->except('parent_ids')->toArray()
            );

            if (array_key_exists('parent_ids', $data)) {

                EmployeeHierarchy::where('employee_id', $employee->id)
                    ->delete();

                foreach ($data['parent_ids'] ?? [] as $parentId) {
                    EmployeeHierarchy::create([
                        'employee_id' => $employee->id,
                        'parent_id' => $parentId,
                    ]);
                }
            }

            return $employee->fresh()->load([
                'info',
                'hierarchy.parent',
                'parentHierarchy.employee'
            ]);
        });
    }

    public function destroy(Employee $employee)
    {
        EmployeeHierarchy::where('employee_id', $employee->id)
            ->orWhere('parent_id', $employee->id)
            ->delete();

        $employee->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }
}