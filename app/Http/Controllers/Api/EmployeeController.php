<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeHierarchy;
use App\Models\EmployeeAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    private function employeeQuery(Request $request)
    {
        $query = Employee::with([
            'info',
            'parents',
            'department',
            'primaryAssignment.plantillaItem.salaryGrade',
            'primaryAssignment.stepIncrement'
        ]);
        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('first_name', 'LIKE', "%{$s}%")
                ->orWhere('middle_name', 'LIKE', "%{$s}%")
                ->orWhere('last_name', 'LIKE', "%{$s}%")
                ->orWhereRaw("
                    CONCAT_WS(' ', first_name, middle_name, last_name) LIKE ?
                ", ["%{$s}%"]);
            });
        }
        return $query->orderBy('last_name');
    }
    private function rules($employeeId = null)
    {
        return [
            'employee_number' => [
                'sometimes',
                Rule::unique('employees','employee_number')->ignore($employeeId)
            ],
            'role_position' => ['sometimes','string'],
            'department_id' => ['sometimes','exists:departments,id'],
            'first_name' => ['sometimes'],
            'middle_name' => ['nullable'],
            'last_name' => ['sometimes'],
            'suffix' => ['nullable'],
            'employment_type' => ['nullable','string'],
            'employment_status' => ['nullable','string'],
            'position_designation' => ['nullable'],
            'plantilla_item_id' => ['nullable','exists:plantilla_items,id'],
            'step_increment_id' => ['nullable','exists:step_increments,id'],
            'annual_salary' => ['nullable','numeric'],
            'monthly_salary' => ['nullable','numeric'],
            'address' => ['nullable'],
            'birthdate' => ['nullable','date'],
            'avatar_url' => ['nullable','image','mimes:jpg,jpeg,png','max:10240'],
            'parent_ids' => ['nullable','array'],
            'parent_ids.*' => ['exists:employees,id'],
        ];
    }
        public function index(Request $request)
        {
            $query = Employee::with([
                'info',
                'department',
                'parents',
                'primaryAssignment.plantillaItem.salaryGrade',
                'primaryAssignment.stepIncrement'
            ]);

            if ($request->filled('search')) {

                $s = trim($request->search);

                $query->where(function ($q) use ($s) {
                    $q->where('first_name','LIKE',"%{$s}%")
                    ->orWhere('middle_name','LIKE',"%{$s}%")
                    ->orWhere('last_name','LIKE',"%{$s}%")
                    ->orWhereRaw("
                        CONCAT_WS(' ', first_name, middle_name, last_name) LIKE ?
                    ", ["%{$s}%"]);
                });
            }

            return $this->employeeQuery($request)->paginate(10);
        }
    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        return DB::transaction(function () use ($request, $data) {
            if ($request->hasFile('avatar_url')) {
                $data['avatar_url'] = $request
                    ->file('avatar_url')
                    ->store('avatars', 'public');
            }
            $employee = Employee::create(
                collect($data)->except([
                    'parent_ids',
                    'plantilla_item_id',
                    'step_increment_id',
                    'gender',
                    'address',
                    'birthdate',
                    'contact',
                    'email'
                ])->toArray()
            );
            $employee->info()->create([
                'gender'    => $request->gender,
                'address'   => $request->address,
                'birthdate' => $request->birthdate,
                'contact'   => $request->contact,
                'email'     => $request->email
            ]);
            $this->syncAssignment($employee, $request);

            $this->syncParents($employee, $data['parent_ids'] ?? []);

            return $employee->fresh()->load([
                'info',
                'parents',
                'department',
                'primaryAssignment.plantillaItem.salaryGrade',
                'primaryAssignment.stepIncrement'
            ]);
        });
    }
    public function update(Request $request, Employee $employee)
    {
        $data = $request->validate($this->rules($employee->id));

        return DB::transaction(function () use ($request, $employee, $data) {
            if ($request->hasFile('avatar_url')) {

                if ($employee->avatar_url) {
                    Storage::disk('public')->delete($employee->avatar_url);
                }

                $data['avatar_url'] = $request
                    ->file('avatar_url')
                    ->store('avatars', 'public');
            }
            $employee->update(
                collect($data)->except([
                    'parent_ids',
                    'plantilla_item_id',
                    'step_increment_id',
                    'gender',
                    'address',
                    'birthdate',
                    'contact',
                    'email'
                ])->toArray()
            );
            $employee->info()->updateOrCreate(
                ['employee_id' => $employee->id],
                [
                    'gender'    => $request->gender,
                    'address'   => $request->address,
                    'birthdate' => $request->birthdate,
                    'contact'   => $request->contact,
                    'email'     => $request->email
                ]
            );
            $this->syncAssignment($employee, $request);
            if (array_key_exists('parent_ids', $data)) {
                $this->syncParents($employee, $data['parent_ids']);
            }
            return $employee->fresh()->load([
                'info',
                'parents',
                'department',
                'primaryAssignment.plantillaItem.salaryGrade',
                'primaryAssignment.stepIncrement'
            ]);
        });
    }
    private function syncParents(Employee $employee, array $parents)
    {
        EmployeeHierarchy::where('employee_id', $employee->id)->delete();

        foreach ($parents as $parentId) {

            EmployeeHierarchy::create([
                'employee_id' => $employee->id,
                'parent_id' => $parentId
            ]);
        }
    }
    private function syncAssignment(Employee $employee, Request $request)
    {
        if (!$request->has('plantilla_item_id')) {
            return;
        }

        EmployeeAssignment::updateOrCreate(
            [
                'employee_id' => $employee->id,
                'is_primary' => true
            ],
            [
                'plantilla_item_id' => $request->plantilla_item_id,
                'step_increment_id' => $request->step_increment_id,
                'start_date' => now()
            ]
        );
    }
    public function destroy(Employee $employee)
    {
        EmployeeHierarchy::where('employee_id', $employee->id)
            ->orWhere('parent_id', $employee->id)
            ->delete();

        $employee->delete();

        return response()->json([
            'message' => 'Deleted successfully'
        ]);
    }
}