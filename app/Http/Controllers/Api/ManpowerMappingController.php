<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\Employee;

class ManpowerMappingController extends Controller
{
    const DEFAULT_AVATAR = 'https://cdn3.iconfinder.com/data/icons/avatars-flat/33/man_5-1024.png';

    /*
    |--------------------------------------------------------------------------
    | TREE
    |--------------------------------------------------------------------------
    */

    public function tree(Request $request)
    {
        $deptId = $request->get('department_id');
        $showStaffAsNode = $deptId && $deptId !== 'All';

        /*
        |--------------------------------------------------------------------------
        | EMPLOYEES
        |--------------------------------------------------------------------------
        */

        $employees = Employee::select(
            'id',
            'department_id',
            'prefix',
            'first_name',
            'middle_name',
            'last_name',
            'suffix',
            'title',
            'role_position',
            'employment_type',
            'avatar_url',
            'border_color',
            'aligned',
            'expanded'
        )
        ->when($deptId && $deptId !== 'All', fn($q) => $q->where('department_id', $deptId))
        ->get();

        $employeeMap = $employees->keyBy('id');

        /*
        |--------------------------------------------------------------------------
        | HIERARCHY
        |--------------------------------------------------------------------------
        */

        $edges = DB::table('employee_hierarchy')
            ->select('parent_id','employee_id')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | INCLUDE PARENTS (WHEN FILTERED)
        |--------------------------------------------------------------------------
        */

        if ($deptId && $deptId !== 'All') {

            $ids = $employees->pluck('id')->toArray();
            $parentLookup = $edges->keyBy('employee_id');

            $queue = $ids;

            while ($queue) {

                $current = array_shift($queue);

                if (!isset($parentLookup[$current])) continue;

                $parentId = $parentLookup[$current]->parent_id;

                if ($parentId && !in_array($parentId, $ids)) {
                    $ids[] = $parentId;
                    $queue[] = $parentId;
                }
            }

            Employee::whereIn('id', $ids)
                ->get()
                ->each(fn($emp) => $employeeMap[$emp->id] = $emp);
        }

        /*
        |--------------------------------------------------------------------------
        | BUILD MAPS
        |--------------------------------------------------------------------------
        */

        $childrenMap = [];
        $parentMap = [];

        foreach ($edges as $edge) {

            if ($edge->parent_id == $edge->employee_id) continue;

            $childrenMap[$edge->parent_id][] = $edge->employee_id;
            $parentMap[$edge->employee_id] = $edge->parent_id;
        }

        /*
        |--------------------------------------------------------------------------
        | HELPERS
        |--------------------------------------------------------------------------
        */

        $makeName = function ($emp) {

            return strtoupper(
                trim(collect([
                    $emp->prefix,
                    $emp->first_name,
                    $emp->middle_name,
                    $emp->last_name,
                    $emp->suffix
                ])->filter()->implode(' '))
            );
        };

        $formatEmployee = function ($emp, $name) {

            return [
                'employeeId' => $emp->id,
                'name' => $name,
                'prefix' => strtoupper($emp->prefix ?? ''),
                'first_name' => strtoupper($emp->first_name ?? ''),
                'middle_name' => strtoupper($emp->middle_name ?? ''),
                'last_name' => strtoupper($emp->last_name ?? ''),
                'suffix' => strtoupper($emp->suffix ?? ''),
                'title' => strtoupper($emp->title ?? ''),
                'role' => strtoupper($emp->role_position ?? ''),
                'employmentType' => strtoupper($emp->employment_type ?? ''),
                'image' => $emp->avatar_url ?: self::DEFAULT_AVATAR,
                'border_color' => $emp->border_color ?? 'gray',
                'aligned' => (bool) $emp->aligned
            ];
        };

        /*
        |--------------------------------------------------------------------------
        | STAFF BUILDER
        |--------------------------------------------------------------------------
        */

        $buildStaff = function ($managerId) use ($childrenMap,$employeeMap,$showStaffAsNode,$formatEmployee,$makeName){

            if ($showStaffAsNode) return [];

            $staff = [];

            foreach ($childrenMap[$managerId] ?? [] as $childId) {

                $emp = $employeeMap[$childId] ?? null;
                if (!$emp) continue;

                $role = strtolower($emp->role_position ?? '');

                if (!in_array($role, ['staff','employee'])) continue;

                $staff[] = [
                    'id' => 'staff-'.$emp->id,
                    ...$formatEmployee($emp,$makeName($emp))
                ];
            }

            return $staff;
        };

        /*
        |--------------------------------------------------------------------------
        | TREE BUILDER
        |--------------------------------------------------------------------------
        */

        $visited = [];

        $buildTree = function ($parentId) use (
            &$buildTree,
            &$visited,
            $childrenMap,
            $employeeMap,
            $makeName,
            $buildStaff,
            $formatEmployee,
            $showStaffAsNode
        ){

            if(isset($visited[$parentId])) return [];

            $visited[$parentId] = true;

            $nodes = [];

            foreach ($childrenMap[$parentId] ?? [] as $childId){

                $emp = $employeeMap[$childId] ?? null;
                if(!$emp) continue;

                $role = strtolower($emp->role_position ?? '');

                if(!$showStaffAsNode && in_array($role,['staff','employee'])) continue;

                $nodes[] = [

                    'key' => 'node-'.$emp->id,
                    'type' => 'person',
                    'expanded' => $showStaffAsNode ? true : (bool)$emp->expanded,

                    'data' => [
                        ...$formatEmployee($emp,$makeName($emp)),
                        'staff' => $buildStaff($emp->id)
                    ],

                    'children' => $buildTree($childId)
                ];
            }

            return $nodes;
        };

        /*
        |--------------------------------------------------------------------------
        | ROOTS
        |--------------------------------------------------------------------------
        */

        $roots = collect($employeeMap)->filter(fn($emp) => !isset($parentMap[$emp->id]));

        $nodes = [];

        foreach ($roots as $root) {

            $nodes[] = [

                'key' => 'node-'.$root->id,
                'type' => 'person',
                'expanded' => $showStaffAsNode ? true : (bool)$root->expanded,

                'data' => [
                    ...$formatEmployee($root,$makeName($root)),
                    'staff' => $buildStaff($root->id)
                ],

                'children' => $buildTree($root->id)
            ];
        }

        return response()->json([
            'nodes' => $nodes
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SUMMARY
    |--------------------------------------------------------------------------
    */

    public function summary()
    {
        $employees = DB::table('employees')
            ->selectRaw("
                SUM(CASE WHEN LOWER(employment_type) LIKE '%plantilla%' THEN 1 ELSE 0 END) as plantilla,
                SUM(CASE WHEN LOWER(employment_type) LIKE '%cos%' OR LOWER(employment_type) LIKE '%contract%' THEN 1 ELSE 0 END) as cos,
                SUM(CASE WHEN LOWER(employment_type) LIKE '%consultant%' THEN 1 ELSE 0 END) as consultant
            ")
            ->first();

        $vacant = DB::table('plantilla_items')->count();

        return response()->json([
            'plantilla' => $employees->plantilla,
            'cos' => $employees->cos,
            'consultant' => $employees->consultant,
            'vacant' => $vacant,
            'total' => $employees->plantilla + $employees->cos + $employees->consultant + $vacant
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | REPORT
    |--------------------------------------------------------------------------
    */

    public function report(Request $request)
    {
        $deptId = $request->get('department_id');

        $employees = Employee::with([
            'department.division',
            'plantillaItem.salaryGrade',
            'stepIncrement'
        ])
        ->when($deptId && $deptId !== 'All', fn ($q) => $q->where('department_id', $deptId))
        ->orderByRaw("
            CASE
                WHEN LOWER(employment_type) LIKE '%plantilla%' THEN 1
                WHEN LOWER(employment_type) LIKE '%cos%' OR LOWER(employment_type) LIKE '%contract%' THEN 2
                WHEN LOWER(employment_type) LIKE '%consultant%' THEN 3
                ELSE 4
            END
        ")
        ->orderBy('last_name')
        ->get();

        $rows = $employees->map(function ($emp) {

            return [
                'EMPLOYEE NUMBER' => strtoupper($emp->employee_number ?? ''),
                'PREFIX' => strtoupper($emp->prefix ?? ''),
                'FIRST NAME' => strtoupper($emp->first_name ?? ''),
                'MIDDLE NAME' => strtoupper($emp->middle_name ?? ''),
                'LAST NAME' => strtoupper($emp->last_name ?? ''),
                'TITLE' => strtoupper($emp->title ?? ''),
                'DIVISION' => strtoupper(optional(optional($emp->department)->division)->name ?? ''),
                'DEPARTMENT' => strtoupper(optional($emp->department)->name ?? ''),
                'POSITION TITLE' => strtoupper(optional($emp->plantillaItem)->title ?? ''),
                'SALARY GRADE' => strtoupper(optional(optional($emp->plantillaItem)->salaryGrade)->salary_grade ?? ''),
                'STEP' => strtoupper(optional($emp->stepIncrement)->step ?? ''),
                'EMPLOYMENT TYPE' => strtoupper($emp->employment_type ?? ''),
                'EMPLOYMENT STATUS' => strtoupper($emp->employment_status ?? '')
            ];
        });

        /*
        |--------------------------------------------------------------------------
        | HEADER / TITLE (same behavior as your old code)
        |--------------------------------------------------------------------------
        */

        $title = "EMPLOYEE MAPPING";
        $divisionName = "";
        $departmentName = "";

        if ($deptId && $deptId !== 'All' && $employees->isNotEmpty()) {

            $first = $employees->first();

            $departmentName = strtoupper(optional($first->department)->name ?? '');
            $divisionName = strtoupper(optional(optional($first->department)->division)->name ?? '');

            $title = trim($divisionName . " - " . $departmentName);
        }

        return response()->json([
            'title' => $title,
            'division' => $divisionName,
            'department' => $departmentName,
            'report' => $rows
        ]);
    }
}