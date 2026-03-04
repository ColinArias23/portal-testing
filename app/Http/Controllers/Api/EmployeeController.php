<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeHierarchy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | BASE QUERY (Shared by index + all)
    |--------------------------------------------------------------------------
    */
    private function employeeQuery(Request $request)
    {
        $query = Employee::with([
            'info',
            'parents',
            'primaryAssignment.plantillaItem.department',
            'primaryAssignment.plantillaItem.division'
        ]);

        if ($request->filled('search')) {

            $s = strtolower(trim($request->input('search')));

            $query->where(function ($q) use ($s) {

                $q->whereRaw("LOWER(first_name) LIKE ?", ["%{$s}%"])
                  ->orWhereRaw("LOWER(middle_name) LIKE ?", ["%{$s}%"])
                  ->orWhereRaw("LOWER(last_name) LIKE ?", ["%{$s}%"])
                  ->orWhereRaw("
                      LOWER(CONCAT(
                          COALESCE(first_name,''),' ',
                          COALESCE(middle_name,''),' ',
                          COALESCE(last_name,'')
                      )) LIKE ?
                  ", ["%{$s}%"]);
            });
        }

        return $query->orderBy('last_name');
    }

    /*
    |--------------------------------------------------------------------------
    | PAGINATED EMPLOYEES
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        $query = $this->employeeQuery($request);

        return $query->paginate(20);
    }

    /*
    |--------------------------------------------------------------------------
    | ALL EMPLOYEES
    |--------------------------------------------------------------------------
    */
    public function all(Request $request)
    {
        $query = $this->employeeQuery($request);

        return $query->get();
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_number' => ['required','unique:employees,employee_number'],
            'role_position' => ['required','string'],
            'first_name' => ['required'],
            'last_name' => ['required'],
            'avatar_url' => ['nullable','image','mimes:jpg,jpeg,png','max:10240'],
            'parent_ids' => ['nullable','array'],
            'parent_ids.*' => ['exists:employees,id'],
        ]);

        return DB::transaction(function () use ($request, &$data) {

            if ($request->hasFile('avatar_url')) {
                $path = $request->file('avatar_url')->store('avatars', 'public');
                $data['avatar_url'] = $path;
            }

            $employee = Employee::create(
                collect($data)->except('parent_ids')->toArray()
            );

            if (!empty($data['parent_ids'])) {
                foreach ($data['parent_ids'] as $parentId) {
                    EmployeeHierarchy::create([
                        'employee_id' => $employee->id,
                        'parent_id' => $parentId,
                    ]);
                }
            }

            return $employee->load([
                'info',
                'hierarchy.parent',
                'parentHierarchy.employee'
            ]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'employee_number' => [
                'sometimes',
                Rule::unique('employees','employee_number')->ignore($employee->id)
            ],
            'role_position' => ['sometimes','string'],
            'first_name' => ['sometimes'],
            'last_name' => ['sometimes'],
            'avatar_url' => ['nullable','image','mimes:jpg,jpeg,png','max:10240'],
            'parent_ids' => ['nullable','array'],
            'parent_ids.*' => ['exists:employees,id'],
        ]);

        return DB::transaction(function () use ($employee, $request, &$data) {

            if ($request->hasFile('avatar_url')) {

                if ($employee->avatar_url) {
                    Storage::disk('public')->delete($employee->avatar_url);
                }

                $path = $request->file('avatar_url')->store('avatars', 'public');
                $data['avatar_url'] = $path;
            }

            $employee->update(
                collect($data)->except('parent_ids')->toArray()
            );

            if (array_key_exists('parent_ids', $data)) {

                EmployeeHierarchy::where('employee_id', $employee->id)
                    ->delete();

                foreach ($data['parent_ids'] ?? [] as $parentId) {
                    EmployeeHierarchy::create([
                        'employee_id' => $employee->id,
                        'parent_id' => $parentId,
                    ]);
                }
            }

            return $employee->fresh()->load([
                'info',
                'hierarchy.parent',
                'parentHierarchy.employee'
            ]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */
    public function destroy(Employee $employee)
    {
        EmployeeHierarchy::where('employee_id', $employee->id)
            ->orWhere('parent_id', $employee->id)
            ->delete();

        $employee->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }
}