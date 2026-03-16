<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\Department;

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
        try {
        $deptId          = $request->get('department_id');
        $divId           = $request->get('division_id');
        $showStaffAsNode = ($deptId && $deptId !== 'All') || ($divId && $divId !== 'All');

        $departmentMap = Department::pluck('name', 'id');

        /*
        |--------------------------------------------------------------------------
        | EMPLOYEES
        |--------------------------------------------------------------------------
        */

        $employees = Employee::select(
                'id', 'department_ids', 'division_id', 'prefix', 'first_name',
                'middle_name', 'last_name', 'suffix', 'title', 'role_position',
                'employment_type', 'avatar_url', 'border_color', 'aligned', 'expanded'
            )
            ->when($deptId && $deptId !== 'All', fn($q) =>
                $q->whereRaw('JSON_CONTAINS(department_ids, ?)', [json_encode((int) $deptId)])
            )
            ->when($divId && $divId !== 'All', fn($q) =>
                $q->where('division_id', $divId)
            )
            ->get();

        $employeeMap = $employees->keyBy('id');

        /*
        |--------------------------------------------------------------------------
        | HIERARCHY — filter out any rows where employee_id or parent_id is NULL
        |--------------------------------------------------------------------------
        */

        $edges = DB::table('employee_hierarchy')
            ->select('parent_id', 'employee_id')
            ->whereNotNull('employee_id')   // ← fix: skip corrupted rows (e.g. employee_id = NULL)
            ->whereNotNull('parent_id')     // ← rows with NULL parent_id are root markers, handled separately
            ->get();

        // Separately get the explicit root markers (parent_id IS NULL) so we
        // know which employees were intentionally placed as roots in the DB.
        // Build as an associative array [ employee_id => true ] for O(1) lookup.
        $explicitRootIds = DB::table('employee_hierarchy')
            ->whereNull('parent_id')
            ->whereNotNull('employee_id')
            ->pluck('employee_id')
            ->mapWithKeys(fn($id) => [(int) $id => true])
            ->toArray();

        /*
        |--------------------------------------------------------------------------
        | INCLUDE PARENTS (WHEN FILTERED)
        |--------------------------------------------------------------------------
        */

        if ($deptId && $deptId !== 'All') {
            $ids          = $employees->pluck('id')->toArray();
            $parentLookup = $edges->keyBy('employee_id');
            $queue        = $ids;

            while ($queue) {
                $current = array_shift($queue);
                if (!isset($parentLookup[$current])) continue;
                $parentId = $parentLookup[$current]->parent_id;
                if ($parentId && !in_array($parentId, $ids)) {
                    $ids[]   = $parentId;
                    $queue[] = $parentId;
                }
            }

            Employee::whereIn('id', $ids)
                ->get()
                ->each(fn($emp) => $employeeMap[$emp->id] = $emp);
        }

        /*
        |--------------------------------------------------------------------------
        | BUILD MAPS — strip circular / mutual edges
        |--------------------------------------------------------------------------
        */

        $edgePairs = [];
        foreach ($edges as $edge) {
            if ($edge->parent_id == $edge->employee_id) continue; // self-loop
            $edgePairs[$edge->parent_id . '-' . $edge->employee_id] = $edge;
        }

        $cleanEdges = [];
        foreach ($edgePairs as $key => $edge) {
            $reverseKey = $edge->employee_id . '-' . $edge->parent_id;
            if (isset($edgePairs[$reverseKey])) {
                if ($edge->parent_id < $edge->employee_id) {
                    $cleanEdges[$key] = $edge;
                }
            } else {
                $cleanEdges[$key] = $edge;
            }
        }

        $childrenMap  = [];
        $parentMap    = [];
        $allParentMap = [];

        foreach ($cleanEdges as $edge) {
            $childrenMap[$edge->parent_id][]    = $edge->employee_id;
            $allParentMap[$edge->employee_id][] = $edge->parent_id;
            if (!isset($parentMap[$edge->employee_id])) {
                $parentMap[$edge->employee_id] = $edge->parent_id;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | HELPERS
        |--------------------------------------------------------------------------
        */

        $makeName = function ($emp) {
            return strtoupper(trim(collect([
                $emp->prefix, $emp->first_name, $emp->middle_name,
                $emp->last_name, $emp->suffix,
            ])->filter()->implode(' ')));
        };

        $resolveIsStaff = function ($emp): bool {
            $roles = collect($emp->role_position ?? [])
                ->map(fn($r) => trim(strtolower($r)))->filter()->values();
            if ($roles->isEmpty()) return true;
            return $roles->every(fn($r) => in_array($r, ['staff', 'employee']));
        };

        $resolveDept = function ($emp) use ($departmentMap) {
            $deptIds = is_array($emp->department_ids)
                ? $emp->department_ids
                : json_decode($emp->department_ids ?? '[]', true);
            return collect($deptIds)
                ->map(fn($id) => strtoupper($departmentMap[$id] ?? ''))
                ->filter()
                ->implode(', ');
        };

        $formatEmployee = function ($emp, $name) use (
            $resolveIsStaff, $resolveDept, $allParentMap, $employeeMap, $makeName
        ) {
            $isStaff   = $resolveIsStaff($emp);
            $roleLabel = strtoupper(collect($emp->role_position ?? [])->filter()->implode(', '));
            $deptLabel = $resolveDept($emp);

            $supervisors = collect($allParentMap[$emp->id] ?? [])
                ->map(function ($pid) use ($employeeMap, $makeName, $resolveDept) {
                    $p = $employeeMap[$pid] ?? null;
                    if (!$p) return null;
                    return [
                        'employeeId'     => $p->id,
                        'name'           => $makeName($p),
                        'prefix'         => strtoupper($p->prefix ?? ''),
                        'first_name'     => strtoupper($p->first_name ?? ''),
                        'middle_name'    => strtoupper($p->middle_name ?? ''),
                        'last_name'      => strtoupper($p->last_name ?? ''),
                        'suffix'         => strtoupper($p->suffix ?? ''),
                        'title'          => strtoupper(is_array($p->title) ? implode(', ', $p->title) : ($p->title ?? '')),
                        'role'           => strtoupper(collect($p->role_position ?? [])->filter()->implode(', ')),
                        'department'     => $resolveDept($p),
                        'employmentType' => strtoupper($p->employment_type ?? ''),
                        'image'          => $p->avatar_url ?: self::DEFAULT_AVATAR,
                    ];
                })
                ->filter()
                ->values()
                ->toArray();

            return [
                'employeeId'     => $emp->id,
                'name'           => $name,
                'prefix'         => strtoupper($emp->prefix ?? ''),
                'first_name'     => strtoupper($emp->first_name ?? ''),
                'middle_name'    => strtoupper($emp->middle_name ?? ''),
                'last_name'      => strtoupper($emp->last_name ?? ''),
                'suffix'         => strtoupper($emp->suffix ?? ''),
                'title'          => strtoupper(is_array($emp->title) ? implode(', ', $emp->title) : ($emp->title ?? '')),
                'role'           => $roleLabel,
                'role_position'  => $emp->role_position ?? [],
                'department'     => $deptLabel,
                'employmentType' => strtoupper($emp->employment_type ?? ''),
                'image'          => $emp->avatar_url ?: self::DEFAULT_AVATAR,
                'border_color'   => $emp->border_color ?? 'gray',
                'aligned'        => (bool) $emp->aligned,
                'isStaff'        => $isStaff,
                'showRole'       => !$isStaff,
                'showDept'       => !$isStaff,
                'supervisors'    => $supervisors,
            ];
        };

        /*
        |--------------------------------------------------------------------------
        | STAFF BUILDER
        |--------------------------------------------------------------------------
        */

        $buildStaff = function ($managerId) use (
            $childrenMap, $employeeMap, $showStaffAsNode,
            $formatEmployee, $makeName, $resolveIsStaff
        ) {
            if ($showStaffAsNode) return [];

            $staff = [];
            foreach ($childrenMap[$managerId] ?? [] as $childId) {
                $emp = $employeeMap[$childId] ?? null;
                if (!$emp || !$resolveIsStaff($emp)) continue;
                $staff[] = [
                    'id' => 'staff-' . $emp->id,
                    ...$formatEmployee($emp, $makeName($emp)),
                ];
            }
            return $staff;
        };

        /*
        |--------------------------------------------------------------------------
        | TREE BUILDER
        |--------------------------------------------------------------------------
        */

        $buildTree = function ($parentId, array $ancestorChain = []) use (
            &$buildTree,
            $childrenMap, $employeeMap,
            $makeName, $buildStaff, $formatEmployee,
            $showStaffAsNode, $resolveIsStaff
        ) {
            $nodes = [];

            foreach ($childrenMap[$parentId] ?? [] as $childId) {
                $emp = $employeeMap[$childId] ?? null;
                if (!$emp) continue;

                $isStaff = $resolveIsStaff($emp);
                if (!$showStaffAsNode && $isStaff) continue;

                if (in_array($childId, $ancestorChain)) continue;

                $nodes[] = [
                    'key'      => 'node-' . $parentId . '-' . $emp->id,
                    'type'     => 'person',
                    'expanded' => $showStaffAsNode ? true : (bool) $emp->expanded,
                    'data'     => [
                        ...$formatEmployee($emp, $makeName($emp)),
                        'staff' => $buildStaff($emp->id),
                    ],
                    'children' => $buildTree($childId, [...$ancestorChain, $childId]),
                ];
            }

            return $nodes;
        };

        /*
        |--------------------------------------------------------------------------
        | ROOTS
        |
        | An employee is a root when:
        |   1. They have an explicit NULL parent_id row in employee_hierarchy, OR
        |   2. They appear in the employeeMap but have no parent in parentMap
        |      (i.e. they were never listed as a child of anyone)
        |--------------------------------------------------------------------------
        */

        $roots = collect($employeeMap)->filter(function ($emp) use ($parentMap, $explicitRootIds) {
            // Explicit root marker takes priority
            if (isset($explicitRootIds[$emp->id])) return true;
            // Otherwise: no one claims this employee as a child
            return !isset($parentMap[$emp->id]);
        });

        $nodes = [];

        foreach ($roots as $root) {
            $nodes[] = [
                'key'      => 'node-root-' . $root->id,
                'type'     => 'person',
                'expanded' => $showStaffAsNode ? true : (bool) $root->expanded,
                'data'     => [
                    ...$formatEmployee($root, $makeName($root)),
                    'staff' => $buildStaff($root->id),
                ],
                'children' => $buildTree($root->id, [$root->id]),
            ];
        }

        return response()->json(['nodes' => $nodes]);

        } catch (\Throwable $e) {
            Log::error('ManpowerMapping@tree: ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
        }
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
            'plantilla'  => $employees->plantilla,
            'cos'        => $employees->cos,
            'consultant' => $employees->consultant,
            'vacant'     => $vacant,
            'total'      => $employees->plantilla + $employees->cos + $employees->consultant + $vacant,
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
        $divId  = $request->get('division_id');
 
        $employees = Employee::with([
            'division',
            'primaryAssignment.plantillaItem.salaryGrade',
            'primaryAssignment.stepIncrement',
        ])
        ->when($divId && $divId !== 'All', fn($q) =>
            $q->where('division_id', $divId)
        )
        ->when($deptId && $deptId !== 'All', fn($q) =>
            $q->whereRaw('JSON_CONTAINS(department_ids, ?)', [json_encode((int) $deptId)])
        )
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
 
        $departmentMap = Department::pluck('name', 'id');
 
        $rows = $employees->map(function ($emp) use ($departmentMap) {
            $assignment = $emp->primaryAssignment;
            $plantilla  = optional($assignment)->plantillaItem;
            $sg         = optional($plantilla)->salaryGrade;
            $step       = optional($assignment)->stepIncrement;
 
            $roleLabel = strtoupper(collect($emp->role_position ?? [])->filter()->implode(', '));
 
            $deptIds   = is_array($emp->department_ids)
                ? $emp->department_ids
                : json_decode($emp->department_ids ?? '[]', true);
 
            $deptLabel = collect($deptIds)
                ->map(fn($id) => strtoupper($departmentMap[$id] ?? ''))
                ->filter()
                ->implode(', ');
 
            return [
                'EMPLOYEE NUMBER'   => strtoupper($emp->employee_number ?? ''),
                'PREFIX'            => strtoupper($emp->prefix ?? ''),
                'FIRST NAME'        => strtoupper($emp->first_name ?? ''),
                'MIDDLE NAME'       => strtoupper($emp->middle_name ?? ''),
                'LAST NAME'         => strtoupper($emp->last_name ?? ''),
                'TITLE'             => strtoupper(is_array($emp->title) ? implode(', ', $emp->title) : ($emp->title ?? '')),
                'ROLE POSITION'     => $roleLabel,
                'DEPARTMENT'        => $deptLabel,
                'DIVISION'          => strtoupper(optional($emp->division)->name ?? ''),
                'POSITION TITLE'    => strtoupper(optional($plantilla)->title ?? ''),
                'SALARY GRADE'      => strtoupper(optional($sg)->salary_grade ?? ''),
                'STEP'              => strtoupper(optional($step)->step ?? ''),
                'EMPLOYMENT TYPE'   => strtoupper($emp->employment_type ?? ''),
                'EMPLOYMENT STATUS' => strtoupper($emp->employment_status ?? ''),
            ];
        });
 
        // Build title based on active filters
        $divisionName = '';
        $deptName     = '';
 
        if ($employees->isNotEmpty()) {
            if ($divId && $divId !== 'All') {
                $divisionName = strtoupper(optional($employees->first()->division)->name ?? '');
            }
            if ($deptId && $deptId !== 'All') {
                $deptName = strtoupper($departmentMap[$deptId] ?? '');
            }
        }
 
        $titleParts = array_filter([$divisionName, $deptName]);
        $title = count($titleParts)
            ? implode(' - ', $titleParts) . ' - EMPLOYEE MAPPING'
            : 'EMPLOYEE MAPPING';
 
        return response()->json([
            'title'    => $title,
            'division' => $divisionName,
            'report'   => $rows,
        ]);
    }
}