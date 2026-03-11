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
    /*
    |--------------------------------------------------------------------------
    | LIST PLANTILLA ITEMS
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $employeeId = $request->input('employee_id');

        $q = PlantillaItem::with([
            'salaryGrade',
            'stepIncrement'
        ]);

        $currentPlantillaId = null;

        if ($employeeId) {
            $currentPlantillaId = EmployeeAssignment::where('employee_id', $employeeId)
                ->whereNull('end_date')
                ->value('plantilla_item_id');
        }

        $q->where(function ($query) use ($currentPlantillaId) {

            // vacant plantilla only
            $query->vacant();

            // include current employee plantilla when editing
            if ($currentPlantillaId) {
                $query->orWhere('id', $currentPlantillaId);
            }

        });

        return $q
            ->orderByRaw('CAST(item_number AS UNSIGNED)')
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | GET STEPS BASED ON SALARY GRADE
    |--------------------------------------------------------------------------
    */

    public function steps($id)
    {
        $plantilla = PlantillaItem::findOrFail($id);

        return StepIncrement::where('salary_grade_id', $plantilla->salary_grade_id)
            ->orderBy('step')
            ->get([
                'id',
                'step',
                'monthly_salary',
                'annual_salary'
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE PLANTILLA ITEM
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $data = $request->validate([
            'item_number' => ['required','string','max:50','unique:plantilla_items,item_number'],
            'status' => ['required', Rule::in(['FILLED','VACANT'])],
            'salary_grade_id' => ['required','exists:salary_grades,id'],
            'step_increment_id' => ['required','exists:step_increments,id'],
            'title' => ['required','string','max:255'],
            'description' => ['nullable','string'],
        ]);

        return PlantillaItem::create($data)
            ->load([
                'salaryGrade',
                'stepIncrement'
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW SINGLE PLANTILLA ITEM
    |--------------------------------------------------------------------------
    */

    public function show(PlantillaItem $plantillaItem)
    {
        return $plantillaItem->load([
            'salaryGrade',
            'stepIncrement'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE PLANTILLA ITEM
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, PlantillaItem $plantillaItem)
    {
        $data = $request->validate([
            'item_number' => [
                'sometimes',
                'string',
                'max:50',
                'unique:plantilla_items,item_number,' . $plantillaItem->id
            ],
            'status' => ['sometimes', Rule::in(['FILLED','VACANT'])],
            'salary_grade_id' => ['sometimes','exists:salary_grades,id'],
            'step_increment_id' => ['sometimes','exists:step_increments,id'],
            'title' => ['sometimes','string','max:255'],
            'description' => ['nullable','string'],
        ]);

        $plantillaItem->update($data);

        return $plantillaItem->fresh([
            'salaryGrade',
            'stepIncrement'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE PLANTILLA ITEM
    |--------------------------------------------------------------------------
    */

    public function destroy(PlantillaItem $plantillaItem)
    {
        $plantillaItem->delete();

        return response()->json([
            'message' => 'Plantilla item deleted.'
        ]);
    }
}