<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlantillaItem;
use App\Models\StepIncrement;
use App\Models\EmployeeAssignment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlantillaItemController extends Controller
{
    public function index(Request $request)
    {
        $employeeId = $request->input('employee_id');

        $q = PlantillaItem::with([
            'salaryGrade',
            'stepIncrement',
            'assignments' => function ($query) {
                $query->whereNull('end_date')
                      ->where('is_primary', true)
                      ->with('employee:id,first_name,last_name,prefix,suffix');
            },
        ]);

        $currentPlantillaId = null;

        if ($employeeId) {
            $currentPlantillaId = EmployeeAssignment::where('employee_id', $employeeId)
                ->whereNull('end_date')
                ->value('plantilla_item_id');
        }

        $items = $q->orderByRaw('CAST(item_number AS UNSIGNED)')->get();

        return $items->map(function ($item) use ($currentPlantillaId) {
            $item->is_current_employee = $currentPlantillaId !== null && $item->id === $currentPlantillaId;
            return $item;
        });
    }

    public function steps($id)
    {
        $plantilla = PlantillaItem::findOrFail($id);

        return StepIncrement::where('salary_grade_id', $plantilla->salary_grade_id)
            ->orderBy('step')
            ->get(['id', 'step', 'monthly_salary', 'annual_salary']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'item_number'      => ['required', 'string', 'max:50', 'unique:plantilla_items,item_number'],
            'salary_grade_id'  => ['required', 'exists:salary_grades,id'],
            'step_increment_id'=> ['required', 'exists:step_increments,id'],
            'title'            => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string'],
        ]);

        return PlantillaItem::create($data)->load(['salaryGrade', 'stepIncrement']);
    }

    public function show(PlantillaItem $plantillaItem)
    {
        return $plantillaItem->load(['salaryGrade', 'stepIncrement']);
    }

    public function update(Request $request, PlantillaItem $plantillaItem)
    {
        $data = $request->validate([
            'item_number'      => ['sometimes', 'string', 'max:50', 'unique:plantilla_items,item_number,' . $plantillaItem->id],
            'status'           => ['sometimes', Rule::in(['FILLED', 'UNFILLED', 'VACANT'])],
            'salary_grade_id'  => ['sometimes', 'exists:salary_grades,id'],
            'step_increment_id'=> ['sometimes', 'exists:step_increments,id'],
            'title'            => ['sometimes', 'string', 'max:255'],
            'description'      => ['nullable', 'string'],
        ]);

        $plantillaItem->update($data);

        return $plantillaItem->fresh(['salaryGrade', 'stepIncrement']);
    }

    public function destroy(PlantillaItem $plantillaItem)
    {
        $plantillaItem->delete();

        return response()->json(['message' => 'Plantilla item deleted.']);
    }
}