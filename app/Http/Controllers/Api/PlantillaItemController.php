<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlantillaItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlantillaItemController extends Controller
{
    public function index(Request $request)
    {
        $q = PlantillaItem::query()->with(['salaryGrade', 'department', 'division']);

        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }

        if ($request->filled('salary_grade')) {
            $q->whereHas('salaryGrade', fn($qq) => $qq->where('salary_grade', $request->integer('salary_grade')));
        }

        if ($request->filled('department_id')) {
            $q->where('department_id', $request->integer('department_id'));
        }

        if ($request->filled('division_id')) {
            $q->where('division_id', $request->integer('division_id'));
        }

        return $q->orderBy('item_number')->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'item_number' => ['required','string','max:50','unique:plantilla_items,item_number'],
            'status' => ['required', Rule::in(['FILLED','UNFILLED','FOR_PSB','PENDING_APPOINTMENT','IMPENDING'])],
            'salary_grade_id' => ['required','exists:salary_grades,id'],
            'title' => ['required','string','max:255'],
            'description' => ['nullable','string'],

            // NEW
            'department_id' => ['nullable','exists:departments,id'],
            'division_id' => ['nullable','exists:divisions,id'],
        ]);

        // optional: ensure division belongs to department if both provided
        if (!empty($data['department_id']) && !empty($data['division_id'])) {
            $divDept = \App\Models\Division::where('id', $data['division_id'])->value('department_id');
            if ((int)$divDept !== (int)$data['department_id']) {
                return response()->json(['message' => 'Division does not belong to the selected Department.'], 422);
            }
        }

        return PlantillaItem::create($data)->load(['salaryGrade','department','division']);
    }

    public function show(PlantillaItem $plantillaItem)
    {
        return $plantillaItem->load(['salaryGrade','department','division']);
    }

    public function update(Request $request, PlantillaItem $plantillaItem)
    {
        $data = $request->validate([
            'item_number' => ['sometimes','string','max:50','unique:plantilla_items,item_number,'.$plantillaItem->id],
            'status' => ['sometimes', Rule::in(['FILLED','UNFILLED','FOR_PSB','PENDING_APPOINTMENT','IMPENDING'])],
            'salary_grade_id' => ['sometimes','exists:salary_grades,id'],
            'title' => ['sometimes','string','max:255'],
            'description' => ['nullable','string'],

            // NEW
            'department_id' => ['nullable','exists:departments,id'],
            'division_id' => ['nullable','exists:divisions,id'],
        ]);

        if (array_key_exists('department_id', $data) || array_key_exists('division_id', $data)) {
            $departmentId = $data['department_id'] ?? $plantillaItem->department_id;
            $divisionId   = $data['division_id'] ?? $plantillaItem->division_id;

            if ($departmentId && $divisionId) {
                $divDept = \App\Models\Division::where('id', $divisionId)->value('department_id');
                if ((int)$divDept !== (int)$departmentId) {
                    return response()->json(['message' => 'Division does not belong to the selected Department.'], 422);
                }
            }
        }

        $plantillaItem->update($data);

        return $plantillaItem->fresh(['salaryGrade','department','division']);
    }

    public function destroy(PlantillaItem $plantillaItem)
    {
        $plantillaItem->delete();
        return response()->json(['message' => 'Plantilla item deleted.']);
    }
}