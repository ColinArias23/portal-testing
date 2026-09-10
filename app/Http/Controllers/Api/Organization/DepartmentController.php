<?php

namespace App\Http\Controllers\Api\Organization;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Services\Organization\DepartmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function __construct(
        private readonly DepartmentService $departments,
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:departments.view')->only(['index', 'show']);
        $this->middleware('permission:departments.manage')->only(['store', 'update', 'destroy']);
    }

    // ─── Index ─────────────────────────────────────────────────────
    public function index()
    {
        return $this->departments->index();
    }

    // ─── Options (lightweight, for dropdowns — any authenticated user) ─────
    public function options()
    {
        return $this->departments->options();
    }

    // ─── Types (valid 'type' enum values, for the Add/Edit dropdown) ───────
    public function types()
    {
        return collect(Department::TYPES)
            ->map(fn (string $type) => ['value' => $type, 'label' => $type])
            ->values();
    }

    // ─── Store ─────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $data = $request->validate([
            'division_id'         => ['required', 'exists:divisions,id'],
            'parent_id'           => ['nullable', 'exists:departments,id'],
            'parent_division_id'  => ['nullable', 'exists:divisions,id'],
            'type'                => ['required', Rule::in(Department::TYPES)],
            'code'                => ['required', 'string', 'max:50', 'unique:departments,code'],
            'name'                => ['required', 'string', 'max:255'],
            'description'         => ['nullable', 'string'],
            'head_employee_id'    => ['nullable', 'exists:employees,id'],
        ]);

        return $this->departments->create($data);
    }

    // ─── Show ─────────────────────────────────────────────────────
    public function show(Department $department)
    {
        return $this->departments->show($department);
    }

    // ─── Update ─────────────────────────────────────────────────────
    public function update(Request $request, Department $department)
    {
        $data = $request->validate([
            'division_id'         => ['sometimes', 'exists:divisions,id'],
            'parent_id'           => ['nullable', 'exists:departments,id'],
            'parent_division_id'  => ['nullable', 'exists:divisions,id'],
            'type'                => ['required', Rule::in(Department::TYPES)],
            'code'                => ['sometimes', 'string', 'max:50', 'unique:departments,code,' . $department->id],
            'name'                => ['sometimes', 'string', 'max:255'],
            'description'         => ['nullable', 'string'],
            'head_employee_id'    => ['nullable', 'exists:employees,id'],
            'employee_ids'        => ['nullable', 'array'],
            'employee_ids.*'      => ['exists:employees,id'],
        ]);

        return $this->departments->update($department, $data, $request->has('employee_ids'));
    }

    // ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(Department $department)
    {
        $this->departments->delete($department);

        return response()->json(['message' => 'Department deleted.']);
    }
}