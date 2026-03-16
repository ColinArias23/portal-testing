<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\Request;

class DivisionController extends Controller
{
    public function index()
    {
        return Division::with(['departments', 'head'])
            ->orderBy('name')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'             => ['required', 'string', 'max:50', 'unique:divisions,code'],
            'name'             => ['required', 'string'],
            'description'      => ['nullable', 'string'],
            'head_employee_id' => ['nullable', 'exists:employees,id'],
        ]);

        return Division::create($data)->load(['departments', 'head']);
    }

    public function show(Division $division)
    {
        return $division->load(['departments', 'head']);
    }

    public function update(Request $request, Division $division)
    {
        $data = $request->validate([
            'code'             => ['sometimes', 'string', 'max:50', 'unique:divisions,code,' . $division->id],
            'name'             => ['sometimes', 'string'],
            'description'      => ['nullable', 'string'],
            'head_employee_id' => ['nullable', 'exists:employees,id'],
        ]);

        $division->update($data);

        return $division->fresh(['departments', 'head']);
    }

    public function destroy(Division $division)
    {
        // Null out division_id on departments that belong to this division,
        // so they become unassigned rather than having a broken FK reference.
        Department::where('division_id', $division->id)
            ->update(['division_id' => null]);

        // Null out division_id on employees directly assigned to this division.
        Employee::where('division_id', $division->id)
            ->update(['division_id' => null]);

        $division->delete();

        return response()->json(['message' => 'Division deleted']);
    }
}