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
        $q = PlantillaItem::query()->with('salaryGrade');

        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }

        if ($request->filled('salary_grade')) {
            $q->whereHas('salaryGrade', fn($qq) => $qq->where('salary_grade', $request->integer('salary_grade')));
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
            'status' => ['sometimes', Rule::in(['FILLED','UNFILLED','FOR_PSB','PENDING_APPOINTMENT','IMPENDING'])],
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
        return response()->json(['message' => 'Plantilla item deleted.']);
    }
}
