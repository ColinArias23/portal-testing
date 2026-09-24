<?php

namespace App\Http\Controllers\Api\Positions;

use App\Http\Controllers\Controller;
use App\Models\PlantillaPosition;
use App\Services\Positions\PlantillaPositionService;
use Illuminate\Http\Request;

class PlantillaPositionController extends Controller
{
    public function __construct(
        private readonly PlantillaPositionService $positions,
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:plantilla_items.view')->only(['index', 'show', 'steps', 'summary', 'salaryConfig', 'availableItems']);
        $this->middleware('permission:plantilla_items.manage')->only(['store', 'addSlots', 'update', 'destroy', 'bulkDelete', 'restore', 'bulkRestore', 'approve']);
    }

    // ─── Index ─────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        return response()->json($this->positions->index($request));
    }

    // ─── Show ──────────────────────────────────────────────────────────────
    public function show(PlantillaPosition $plantillaPosition)
    {
        return response()->json($this->positions->show($plantillaPosition));
    }

    // ─── Steps ─────────────────────────────────────────────────────────────
    public function steps(PlantillaPosition $plantillaPosition)
    {
        return response()->json($this->positions->steps($plantillaPosition));
    }

    // ─── Store ─────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $data = $request->validate([
            'base_item_number'      => ['required', 'string', 'max:20'],
            'title'                 => ['required', 'string', 'max:255'],
            'description'           => ['nullable', 'string'],
            'slots'                 => ['required', 'integer', 'min:1', 'max:99'],
            'display_department_id' => ['nullable', 'integer', 'exists:departments,id', 'prohibits:display_division_id'],
            'display_division_id'   => ['nullable', 'integer', 'exists:divisions,id', 'prohibits:display_department_id'],
            'role'                  => ['nullable', 'string', 'max:100'],
            'cti'                   => ['nullable', 'in:YES,NOT_SPECIFIED'],
            'salary_grade_id'       => ['nullable', 'integer', 'exists:salary_grades,id'],
            'step_increment_id'     => ['nullable', 'integer', 'exists:step_increments,id'],
        ]);

        return response()->json($this->positions->store($data, $request), 201);
    }

    // ─── AddSlots ─────────────────────────────────────────────────────────
    public function addSlots(Request $request)
    {
        $data = $request->validate([
            'base_item_number' => ['required', 'string', 'exists:plantilla_positions'],
            'count'            => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $result = $this->positions->addSlots($data, $request);

        return response()->json($result['body'], $result['status']);
    }

    // ─── Update ────────────────────────────────────────────────────────────
    public function update(Request $request, PlantillaPosition $plantillaPosition)
    {
        $data = $request->validate([
            'position_title'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'role'                  => ['sometimes', 'nullable', 'string', 'max:100'],
            'cti'                   => ['sometimes', 'nullable', 'in:YES,NOT_SPECIFIED'],
            'display_department_id' => ['sometimes', 'nullable', 'integer', 'exists:departments,id', 'prohibits:display_division_id'],
            'display_division_id'   => ['sometimes', 'nullable', 'integer', 'exists:divisions,id', 'prohibits:display_department_id'],
            'salary_grade_id'       => ['sometimes', 'nullable', 'integer', 'exists:salary_grades,id'],
            'step_increment_id'     => ['sometimes', 'nullable', 'integer', 'exists:step_increments,id'],
            'date_of_assumption'    => ['sometimes', 'nullable', 'date'],
            'vacancy_notes'         => ['sometimes', 'nullable', 'string'],
            'requires_approval'     => ['sometimes', 'boolean'],
        ]);

        return response()->json($this->positions->update($request, $plantillaPosition, $data));
    }

    // ─── Destroy ───────────────────────────────────────────────────────────
    public function destroy(Request $request, PlantillaPosition $plantillaPosition)
    {
        $result = $this->positions->destroy($request, $plantillaPosition);

        return response()->json($result['body'], $result['status']);
    }

    // ─── BulkDelete ───────────────────────────────────────────────────────
    public function bulkDelete(Request $request)
    {
        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:plantilla_positions,id'],
        ]);

        $result = $this->positions->bulkDelete($data['ids'], $request);

        return response()->json($result['body'], $result['status']);
    }

    // ─── Restore ───────────────────────────────────────────────────────────
    public function restore(Request $request, $id)
    {
        $result = $this->positions->restore((int) $id, $request);

        return response()->json($result['body'], $result['status']);
    }

    // ─── BulkRestore ──────────────────────────────────────────────────────
    public function bulkRestore(Request $request)
    {
        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $result = $this->positions->bulkRestore($data['ids'], $request);

        return response()->json($result['body'], $result['status']);
    }

    // ─── Approve ───────────────────────────────────────────────────────────
    public function approve(Request $request, PlantillaPosition $plantillaPosition)
    {
        $result = $this->positions->approve($plantillaPosition, $request);

        return response()->json($result['body'], $result['status']);
    }

    // ─── Summary ───────────────────────────────────────────────────────────
    public function summary()
    {
        return response()->json($this->positions->summary());
    }

    // ─── SalaryConfig ─────────────────────────────────────────────────────
    public function salaryConfig(PlantillaPosition $plantillaPosition)
    {
        return response()->json($this->positions->salaryConfig($plantillaPosition));
    }

    // ─── AvailableItems ───────────────────────────────────────────────────
    public function availableItems()
    {
        return response()->json($this->positions->availableItems());
    }
}
