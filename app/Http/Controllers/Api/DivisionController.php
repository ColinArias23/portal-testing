<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Division;
use Illuminate\Http\Request;

class DivisionController extends Controller
{
    public function index()
    {
        return Division::with(['departments','head'])
            ->orderBy('name')
            ->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required','string','max:50','unique:divisions,code'],
            'name' => ['required','string'],
            'description' => ['nullable','string'],
            'head_employee_id' => ['nullable','exists:employees,id'],
        ]);

        return Division::create($data);
    }

    public function show(Division $division)
    {
        return $division->load(['departments','head']);
    }

    public function update(Request $request, Division $division)
    {
        $data = $request->validate([
            'code' => ['sometimes','string','max:50','unique:divisions,code,'.$division->id],
            'name' => ['sometimes','string'],
            'description' => ['nullable','string'],
            'head_employee_id' => ['nullable','exists:employees,id'],
        ]);

        $division->update($data);

        return $division->fresh(['departments','head']);
    }

    public function destroy(Division $division)
    {
        $division->delete();
        return response()->json(['message'=>'Division deleted']);
    }
}