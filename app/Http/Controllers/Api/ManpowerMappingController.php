<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\Department;
use App\Models\PlantillaItem;

class ManpowerMappingController extends Controller
{
    const DEFAULT_AVATAR = 'https://cdn3.iconfinder.com/data/icons/avatars-flat/33/man_5-1024.png';

    /*
    |--------------------------------------------------------------------------
    | HELPER — Resolve avatar URL
    |--------------------------------------------------------------------------
    */

    private function resolveAvatar(?string $avatarUrl): string
    {
        if (!$avatarUrl) {
            return self::DEFAULT_AVATAR;
        }

        if (str_starts_with($avatarUrl, 'http://') || str_starts_with($avatarUrl, 'https://')) {
            return $avatarUrl;
        }

        return asset('storage/' . $avatarUrl);
    }

    /**
     * Decode a column that may be stored as a JSON string or already cast to array.
     * Returns a plain PHP array of strings.
     */
    private function decodeJsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && str_starts_with(trim($value), '[')) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        if (is_string($value) && $value !== '') {
            return [$value];
        }
        return [];
    }

    /**
     * Build the display name in the format:
     *   PREFIX. FIRSTNAME MIDDLEINITIAL. LASTNAME SUFFIX, TITLE1, TITLE2
     *
     * e.g. "DR. JUAN C. DELA CRUZ JR., MD, FPCS"
     */
    private function makeName(object $emp): string
    {
        $parts = array_filter([
            $emp->prefix     ? strtoupper(trim($emp->prefix))     : null,
            $emp->first_name ? strtoupper(trim($emp->first_name)) : null,
            $emp->middle_name
                ? strtoupper(substr(trim($emp->middle_name), 0, 1)) . '.'
                : null,
            $emp->last_name  ? strtoupper(trim($emp->last_name))  : null,
            $emp->suffix     ? strtoupper(trim($emp->suffix))     : null,
        ]);

        $fullName = implode(' ', $parts);

        // Append titles if present
        $titles = collect($this->decodeJsonArray($emp->title))
            ->map(fn($t) => strtoupper(trim($t)))
            ->filter()
            ->values();

        if ($titles->isNotEmpty()) {
            $fullName .= ', ' . $titles->implode(', ');
        }

        return $fullName;
    }

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

            // ── Load employees ──────────────────────────────────────────────
            $employeeMap = Employee::select(
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
                ->get()
                ->keyBy('id');

            // ── Load hierarchy edges ────────────────────────────────────────
            $edges = DB::table('employee_hierarchy')
                ->select('parent_id', 'employee_id')
                ->whereNotNull('employee_id')
                ->whereNotNull('parent_id')
                ->get();

            $explicitRootIds = DB::table('employee_hierarchy')
                ->whereNull('parent_id')
                ->whereNotNull('employee_id')
                ->pluck('employee_id')
                ->flip()
                ->map(fn() => true)
                ->toArray();

            // ── Walk up to include ancestor employees when filtering by dept ─
            if ($deptId && $deptId !== 'All') {
                $ids          = $employeeMap->keys()->toArray();
                $parentLookup = $edges->keyBy('employee_id');
                $queue        = $ids;

                while ($queue) {
                    $current  = array_shift($queue);
                    $parentId = $parentLookup[$current]->parent_id ?? null;
                    if ($parentId && !in_array($parentId, $ids)) {
                        $ids[]   = $parentId;
                        $queue[] = $parentId;
                    }
                }

                Employee::whereIn('id', array_diff($ids, $employeeMap->keys()->toArray()))
                    ->get()
                    ->each(fn($emp) => $employeeMap[$emp->id] = $emp);
            }

            // ── Deduplicate & clean edges ───────────────────────────────────
            $edgePairs  = [];
            $cleanEdges = [];

            foreach ($edges as $edge) {
                if ($edge->parent_id == $edge->employee_id) continue;
                $edgePairs[$edge->parent_id . '-' . $edge->employee_id] = $edge;
            }

            foreach ($edgePairs as $key => $edge) {
                $reverseKey = $edge->employee_id . '-' . $edge->parent_id;
                if (!isset($edgePairs[$reverseKey]) || $edge->parent_id < $edge->employee_id) {
                    $cleanEdges[$key] = $edge;
                }
            }

            // ── Build relationship maps ─────────────────────────────────────
            $childrenMap  = [];
            $parentMap    = [];
            $allParentMap = [];

            foreach ($cleanEdges as $edge) {
                $childrenMap[$edge->parent_id][]    = $edge->employee_id;
                $allParentMap[$edge->employee_id][] = $edge->parent_id;
                $parentMap[$edge->employee_id]      ??= $edge->parent_id;
            }

            // ── Helpers ─────────────────────────────────────────────────────
            $resolveIsStaff = function ($emp): bool {
                $roles = collect($this->decodeJsonArray($emp->role_position))
                    ->map(fn($r) => trim(strtolower($r)))->filter()->values();
                return $roles->isEmpty() || $roles->every(fn($r) => in_array($r, ['staff', 'employee']));
            };

            $resolveDept = fn($emp) => collect(
                is_array($emp->department_ids)
                    ? $emp->department_ids
                    : json_decode($emp->department_ids ?? '[]', true)
            )->map(fn($id) => strtoupper($departmentMap[$id] ?? ''))->filter()->implode(', ');

            $formatEmployee = function ($emp, string $name) use (
                $resolveIsStaff, $resolveDept, $allParentMap, $employeeMap
            ) {
                $isStaff = $resolveIsStaff($emp);

                $titleArray = $this->decodeJsonArray($emp->title);
                $titleStr   = collect($titleArray)->map(fn($t) => strtoupper(trim($t)))->filter()->implode(', ');

                $supervisors = collect($allParentMap[$emp->id] ?? [])
                    ->map(function ($pid) use ($employeeMap, $resolveDept) {
                        if (!isset($employeeMap[$pid])) return null;
                        $p = $employeeMap[$pid];
                        $pTitle = collect($this->decodeJsonArray($p->title))
                            ->map(fn($t) => strtoupper(trim($t)))->filter()->implode(', ');
                        return [
                            'employeeId'     => $p->id,
                            'name'           => $this->makeName($p),
                            'prefix'         => strtoupper($p->prefix ?? ''),
                            'first_name'     => strtoupper($p->first_name ?? ''),
                            'middle_name'    => strtoupper($p->middle_name ?? ''),
                            'last_name'      => strtoupper($p->last_name ?? ''),
                            'suffix'         => strtoupper($p->suffix ?? ''),
                            'title'          => $pTitle,
                            'role'           => strtoupper(collect($this->decodeJsonArray($p->role_position))->filter()->implode(', ')),
                            'department'     => $resolveDept($p),
                            'employmentType' => strtoupper($p->employment_type ?? ''),
                            'image'          => $this->resolveAvatar($p->avatar_url),
                        ];
                    })
                    ->filter()->values()->toArray();

                return [
                    'employeeId'     => $emp->id,
                    'name'           => $name,
                    'prefix'         => strtoupper($emp->prefix ?? ''),
                    'first_name'     => strtoupper($emp->first_name ?? ''),
                    'middle_name'    => strtoupper($emp->middle_name ?? ''),
                    'last_name'      => strtoupper($emp->last_name ?? ''),
                    'suffix'         => strtoupper($emp->suffix ?? ''),
                    'title'          => $titleStr,
                    'role'           => strtoupper(collect($this->decodeJsonArray($emp->role_position))->filter()->implode(', ')),
                    'role_position'  => $this->decodeJsonArray($emp->role_position),
                    'department'     => $resolveDept($emp),
                    'employmentType' => strtoupper($emp->employment_type ?? ''),
                    'image'          => $this->resolveAvatar($emp->avatar_url),
                    'border_color'   => $emp->border_color ?? 'gray',
                    'aligned'        => (bool) $emp->aligned,
                    'isStaff'        => $isStaff,
                    'showRole'       => !$isStaff,
                    'showDept'       => !$isStaff,
                    'supervisors'    => $supervisors,
                ];
            };

            $buildStaff = function ($managerId) use (
                $childrenMap, $employeeMap, $showStaffAsNode,
                $formatEmployee, $resolveIsStaff
            ) {
                if ($showStaffAsNode) return [];

                return collect($childrenMap[$managerId] ?? [])
                    ->map(fn($childId) => isset($employeeMap[$childId]) && $resolveIsStaff($employeeMap[$childId])
                        ? ['id' => 'staff-' . $childId, ...$formatEmployee($employeeMap[$childId], $this->makeName($employeeMap[$childId]))]
                        : null
                    )
                    ->filter()->values()->toArray();
            };

            $buildTree = function ($parentId, array $ancestorChain = []) use (
                &$buildTree,
                $childrenMap, $employeeMap,
                $buildStaff, $formatEmployee,
                $showStaffAsNode, $resolveIsStaff
            ) {
                return collect($childrenMap[$parentId] ?? [])
                    ->filter(fn($childId) =>
                        isset($employeeMap[$childId]) &&
                        !in_array($childId, $ancestorChain) &&
                        ($showStaffAsNode || !$resolveIsStaff($employeeMap[$childId]))
                    )
                    ->map(fn($childId) => [
                        'key'      => "node-{$parentId}-{$childId}",
                        'type'     => 'person',
                        'expanded' => $showStaffAsNode ? true : (bool) $employeeMap[$childId]->expanded,
                        'data'     => [
                            ...$formatEmployee($employeeMap[$childId], $this->makeName($employeeMap[$childId])),
                            'staff' => $buildStaff($childId),
                        ],
                        'children' => $buildTree($childId, [...$ancestorChain, $childId]),
                    ])
                    ->values()->toArray();
            };

            // ── Build root nodes ────────────────────────────────────────────
            $nodes = $employeeMap
                ->filter(fn($emp) => isset($explicitRootIds[$emp->id]) || !isset($parentMap[$emp->id]))
                ->map(fn($root) => [
                    'key'      => "node-root-{$root->id}",
                    'type'     => 'person',
                    'expanded' => $showStaffAsNode ? true : (bool) $root->expanded,
                    'data'     => [
                        ...$formatEmployee($root, $this->makeName($root)),
                        'staff' => $buildStaff($root->id),
                    ],
                    'children' => $buildTree($root->id, [$root->id]),
                ])
                ->values()->toArray();

            return response()->json(['nodes' => $nodes]);

        } catch (\Throwable $e) {
            Log::error('ManpowerMapping@tree: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SUMMARY
    |--------------------------------------------------------------------------
    */

    public function summary()
    {
        $counts = DB::table('employees')->selectRaw("
            SUM(CASE WHEN LOWER(employment_type) LIKE '%plantilla%'                                        THEN 1 ELSE 0 END) as plantilla,
            SUM(CASE WHEN LOWER(employment_type) LIKE '%cos%' OR LOWER(employment_type) LIKE '%contract%' THEN 1 ELSE 0 END) as cos,
            SUM(CASE WHEN LOWER(employment_type) LIKE '%consultant%'                                       THEN 1 ELSE 0 END) as consultant
        ")->first();

        $vacant     = PlantillaItem::whereDoesntHave('assignments', fn($q) => $q->whereNull('end_date'))->count();
        $plantilla  = (int) $counts->plantilla;
        $cos        = (int) $counts->cos;
        $consultant = (int) $counts->consultant;

        return response()->json([
            'plantilla'  => $plantilla,
            'cos'        => $cos,
            'consultant' => $consultant,
            'vacant'     => $vacant,
            'total'      => $plantilla + $cos + $consultant,
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
        ->when($divId && $divId !== 'All', fn($q) => $q->where('division_id', $divId))
        ->when($deptId && $deptId !== 'All', fn($q) =>
            $q->whereRaw('JSON_CONTAINS(department_ids, ?)', [json_encode((int) $deptId)])
        )
        ->orderByRaw("
            CASE
                WHEN LOWER(employment_type) LIKE '%plantilla%'                                        THEN 1
                WHEN LOWER(employment_type) LIKE '%cos%' OR LOWER(employment_type) LIKE '%contract%' THEN 2
                WHEN LOWER(employment_type) LIKE '%consultant%'                                       THEN 3
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

            $deptLabel = collect(
                is_array($emp->department_ids)
                    ? $emp->department_ids
                    : json_decode($emp->department_ids ?? '[]', true)
            )->map(fn($id) => strtoupper($departmentMap[$id] ?? ''))->filter()->implode(', ');

            $titleArr = $this->decodeJsonArray($emp->title);
            $titleStr = collect($titleArr)->map(fn($t) => strtoupper(trim($t)))->filter()->implode(', ');

            return [
                'EMPLOYEE NUMBER'   => strtoupper($emp->employee_number ?? ''),
                'PREFIX'            => strtoupper($emp->prefix ?? ''),
                'FIRST NAME'        => strtoupper($emp->first_name ?? ''),
                'MIDDLE NAME'       => strtoupper($emp->middle_name ?? ''),
                'LAST NAME'         => strtoupper($emp->last_name ?? ''),
                'TITLE'             => $titleStr,
                'ROLE POSITION'     => strtoupper(collect($this->decodeJsonArray($emp->role_position))->filter()->implode(', ')),
                'DEPARTMENT'        => $deptLabel,
                'DIVISION'          => strtoupper(optional($emp->division)->name ?? ''),
                'POSITION TITLE'    => strtoupper(optional($plantilla)->title ?? ''),
                'SALARY GRADE'      => strtoupper(optional($sg)->salary_grade ?? ''),
                'STEP'              => strtoupper(optional($step)->step ?? ''),
                'EMPLOYMENT TYPE'   => strtoupper($emp->employment_type ?? ''),
                'EMPLOYMENT STATUS' => strtoupper($emp->employment_status ?? ''),
            ];
        });

        $divisionName = ($divId && $divId !== 'All')
            ? strtoupper(optional($employees->first()?->division)->name ?? '')
            : '';

        $deptName = ($deptId && $deptId !== 'All')
            ? strtoupper($departmentMap[$deptId] ?? '')
            : '';

        $titleParts = array_filter([$divisionName, $deptName]);
        $title = $titleParts
            ? implode(' - ', $titleParts) . ' - EMPLOYEE MAPPING'
            : 'EMPLOYEE MAPPING';

        return response()->json([
            'title'    => $title,
            'division' => $divisionName,
            'report'   => $rows,
        ]);
    }
}