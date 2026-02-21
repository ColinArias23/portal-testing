<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Division;
use Illuminate\Http\Request;

class DivisionController extends Controller
{
    public function index(Request $request)
    {
        $q = Division::query()->with(['department', 'head']);

        if ($request->filled('department_id')) {
            $q->where('department_id', $request->integer('department_id'));
        }

        return $q->orderBy('name')->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'department_id' => ['required','exists:departments,id'],
            'code' => ['required','string','max:50'],
            'name' => ['required','string','max:255'],
            'description' => ['nullable','string'],
            'head_employee_id' => ['nullable','exists:employees,id'],
        ]);

        // enforce unique(department_id, code)
        $exists = Division::where('department_id', $data['department_id'])
            ->where('code', $data['code'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Code already exists for this department.'], 422);
        }

        return Division::create($data);
    }

    public function show(Division $division)
    {
        return $division->load(['department', 'head', 'employees']);
    }

    public function update(Request $request, Division $division)
    {
        $data = $request->validate([
            'department_id' => ['sometimes','exists:departments,id'],
            'code' => ['sometimes','string','max:50'],
            'name' => ['sometimes','string','max:255'],
            'description' => ['nullable','string'],
            'head_employee_id' => ['nullable','exists:employees,id'],
        ]);

        // if changing dept/code, re-check unique
        $deptId = $data['department_id'] ?? $division->department_id;
        $code   = $data['code'] ?? $division->code;

        $exists = Division::where('department_id', $deptId)
            ->where('code', $code)
            ->where('id', '!=', $division->id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Code already exists for this department.'], 422);
        }

        $division->update($data);

        return $division->fresh(['department','head']);
    }

    public function destroy(Division $division)
    {
        $division->delete();
        return response()->json(['message' => 'Division deleted.']);
    }
}
