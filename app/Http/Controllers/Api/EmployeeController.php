<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $q = Employee::query()->with([
            'plantillaItem.salaryGrade',
            'stepIncrement.salaryGrade',
            'divisions.department',
        ]);

        if ($request->filled('search')) {
            $s = $request->string('search');
            $q->where(function ($qq) use ($s) {
                $qq->where('employee_number', 'like', "%{$s}%")
                   ->orWhere('first_name', 'like', "%{$s}%")
                   ->orWhere('last_name', 'like', "%{$s}%");
            });
        }

        if ($request->filled('division_id')) {
            $divisionId = $request->integer('division_id');
            $q->whereHas('divisions', fn ($qq) => $qq->where('divisions.id', $divisionId));
        }

        return $q->orderBy('last_name')->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_number' => ['required','string','max:100','unique:employees,employee_number'],
            'plantilla_item_id' => ['nullable','exists:plantilla_items,id'],
            'sg_level' => ['nullable','integer','min:1','max:99'],
            'step_increment_id' => ['nullable','exists:step_increments,id'],

            'prefix' => ['nullable','string','max:50'],
            'first_name' => ['required','string','max:255'],
            'middle_name' => ['nullable','string','max:255'],
            'last_name' => ['required','string','max:255'],
            'suffix' => ['nullable','string','max:50'],

            'title' => ['nullable','string','max:255'],
            'position_designation' => ['nullable','string','max:255'],

            'role' => ['nullable','string','max:255'],
            'employment_type' => ['nullable','string','max:255'],
            'employment_status' => ['nullable','string','max:255'],

            'avatar' => ['nullable','string','max:255'],
            'border_color' => ['nullable','string','max:50'],
            'aligned' => ['nullable','boolean'],
            'expanded' => ['nullable','boolean'],
            'notes' => ['nullable','string'],

            // optional: divisions attach in same request
            'division_ids' => ['nullable','array'],
            'division_ids.*' => ['integer','exists:divisions,id'],
            'primary_division_id' => ['nullable','integer','exists:divisions,id'],
        ]);

        $divisionIds = $data['division_ids'] ?? [];
        $primaryId   = $data['primary_division_id'] ?? null;

        unset($data['division_ids'], $data['primary_division_id']);

        $employee = Employee::create($data);

        // attach divisions + set primary
        if (!empty($divisionIds)) {
            $sync = [];
            foreach ($divisionIds as $id) {
                $sync[$id] = ['is_primary' => ($primaryId && $primaryId == $id)];
            }
            $employee->divisions()->sync($sync);
        }

        return $employee->load(['plantillaItem.salaryGrade','stepIncrement.salaryGrade','divisions.department']);
    }

    public function show(Employee $employee)
    {
        return $employee->load(['plantillaItem.salaryGrade','stepIncrement.salaryGrade','divisions.department']);
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'employee_number' => ['sometimes','string','max:100','unique:employees,employee_number,'.$employee->id],
            'plantilla_item_id' => ['nullable','exists:plantilla_items,id'],
            'sg_level' => ['nullable','integer','min:1','max:99'],
            'step_increment_id' => ['nullable','exists:step_increments,id'],

            'prefix' => ['nullable','string','max:50'],
            'first_name' => ['sometimes','string','max:255'],
            'middle_name' => ['nullable','string','max:255'],
            'last_name' => ['sometimes','string','max:255'],
            'suffix' => ['nullable','string','max:50'],

            'title' => ['nullable','string','max:255'],
            'position_designation' => ['nullable','string','max:255'],

            'role' => ['nullable','string','max:255'],
            'employment_type' => ['nullable','string','max:255'],
            'employment_status' => ['nullable','string','max:255'],

            'avatar' => ['nullable','string','max:255'],
            'border_color' => ['nullable','string','max:50'],
            'aligned' => ['nullable','boolean'],
            'expanded' => ['nullable','boolean'],
            'notes' => ['nullable','string'],

            'division_ids' => ['nullable','array'],
            'division_ids.*' => ['integer','exists:divisions,id'],
            'primary_division_id' => ['nullable','integer','exists:divisions,id'],
        ]);

        $divisionIds = $data['division_ids'] ?? null;
        $primaryId   = $data['primary_division_id'] ?? null;

        unset($data['division_ids'], $data['primary_division_id']);

        $employee->update($data);

        if (is_array($divisionIds)) {
            $sync = [];
            foreach ($divisionIds as $id) {
                $sync[$id] = ['is_primary' => ($primaryId && $primaryId == $id)];
            }
            $employee->divisions()->sync($sync);
        }

        return $employee->fresh(['plantillaItem.salaryGrade','stepIncrement.salaryGrade','divisions.department']);
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();
        return response()->json(['message' => 'Employee deleted.']);
    }
}
