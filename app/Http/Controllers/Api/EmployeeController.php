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
            'division',
            'primaryAssignment.plantillaItem.salaryGrade',
            'primaryAssignment.stepIncrement',
        ]);

        // ── Full-text search ──────────────────────────────────────────────
        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('first_name', 'LIKE', "%{$s}%")
                    ->orWhere('middle_name', 'LIKE', "%{$s}%")
                    ->orWhere('last_name', 'LIKE', "%{$s}%")
                    ->orWhereRaw("CONCAT_WS(' ', first_name, middle_name, last_name) LIKE ?", ["%{$s}%"])
                    ->orWhereRaw("JSON_SEARCH(role_position, 'one', ?) IS NOT NULL", ["%{$s}%"]);
            });
        }

        // ── Division filter ───────────────────────────────────────────────
        if ($request->filled('division_id')) {
            $query->where('division_id', $request->division_id);
        }

        // ── Department filter ─────────────────────────────────────────────
        if ($request->filled('department_id')) {
            $deptId = (int) $request->department_id;
            $query->where(function ($q) use ($deptId) {
                $q->whereRaw('JSON_CONTAINS(department_ids, ?)', [json_encode($deptId)])
                  ->orWhereRaw('JSON_CONTAINS(department_ids, ?)', [json_encode((string) $deptId)]);
            });
        }

        return $query->orderBy('last_name');
    }

    private function rules($employeeId = null)
    {
        return [
            'employee_number'        => ['sometimes', Rule::unique('employees', 'employee_number')->ignore($employeeId)],
            'role_position'          => ['nullable', 'array'],
            'role_position.*'        => ['string'],
            'department_ids'         => ['nullable', 'array'],
            'department_ids.*'       => ['exists:departments,id'],
            'division_id'            => ['nullable', 'exists:divisions,id'],
            'first_name'             => ['sometimes'],
            'middle_name'            => ['nullable'],
            'last_name'              => ['sometimes'],
            'suffix'                 => ['nullable'],
            'title'                  => ['nullable', 'array'],
            'title.*'                => ['string'],
            'employment_type'        => ['nullable', 'in:Plantilla,Contract of Service,Consultant'],
            'employment_status'      => ['nullable', 'string'],
            'position_designation'   => ['nullable', 'array'],
            'position_designation.*' => ['string'],
            'plantilla_item_id'      => ['nullable', 'exists:plantilla_items,id'],
            'step_increment_id'      => ['nullable', 'exists:step_increments,id'],
            'annual_salary'          => ['nullable', 'numeric'],
            'monthly_salary'         => ['nullable', 'numeric'],
            'address'                => ['nullable'],
            'birthdate'              => ['nullable', 'date'],
            'avatar_url'             => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:10240'],
            'parent_ids'             => ['nullable', 'array'],
            'parent_ids.*'           => ['exists:employees,id'],
        ];
    }

    public function index(Request $request)
    {
        return $this->employeeQuery($request)->paginate($request->per_page ?? 10);
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

            $data['department_ids']       = array_map('intval', $request->department_ids ?? []);
            $data['role_position']        = $request->role_position         ?? [];
            $data['title']                = $request->title                 ?? [];
            $data['position_designation'] = $request->position_designation  ?? [];

            $employee = Employee::create(
                collect($data)->except([
                    'parent_ids',
                    'plantilla_item_id',
                    'step_increment_id',
                    'gender',
                    'address',
                    'birthdate',
                    'contact',
                    'email',
                ])->toArray()
            );

            $employee->info()->create([
                'gender'    => $request->gender,
                'address'   => $request->address,
                'birthdate' => $request->birthdate,
                'contact'   => $request->contact,
                'email'     => $request->email,
            ]);

            $this->syncAssignment($employee, $request);
            $this->syncParents($employee, $data['parent_ids'] ?? []);

            return $employee->fresh()->load([
                'info',
                'parents',
                'division',
                'primaryAssignment.plantillaItem.salaryGrade',
                'primaryAssignment.stepIncrement',
            ]);
        });
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $request->validate($this->rules($employee->id));

        return DB::transaction(function () use ($request, $employee, $data) {
            if ($request->hasFile('avatar_url')) {
                $rawAvatar = DB::table('employees')
                    ->where('id', $employee->id)
                    ->value('avatar_url');

                if ($rawAvatar) {
                    Storage::disk('public')->delete($rawAvatar);
                }

                $data['avatar_url'] = $request
                    ->file('avatar_url')
                    ->store('avatars', 'public');
            }

            $data['department_ids']       = array_map('intval', $request->department_ids ?? []);
            $data['role_position']        = $request->role_position         ?? [];
            $data['title']                = $request->title                 ?? [];
            $data['position_designation'] = $request->position_designation  ?? [];

            $employee->update(
                collect($data)->except([
                    'parent_ids',
                    'plantilla_item_id',
                    'step_increment_id',
                    'gender',
                    'address',
                    'birthdate',
                    'contact',
                    'email',
                ])->toArray()
            );

            $employee->info()->updateOrCreate(
                ['employee_id' => $employee->id],
                [
                    'gender'    => $request->gender,
                    'address'   => $request->address,
                    'birthdate' => $request->birthdate,
                    'contact'   => $request->contact,
                    'email'     => $request->email,
                ]
            );

            $this->syncAssignment($employee, $request);

            // ── Sync parents ──────────────────────────────────────────────
            // The frontend sends "_sync_parents=1" on every update so we know
            // this request intends to sync parents — even when clearing all.
            // Using a dedicated flag avoids issues with Laravel's
            // ConvertEmptyStringsToNull middleware swallowing empty parent_ids.
            if ($request->input('_sync_parents') === '1') {
                $parentIds = array_filter(
                    array_map('intval', (array) ($request->input('parent_ids') ?? []))
                );
                $this->syncParents($employee, $parentIds);
            }

            return $employee->fresh()->load([
                'info',
                'parents',
                'division',
                'primaryAssignment.plantillaItem.salaryGrade',
                'primaryAssignment.stepIncrement',
            ]);
        });
    }

    private function syncParents(Employee $employee, array $parents)
    {
        EmployeeHierarchy::where('employee_id', $employee->id)->delete();

        foreach ($parents as $parentId) {
            if (!$parentId) continue;
            EmployeeHierarchy::create([
                'employee_id' => $employee->id,
                'parent_id'   => $parentId,
            ]);
        }
    }

    private function syncAssignment(Employee $employee, Request $request)
    {
        if (!$request->has('plantilla_item_id')) {
            return;
        }

        $newPlantillaId = $request->plantilla_item_id ?: null;
        $newStepId      = $request->step_increment_id ?: null;

        if (!$newPlantillaId) {
            return;
        }

        $occupied = EmployeeAssignment::where('plantilla_item_id', $newPlantillaId)
            ->where('employee_id', '!=', $employee->id)
            ->whereNull('end_date')
            ->exists();

        if ($occupied) {
            throw new \Exception("Plantilla item is already occupied by another employee.");
        }

        EmployeeAssignment::updateOrCreate(
            [
                'employee_id' => $employee->id,
                'is_primary'  => true,
            ],
            [
                'plantilla_item_id' => $newPlantillaId,
                'step_increment_id' => $newStepId,
                'start_date'        => now(),
                'end_date'          => null,
            ]
        );
    }

    public function destroy(Employee $employee)
    {
        EmployeeHierarchy::where('employee_id', $employee->id)
            ->orWhere('parent_id', $employee->id)
            ->delete();

        $employee->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }
}