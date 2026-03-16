<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DepartmentController extends Controller
{
    public function index()
    {
        // Use a consistent eager-load across all actions — load the full
        // head relationship so callers always get the same shape.
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
        // Remove this department's ID from every employee's JSON department_ids
        // array.  MySQL's JSON_REMOVE needs the exact path; we locate it first
        // via JSON_SEARCH and then strip it in a single UPDATE per row.
        //
        // Because JSON_SEARCH returns the path of the *first* match and
        // department_ids should never hold duplicate IDs, one pass is enough.
        DB::statement("
            UPDATE employees
            SET department_ids = JSON_REMOVE(
                department_ids,
                JSON_UNQUOTE(JSON_SEARCH(department_ids, 'one', ?))
            )
            WHERE JSON_CONTAINS(department_ids, ?)
        ", [$department->id, json_encode($department->id)]);

        $department->delete();

        return response()->json(['message' => 'Department deleted.']);
    }
}