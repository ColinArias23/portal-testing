<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeAssignmentController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required','exists:employees,id'],
            'plantilla_item_id' => ['required','exists:plantilla_items,id'],
            'is_primary' => ['required','boolean'],
            'start_date' => ['required','date'],
        ]);

        return DB::transaction(function () use ($data) {

            if ($data['is_primary']) {

                EmployeeAssignment::where('employee_id', $data['employee_id'])
                    ->whereNull('end_date')
                    ->update([
                        'end_date' => now(),
                        'is_primary' => false
                    ]);
            }

            return EmployeeAssignment::create($data);
        });
    }

    public function end(EmployeeAssignment $assignment)
    {
        $assignment->update([
            'end_date' => now()
        ]);

        return response()->json([
            'message' => 'Assignment closed'
        ]);
    }
}