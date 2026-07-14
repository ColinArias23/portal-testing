<?php

namespace App\Http\Controllers\Api\Organization;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\Request;

class DivisionController extends Controller
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
        return Division::with(['departments', 'head', 'parent', 'children'])
            ->orderBy('name')
            ->get();
    }
// ─── Options (lightweight, for dropdowns — any authenticated user) ─────
    public function options()
    {
        return Division::orderBy('name')->get(['id', 'name']);
    }
// ─── Store ─────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $data = $request->validate([
            'code'             => ['required', 'string', 'max:50', 'unique:divisions,code'],
            'name'             => ['required', 'string'],
            'description'      => ['nullable', 'string'],
            'head_employee_id' => ['nullable', 'exists:employees,id'],
            'parent_id'        => ['nullable', 'exists:divisions,id'],
            'type'             => ['sometimes', 'in:OFFICE,DIVISION,DIRECTORATE'],
        ]);

        return Division::create($data)->load(['departments', 'head', 'parent', 'children']);
    }
// ─── Show ─────────────────────────────────────────────────────
    public function show(Division $division)
    {
        return $division->load(['departments', 'head', 'parent', 'children']);
    }
// ─── Update ─────────────────────────────────────────────────────
    public function update(Request $request, Division $division)
    {
        $data = $request->validate([
            'code'             => ['sometimes', 'string', 'max:50', 'unique:divisions,code,' . $division->id],
            'name'             => ['sometimes', 'string'],
            'description'      => ['nullable', 'string'],
            'head_employee_id' => ['nullable', 'exists:employees,id'],
            'type'             => ['sometimes', 'in:OFFICE,DIVISION,DIRECTORATE'],
            'parent_id'        => [
                'nullable',
                'exists:divisions,id',
                function ($attribute, $value, $fail) use ($division) {
                    if ($value !== null && (int) $value === (int) $division->id) {
                        $fail('A division cannot be its own parent.');
                    }
                },
            ],
        ]);

        $division->update($data);

        return $division->fresh(['departments', 'head', 'parent', 'children']);
    }
// ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(Division $division)
    {
        Division::where('parent_id', $division->id)
            ->update(['parent_id' => $division->parent_id]);

        Department::where('division_id', $division->id)
            ->update(['division_id' => null]);

        Employee::where('division_id', $division->id)
            ->update(['division_id' => null]);

        $division->delete();

        return response()->json(['message' => 'Division deleted']);
    }
}