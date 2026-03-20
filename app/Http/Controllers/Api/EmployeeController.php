<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeHierarchy;
use App\Models\EmployeeAssignment;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmployeeController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | AVATAR URL HELPER
    |--------------------------------------------------------------------------
    */

    private function resolveAvatarUrl(?string $path): ?string
    {
        if (!$path) return null;
        $filePath = storage_path('app/public/' . $path);
        if (!file_exists($filePath)) return null;
        return rtrim(config('app.url'), '/') . '/storage/' . $path;
    }

    /*
    |--------------------------------------------------------------------------
    | QUERY BUILDER
    |--------------------------------------------------------------------------
    */

    private function employeeQuery(Request $request)
    {
        $query = Employee::with([
            'info',
            'parents',
            'division',
            'primaryAssignment.plantillaItem.salaryGrade',
            'primaryAssignment.stepIncrement',
        ]);

        if ($request->filled('division_id')) {
            $query->where('division_id', $request->division_id);
        }

        if ($request->filled('department_id')) {
            $deptId = (int) $request->department_id;
            $query->whereRaw('JSON_CONTAINS(department_ids, ?)', [json_encode($deptId)]);
        }

        return $query->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATION RULES
    |--------------------------------------------------------------------------
    */

    private function rules(): array
    {
        return [
            'employee_number'        => ['sometimes'],
            'role_position'          => ['nullable', 'array'],
            'role_position.*'        => ['string'],
            'department_ids'         => ['nullable', 'array'],
            'department_ids.*'       => ['exists:departments,id'],
            'division_id'            => ['nullable', 'exists:divisions,id'],
            'prefix'                 => ['nullable'],
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

    /*
    |--------------------------------------------------------------------------
    | UNIQUE EMPLOYEE NUMBER CHECK
    |--------------------------------------------------------------------------
    */

    private function assertUniqueEmployeeNumber(?string $number, $ignoreId = null): void
    {
        if (!$number) return;

        $number = strtoupper($number);

        $exists = Employee::select('id', 'employee_number')
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->get()
            ->contains(fn($emp) => strtoupper($emp->employee_number) === $number);

        if ($exists) {
            abort(422, json_encode([
                'employee_number' => ['The employee number has already been taken.'],
            ]));
        }
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $query = $this->employeeQuery($request);

        if ($request->filled('search')) {
            $s        = strtolower(trim($request->search));
            $perPage  = (int) ($request->per_page ?? 10);
            $page     = (int) ($request->page ?? 1);
            $allDepts = Department::all(['id', 'name'])->keyBy('id');

            $filtered = $query->get()->filter(function ($emp) use ($s) {
                return str_contains(strtolower($emp->full_name), $s)
                    || collect($emp->role_position ?? [])
                        ->contains(fn($r) => str_contains(strtolower($r), $s));
            })->values();

            $filtered->each(function ($emp) use ($allDepts) {
                $emp->departments = collect($emp->department_ids ?? [])
                    ->map(fn($id) => $allDepts->get($id))
                    ->filter()->values()->toArray();

                $emp->avatar_url = $this->resolveAvatarUrl(
                    $emp->getRawOriginal('avatar_url')
                );
            });

            $total    = $filtered->count();
            $items    = $filtered->slice(($page - 1) * $perPage, $perPage)->values();
            $lastPage = (int) ceil($total / $perPage) ?: 1;

            return response()->json([
                'data'         => $items,
                'current_page' => $page,
                'last_page'    => $lastPage,
                'per_page'     => $perPage,
                'total'        => $total,
                'from'         => $total ? ($page - 1) * $perPage + 1 : null,
                'to'           => min($page * $perPage, $total),
            ]);
        }

        $allDepts  = Department::all(['id', 'name'])->keyBy('id');
        $paginator = $query->paginate($request->per_page ?? 10);

        $paginator->getCollection()->transform(function ($employee) use ($allDepts) {
            $employee->departments = collect($employee->department_ids ?? [])
                ->map(fn($id) => $allDepts->get($id))
                ->filter()->values()->toArray();

            $employee->avatar_url = $this->resolveAvatarUrl(
                $employee->getRawOriginal('avatar_url')
            );

            return $employee;
        });

        return $paginator;
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $this->assertUniqueEmployeeNumber($request->employee_number);

        return DB::transaction(function () use ($request, $data) {
            if ($request->hasFile('avatar_url')) {
                $data['avatar_url'] = $this->storeAvatar($request->file('avatar_url'));
            }

            $data['department_ids']       = array_map('intval', $request->department_ids ?? []);
            $data['role_position']        = $request->role_position        ?? [];
            $data['title']                = $request->title                ?? [];
            $data['position_designation'] = $request->position_designation ?? [];

            $employee = Employee::create(
                collect($data)->except([
                    'parent_ids', 'plantilla_item_id', 'step_increment_id',
                    'gender', 'address', 'birthdate', 'contact', 'email',
                ])->toArray()
            );

            $employee->info()->create([
                'gender'    => $request->gender,
                'address'   => $request->address,
                'birthdate' => $request->birthdate,
                'contact'   => $request->contact,
            ]);

            $this->syncAssignment($employee, $request);
            $this->syncParents($employee, $data['parent_ids'] ?? []);

            return $employee->fresh()->load([
                'info', 'parents', 'division',
                'primaryAssignment.plantillaItem.salaryGrade',
                'primaryAssignment.stepIncrement',
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
        $data = $request->validate($this->rules());
        $this->assertUniqueEmployeeNumber($request->employee_number, $employee->id);

        return DB::transaction(function () use ($request, $employee, $data) {
            if ($request->hasFile('avatar_url')) {
                $oldPath = $employee->getRawOriginal('avatar_url');
                if ($oldPath && file_exists(storage_path('app/public/' . $oldPath))) {
                    Storage::disk('public')->delete($oldPath);
                }
                $data['avatar_url'] = $this->storeAvatar($request->file('avatar_url'));
            }

            $data['department_ids']       = array_map('intval', $request->department_ids ?? []);
            $data['role_position']        = $request->role_position        ?? [];
            $data['title']                = $request->title                ?? [];
            $data['position_designation'] = $request->position_designation ?? [];

            $employee->update(
                collect($data)->except([
                    'parent_ids', 'plantilla_item_id', 'step_increment_id',
                    'gender', 'address', 'birthdate', 'contact', 'email',
                ])->toArray()
            );

            $employee->info()->updateOrCreate(
                ['employee_id' => $employee->id],
                [
                    'gender'    => $request->gender,
                    'address'   => $request->address,
                    'birthdate' => $request->birthdate,
                    'contact'   => $request->contact,
                ]
            );

            $this->syncAssignment($employee, $request);

            if ($request->input('_sync_parents') === '1') {
                $parentIds = array_filter(
                    array_map('intval', (array) ($request->input('parent_ids') ?? []))
                );
                $this->syncParents($employee, $parentIds);
            }

            return $employee->fresh()->load([
                'info', 'parents', 'division',
                'primaryAssignment.plantillaItem.salaryGrade',
                'primaryAssignment.stepIncrement',
            ]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY
    |--------------------------------------------------------------------------
    */

    public function destroy(Employee $employee)
    {
        $path = $employee->getRawOriginal('avatar_url');
        if ($path && file_exists(storage_path('app/public/' . $path))) {
            Storage::disk('public')->delete($path);
        }

        EmployeeHierarchy::where('employee_id', $employee->id)
            ->orWhere('parent_id', $employee->id)
            ->delete();

        $employee->delete();

        return response()->json(['message' => 'Deleted successfully.']);
    }

    /*
    |--------------------------------------------------------------------------
    | AVATAR
    |--------------------------------------------------------------------------
    */

    public function avatar(Request $request, Employee $employee)
    {
        Log::channel('confidential_access')->info('Avatar accessed', [
            'subject_emp_id' => $employee->id,
            'accessed_by'    => $request->user()?->id ?? 'unauthenticated',
            'ip'             => $request->ip(),
            'at'             => now()->toIso8601String(),
        ]);

        $path = $employee->getRawOriginal('avatar_url');
        $url  = $this->resolveAvatarUrl($path);

        return response()->json(['url' => $url]);
    }

    /*
    |--------------------------------------------------------------------------
    | PRIVATE HELPERS
    |--------------------------------------------------------------------------
    */

    private function storeAvatar(\Illuminate\Http\UploadedFile $file): string
    {
        $path = 'avatars/' . Str::uuid() . '.' . $file->getClientOriginalExtension();
        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));
        return $path;
    }

    private function syncParents(Employee $employee, array $parents): void
    {
        EmployeeHierarchy::where('employee_id', $employee->id)->delete();

        foreach (array_filter($parents) as $parentId) {
            EmployeeHierarchy::create([
                'employee_id' => $employee->id,
                'parent_id'   => $parentId,
            ]);
        }
    }

    private function syncAssignment(Employee $employee, Request $request): void
    {
        if (!$request->has('plantilla_item_id')) return;

        $newPlantillaId = $request->plantilla_item_id ?: null;
        $newStepId      = $request->step_increment_id ?: null;

        $existing = EmployeeAssignment::where('employee_id', $employee->id)
            ->where('is_primary', true)
            ->whereNull('end_date')
            ->first();

        if (
            $existing &&
            (string) $existing->plantilla_item_id === (string) $newPlantillaId &&
            (string) $existing->step_increment_id === (string) $newStepId
        ) {
            return;
        }

        if ($existing) {
            $existing->update(['end_date' => now()]);
        }

        if (!$newPlantillaId) return;

        $occupied = EmployeeAssignment::where('plantilla_item_id', $newPlantillaId)
            ->where('employee_id', '!=', $employee->id)
            ->whereNull('end_date')
            ->exists();

        if ($occupied) {
            throw new \Exception("Plantilla item is already occupied by another employee.");
        }

        EmployeeAssignment::create([
            'employee_id'       => $employee->id,
            'plantilla_item_id' => $newPlantillaId,
            'step_increment_id' => $newStepId,
            'is_primary'        => true,
            'start_date'        => now(),
            'end_date'          => null,
        ]);
    }
}