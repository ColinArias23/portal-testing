<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\Employee;

class ManpowerMappingController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | TREE (Org Chart + Staff Modal)
    |--------------------------------------------------------------------------
    */
    public function tree()
    {
        // Load all employees
        // $employees = DB::table('employees')
        //     ->get()
        //     ->keyBy('id');
        $employees = Employee::all()->keyBy('id');

        // Load hierarchy
        $edges = DB::table('employee_hierarchy')->get();

        $childrenMap = [];
        foreach ($edges as $e) {

            // Prevent self loop
            if ($e->parent_id !== null && $e->parent_id == $e->employee_id) {
                continue;
            }

            $childrenMap[$e->parent_id][] = $e->employee_id;
        }

        /*
        |--------------------------------------------------------------------------
        | NAME FORMATTER
        |--------------------------------------------------------------------------
        */
        $makeName = function ($emp) {
            return trim(collect([
                $emp->prefix,
                $emp->first_name,
                $emp->middle_name,
                $emp->last_name,
                $emp->suffix
            ])->filter()->implode(' '));
        };

        /*
        |--------------------------------------------------------------------------
        | STAFF MODAL (ONLY Staff & Employee)
        |--------------------------------------------------------------------------
        */
        $buildStaff = function ($managerId) use ($childrenMap, $employees) {

        $staff = [];
        $childIds = $childrenMap[$managerId] ?? [];

        foreach ($childIds as $childId) {

            $emp = $employees[$childId] ?? null;
            if (!$emp) continue;

            // Skip self
            if ((int)$emp->id === (int)$managerId) continue;

            $role = strtolower(trim($emp->role_position ?? ''));

            // ONLY staff & employee
            if (!in_array($role, ['staff', 'employee'])) continue;

            $staff[] = [
                'id' => 'staff-' . $emp->id,
                'employeeId' => $emp->id,

                // 🔥 NEW — Full name parts
                'prefix' => $emp->prefix,
                'first_name' => $emp->first_name,
                'middle_name' => $emp->middle_name,
                'last_name' => $emp->last_name,
                'suffix' => $emp->suffix,
                'title' => $emp->title,

                'role' => $emp->role_position,
                'employmentType' => $emp->employment_type,
                'image' => $emp->avatar_url
                    ?: 'https://cdn3.iconfinder.com/data/icons/avatars-flat/33/man_5-1024.png',
            ];
        }

        return $staff;
    };

        /*
        |--------------------------------------------------------------------------
        | SAFE TREE BUILDER (Managers Only)
        |--------------------------------------------------------------------------
        */
        $buildTree = function ($parentId, $visited = []) use (
            &$buildTree,
            $childrenMap,
            $employees,
            $makeName,
            $buildStaff
        ) {

            if (in_array($parentId, $visited, true)) {
                return [];
            }

            $visited[] = $parentId;

            $nodes = [];
            $childIds = $childrenMap[$parentId] ?? [];

            foreach ($childIds as $childId) {

                $emp = $employees[$childId] ?? null;
                if (!$emp) continue;

                $role = strtolower(trim($emp->role_position ?? ''));

                // Hide staff & employee in main tree
                if (in_array($role, ['staff', 'employee'])) continue;

                $isExpanded = (bool) ($emp->expanded ?? false);

                $nodes[] = [
                    'key' => 'node-' . $emp->id,
                    'type' => 'person',
                    'expanded' => $isExpanded,
                    'data' => [
                        'employeeId' => $emp->id,

                        // 🔥 ADD THESE (ITO ANG KULANG MO)
                        'prefix' => $emp->prefix,
                        'first_name' => $emp->first_name,
                        'middle_name' => $emp->middle_name,
                        'last_name' => $emp->last_name,
                        'suffix' => $emp->suffix,
                        'title' => $emp->title,

                        // Keep formatted name also
                        'name' => $makeName($emp),

                        'role' => $emp->role_position,
                        'employmentType' => $emp->employment_type,

                        'image' => $emp->avatar_url
                            ?: 'https://cdn3.iconfinder.com/data/icons/avatars-flat/33/man_5-1024.png',

                        'border_color' => $emp->border_color ?? 'gray',
                        'aligned' => (bool) ($emp->aligned ?? false),
                        'expanded' => $isExpanded,

                        // Staff modal
                        'staff' => $buildStaff($emp->id),
                    ],
                    'children' => $buildTree($childId, $visited),
                ];
            }

            return $nodes;
        };

        // $rootParentId = isset($childrenMap[null]) ? null : 0;
        $rootParentId = null;

        if (!array_key_exists(null, $childrenMap) && !array_key_exists('', $childrenMap)) {
            $rootParentId = 0;
        }

        return response()->json([
            'nodes' => $buildTree($rootParentId)
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SUMMARY (UNCHANGED)
    |--------------------------------------------------------------------------
    */
    public function summary()
    {
        $plantilla = DB::table('employees')
            ->whereRaw("LOWER(employment_type) LIKE '%plantilla%'")
            ->count();

        $cos = DB::table('employees')
            ->where(function ($q) {
                $q->whereRaw("LOWER(employment_type) LIKE '%cos%'")
                  ->orWhereRaw("LOWER(employment_type) LIKE '%contract%'");
            })
            ->count();

        $consultant = DB::table('employees')
            ->whereRaw("LOWER(employment_type) LIKE '%consultant%'")
            ->count();

        $vacant = DB::table('plantilla_items')->count();

        return response()->json([
            'plantilla'  => $plantilla,
            'cos'        => $cos,
            'consultant' => $consultant,
            'vacant'     => $vacant,
            'total'      => $plantilla + $cos + $consultant + $vacant,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | REPORT (BASED ON MAPPING - NO DUPLICATES)
    |--------------------------------------------------------------------------
    */
   public function report(Request $request)
    {
        $deptId = $request->get('department_id');

        $query = \App\Models\Employee::query()
            ->with([
                'division.department',
                'plantillaItem.salaryGrade',
                'stepIncrement'
            ]);

        if ($deptId && $deptId !== 'All' && is_numeric($deptId)) {
            $query->whereHas('division', function ($q) use ($deptId) {
                $q->where('department_id', $deptId);
            });
        }

        $rows = $query->get()->map(function ($emp) {

            // ✅ Combine First + Middle + Last + Suffix
            $fullName = collect([
                $emp->first_name,
                $emp->middle_name
                    ? strtoupper(substr($emp->middle_name, 0, 1)) . '.'
                    : null,
                $emp->last_name,
                $emp->suffix
            ])->filter()->implode(' ');

            return [
                'Employee Number' => $emp->employee_number,

                // Separate Prefix
                'Prefix' => $emp->prefix ?? '',

                // Combined Name
                'Full Name' => $fullName,

                'Title' => $emp->title ?? '',
                'Department' => $emp->division->department->name ?? '',
                'Division' => $emp->division->name ?? '',
                'Position Title' => $emp->plantillaItem->title ?? '',
                // 'Salary Grade' => $emp->plantillaItem->salaryGrade->salary_grade ?? '',
                'Salary Grade' => optional(optional($emp->plantillaItem)->salaryGrade)->salary_grade ?? '',
                'Step Increment' => $emp->stepIncrement->step ?? '',
                'Employment Type' => $emp->employment_type,
                'Employment Status' => $emp->employment_status,
            ];
        });

        return response()->json([
            'report' => $rows
        ]);
    }
}