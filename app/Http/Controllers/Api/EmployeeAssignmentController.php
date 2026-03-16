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
            'employee_id'      => ['required', 'exists:employees,id'],
            'plantilla_item_id' => ['required', 'exists:plantilla_items,id'],
            'is_primary'       => ['required', 'boolean'],
            'start_date'       => ['required', 'date'],
        ]);

        return DB::transaction(function () use ($data) {

            // Prevent duplicate active primary assignment (race-condition guard)
            $alreadyPrimary = EmployeeAssignment::where('employee_id', $data['employee_id'])
                ->where('is_primary', true)
                ->whereNull('end_date')
                ->lockForUpdate()
                ->exists();

            if ($data['is_primary'] && $alreadyPrimary) {
                // Close the existing primary before opening a new one
                EmployeeAssignment::where('employee_id', $data['employee_id'])
                    ->where('is_primary', true)
                    ->whereNull('end_date')
                    ->update([
                        'end_date'   => now(),
                        'is_primary' => false,
                    ]);
            }

            return EmployeeAssignment::create([
                'employee_id'      => $data['employee_id'],
                'plantilla_item_id' => $data['plantilla_item_id'],
                'is_primary'       => $data['is_primary'],
                'start_date'       => $data['start_date'],
                'end_date'         => null,   // explicitly open-ended
            ]);
        });
    }

    public function end(EmployeeAssignment $assignment)
    {
        $assignment->update([
            'end_date' => now(),
        ]);

        return response()->json([
            'message' => 'Assignment closed',
        ]);
    }
}