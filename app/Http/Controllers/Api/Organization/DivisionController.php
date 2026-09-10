<?php

namespace App\Http\Controllers\Api\Organization;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Services\Organization\DivisionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DivisionController extends Controller
{
    public function __construct(
        private readonly DivisionService $divisions,
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:departments.view')->only(['index', 'show']);
        $this->middleware('permission:departments.manage')->only(['store', 'update', 'destroy']);
    }

    // ─── Index ─────────────────────────────────────────────────────
    public function index()
    {
        return $this->divisions->index();
    }

    // ─── Options (lightweight, for dropdowns — any authenticated user) ─────
    public function options()
    {
        return $this->divisions->options();
    }

    // ─── Types (valid 'type' enum values, for the Add/Edit dropdown) ───────
    public function types()
    {
        return collect(Division::TYPES)
            ->map(fn (string $type) => ['value' => $type, 'label' => $type])
            ->values();
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
            'type'             => ['sometimes', Rule::in(Division::TYPES)],
        ]);

        return $this->divisions->create($data);
    }

    // ─── Show ─────────────────────────────────────────────────────
    public function show(Division $division)
    {
        return $this->divisions->show($division);
    }

    // ─── Update ─────────────────────────────────────────────────────
    public function update(Request $request, Division $division)
    {
        $data = $request->validate([
            'code'             => ['sometimes', 'string', 'max:50', 'unique:divisions,code,' . $division->id],
            'name'             => ['sometimes', 'string'],
            'description'      => ['nullable', 'string'],
            'head_employee_id' => ['nullable', 'exists:employees,id'],
            'type'             => ['sometimes', Rule::in(Division::TYPES)],
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

        return $this->divisions->update($division, $data);
    }

    // ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(Division $division)
    {
        $this->divisions->delete($division);

        return response()->json(['message' => 'Division deleted']);
    }
}