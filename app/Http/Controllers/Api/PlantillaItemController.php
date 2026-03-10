<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlantillaItem;
use App\Models\StepIncrement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlantillaItemController extends Controller
{
    public function index(Request $request)
    {
        $employeeId = $request->input('employee_id');

        $q = PlantillaItem::query()
            ->with('salaryGrade');

        if ($employeeId) {

            // current plantilla
            $currentPlantillaId = \App\Models\EmployeeAssignment::where('employee_id', $employeeId)
                ->whereNull('end_date')
                ->value('plantilla_item_id');

            // return vacant OR current plantilla
            $q->where(function ($query) use ($currentPlantillaId) {

                $query->whereDoesntHave('activeAssignments');

                if ($currentPlantillaId) {
                    $query->orWhere('id', $currentPlantillaId);
                }

            });

        } else {

            // create employee → vacant only
            $q->whereDoesntHave('activeAssignments');

        }

        return $q->orderBy('item_number')->get();
    }

    public function steps($id)
    {
        $plantilla = PlantillaItem::findOrFail($id);

        return StepIncrement::where('salary_grade_id', $plantilla->salary_grade_id)
            ->orderBy('step')
            ->get([
                'id',
                'step',
                'description',
                'increment_amount'
            ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'item_number' => ['required','string','max:50','unique:plantilla_items,item_number'],
            'status' => ['required', Rule::in(['FILLED','VACANT'])],
            'salary_grade_id' => ['required','exists:salary_grades,id'],
            'title' => ['required','string','max:255'],
            'description' => ['nullable','string'],
        ]);

        return PlantillaItem::create($data)->load('salaryGrade');
    }

    public function show(PlantillaItem $plantillaItem)
    {
        return $plantillaItem->load('salaryGrade');
    }

    public function update(Request $request, PlantillaItem $plantillaItem)
    {
        $data = $request->validate([
            'item_number' => ['sometimes','string','max:50','unique:plantilla_items,item_number,'.$plantillaItem->id],
            'status' => ['sometimes', Rule::in(['FILLED','VACANT'])],
            'salary_grade_id' => ['sometimes','exists:salary_grades,id'],
            'title' => ['sometimes','string','max:255'],
            'description' => ['nullable','string'],
        ]);

        $plantillaItem->update($data);

        return $plantillaItem->fresh('salaryGrade');
    }

    public function destroy(PlantillaItem $plantillaItem)
    {
        $plantillaItem->delete();

        return response()->json([
            'message' => 'Plantilla item deleted.'
        ]);
    }
}