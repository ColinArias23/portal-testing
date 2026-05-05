<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\PlantillaPosition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ManpowerMappingController extends Controller
{
    // ─── Constants ────────────────────────────────────────────────────────

    private const DEFAULT_AVATAR = 'https://cdn3.iconfinder.com/data/icons/avatars-flat/33/man_5-1024.png';
    private const STAFF_ROLES    = ['staff', 'employee'];

    // ─── Helpers ──────────────────────────────────────────────────────────

    private function decodeJsonArray(mixed $value): array
    {
        if (is_array($value)) return $value;

        if (is_string($value) && str_starts_with(ltrim($value), '[')) {
            return json_decode($value, true) ?? [];
        }

        return $value !== null && $value !== '' ? [$value] : [];
    }

    private function resolveAvatar(?string $avatarPath): string
    {
        if (! $avatarPath) return self::DEFAULT_AVATAR;

        if (str_starts_with($avatarPath, 'http://') || str_starts_with($avatarPath, 'https://')) {
            return $avatarPath;
        }

        $absolute = storage_path('app/public/' . $avatarPath);

        return file_exists($absolute)
            ? asset('storage/' . $avatarPath)
            : self::DEFAULT_AVATAR;
    }

    private function buildDisplayName(object $emp): string
    {
        $parts = array_filter([
            $emp->prefix      ? strtoupper(trim($emp->prefix))                             : null,
            $emp->first_name  ? strtoupper(trim($emp->first_name))                         : null,
            $emp->middle_name ? strtoupper(mb_substr(trim($emp->middle_name), 0, 1)) . '.' : null,
            $emp->last_name   ? strtoupper(trim($emp->last_name))                          : null,
            $emp->suffix      ? strtoupper(trim($emp->suffix))                             : null,
        ]);

        $fullName = implode(' ', $parts);

        $titles = collect($this->decodeJsonArray($emp->title))
            ->map(fn ($t) => strtoupper(trim($t)))
            ->filter()
            ->values();

        return $titles->isNotEmpty()
            ? $fullName . ', ' . $titles->implode(', ')
            : $fullName;
    }

    private function resolveDepartmentLabel(object $emp, \Illuminate\Support\Collection $departmentMap): string
    {
        $ids = is_array($emp->department_ids)
            ? $emp->department_ids
            : json_decode($emp->department_ids ?? '[]', true);

        return collect($ids)
            ->map(fn ($id) => strtoupper($departmentMap->get($id) ?? ''))
            ->filter()
            ->implode(', ');
    }

    private function isStaffOnly(object $emp): bool
    {
        $roles = collect($this->decodeJsonArray($emp->role_position))
            ->map(fn ($r) => strtolower(trim($r)))
            ->filter();

        return $roles->isEmpty()
            || $roles->every(fn ($r) => in_array($r, self::STAFF_ROLES, true));
    }

    private function serializeEmployee(
        object $emp,
        string $displayName,
        array $allParentMap,
        \Illuminate\Support\Collection $departmentMap,
        \Illuminate\Support\Collection $employeeMap,
    ): array {
        $isStaff  = $this->isStaffOnly($emp);
        $titleStr = collect($this->decodeJsonArray($emp->title))
            ->map(fn ($t) => strtoupper(trim($t)))
            ->filter()
            ->implode(', ');

        $supervisors = collect($allParentMap[$emp->id] ?? [])
            ->map(function ($parentId) use ($employeeMap, $departmentMap) {
                $parent = $employeeMap->get($parentId);
                if (! $parent) return null;

                return [
                    'employeeId'     => $parent->id,
                    'name'           => $this->buildDisplayName($parent),
                    'prefix'         => strtoupper($parent->prefix ?? ''),
                    'first_name'     => strtoupper($parent->first_name ?? ''),
                    'middle_name'    => strtoupper($parent->middle_name ?? ''),
                    'last_name'      => strtoupper($parent->last_name ?? ''),
                    'suffix'         => strtoupper($parent->suffix ?? ''),
                    'title'          => collect($this->decodeJsonArray($parent->title))->map(fn ($t) => strtoupper(trim($t)))->filter()->implode(', '),
                    'role'           => strtoupper(collect($this->decodeJsonArray($parent->role_position))->filter()->implode(', ')),
                    'department'     => $this->resolveDepartmentLabel($parent, $departmentMap),
                    'employmentType' => strtoupper($parent->employment_type ?? ''),
                    'image'          => $this->resolveAvatar($parent->avatar_url),
                ];
            })
            ->filter()
            ->values()
            ->toArray();

        return [
            'employeeId'     => $emp->id,
            'name'           => $displayName,
            'prefix'         => strtoupper($emp->prefix ?? ''),
            'first_name'     => strtoupper($emp->first_name ?? ''),
            'middle_name'    => strtoupper($emp->middle_name ?? ''),
            'last_name'      => strtoupper($emp->last_name ?? ''),
            'suffix'         => strtoupper($emp->suffix ?? ''),
            'title'          => $titleStr,
            'role'           => strtoupper(collect($this->decodeJsonArray($emp->role_position))->filter()->implode(', ')),
            'role_position'  => $this->decodeJsonArray($emp->role_position),
            'department'     => $this->resolveDepartmentLabel($emp, $departmentMap),
            'employmentType' => strtoupper($emp->employment_type ?? ''),
            'image'          => $this->resolveAvatar($emp->avatar_url),
            'border_color'   => $emp->border_color ?? 'gray',
            'aligned'        => (bool) $emp->aligned,
            'isStaff'        => $isStaff,
            'showRole'       => ! $isStaff,
            'showDept'       => ! $isStaff,
            'supervisors'    => $supervisors,
        ];
    }

    private function serializeVacantPosition(PlantillaPosition $pos): array
    {
        $item = $pos->item;
        $sg   = $pos->salaryGrade;

        return [
            'positionId'       => $pos->id,
            'itemNumber'       => $pos->item_number,
            'positionTitle'    => strtoupper($pos->position_title ?? $item?->title ?? ''),
            'status'           => $pos->computed_status,
            'salaryGrade'      => $sg ? [
                'id'             => $sg->id,
                'salary_grade'   => $sg->salary_grade,
                'monthly_salary' => $sg->monthly_salary,
            ] : null,
            'division'         => $item?->division ? [
                'id'   => $item->division->id,
                'name' => strtoupper($item->division->name),
            ] : null,
            'department_ids'   => $item?->department_ids ?? [],
            'vacatedAt'        => $pos->vacated_at,
            'vacancyNotes'     => $pos->vacancy_notes,
            'requiresApproval' => $pos->requires_approval,
        ];
    }

    // ─── Shared Query Helpers ─────────────────────────────────────────────

    private function employeesByType(string $keyword)
    {
        return Employee::whereNotNull('employment_type')
            ->whereRaw('LOWER(employment_type) LIKE ?', ['%' . strtolower($keyword) . '%']);
    }

    private function cosEmployees()
    {
        return Employee::whereNotNull('employment_type')
            ->where(fn ($q) =>
                $q->whereRaw('LOWER(employment_type) LIKE ?', ['%cos%'])
                  ->orWhereRaw('LOWER(employment_type) LIKE ?', ['%contract%'])
            );
    }

    private function vacantPositionsQuery(mixed $deptId = null, mixed $divId = null)
    {
        $q = PlantillaPosition::vacant()->with(['item.division', 'salaryGrade']);

        if ($divId && $divId !== 'All') {
            $q->forDivision((int) $divId);
        }

        if ($deptId && $deptId !== 'All') {
            $q->forDepartment((int) $deptId);
        }

        return $q;
    }

    // ─── Org-chart Tree ───────────────────────────────────────────────────

    /**
     * GET /api/manpower/tree?department_id=&division_id=
     */
    public function tree(Request $request): JsonResponse
    {
        try {
            $deptId         = $request->input('department_id');
            $divId          = $request->input('division_id');
            $filterActive   = ($deptId && $deptId !== 'All') || ($divId && $divId !== 'All');
            $deptFilterOnly = $deptId && $deptId !== 'All';

            $departmentMap = Department::pluck('name', 'id');

            $employeeMap = Employee::select(
                    'id', 'department_ids', 'division_id',
                    'prefix', 'first_name', 'middle_name', 'last_name', 'suffix',
                    'title', 'role_position', 'employment_type',
                    'avatar_url', 'border_color', 'aligned', 'expanded'
                )
                ->when($deptFilterOnly, fn ($q) =>
                    $q->whereRaw('JSON_CONTAINS(department_ids, ?)', [json_encode((int) $deptId)])
                )
                ->when($divId && $divId !== 'All', fn ($q) =>
                    $q->where('division_id', $divId)
                )
                ->get()
                ->keyBy('id');

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
                ->map(fn () => true)
                ->toArray();

            if ($deptFilterOnly) {
                $this->hydrateAncestors($employeeMap, $edges);
            }

            $cleanEdges = $this->deduplicateEdges($edges);

            [$childrenMap, $parentMap, $allParentMap] = $this->buildAdjacencyMaps($cleanEdges);

            $nodes = $employeeMap
                ->filter(fn ($emp) => isset($explicitRootIds[$emp->id]) || ! isset($parentMap[$emp->id]))
                ->map(fn ($root) => $this->buildRootNode(
                    $root, $filterActive, $childrenMap, $employeeMap, $allParentMap, $departmentMap
                ))
                ->values()
                ->toArray();

            // Vacant positions scoped to same filter as the tree
            $vacantPositions = $this->vacantPositionsQuery($deptId, $divId)
                ->get()
                ->map(fn ($pos) => $this->serializeVacantPosition($pos))
                ->values()
                ->toArray();

            return response()->json([
                'nodes'            => $nodes,
                'vacant_positions' => $vacantPositions,
            ]);

        } catch (\Throwable $e) {
            Log::error('ManpowerMappingController@tree: ' . $e->getMessage(), [
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

    // ─── Manpower Counts ──────────────────────────────────────────────────

    public function plantillaCount(): JsonResponse
    {
        return response()->json(['count' => $this->employeesByType('plantilla')->count()]);
    }

    public function cosCount(): JsonResponse
    {
        return response()->json(['count' => $this->cosEmployees()->count()]);
    }

    public function consultantCount(): JsonResponse
    {
        return response()->json(['count' => $this->employeesByType('consultant')->count()]);
    }

    public function vacantCount(): JsonResponse
    {
        return response()->json(['count' => PlantillaPosition::vacant()->count()]);
    }

    // ─── Summary ──────────────────────────────────────────────────────────

    public function summary(): JsonResponse
    {
        $counts = DB::table('employees')
            ->selectRaw("
                SUM(CASE WHEN LOWER(employment_type) LIKE '%plantilla%'                                        THEN 1 ELSE 0 END) AS plantilla,
                SUM(CASE WHEN LOWER(employment_type) LIKE '%cos%' OR LOWER(employment_type) LIKE '%contract%' THEN 1 ELSE 0 END) AS cos,
                SUM(CASE WHEN LOWER(employment_type) LIKE '%consultant%'                                       THEN 1 ELSE 0 END) AS consultant
            ")
            ->first();

        $plantilla  = (int) $counts->plantilla;
        $cos        = (int) $counts->cos;
        $consultant = (int) $counts->consultant;

        return response()->json([
            'plantilla'  => $plantilla,
            'cos'        => $cos,
            'consultant' => $consultant,
            'vacant'     => PlantillaPosition::vacant()->count(),
            'unfilled'   => PlantillaPosition::unfilled()->count(),
            'total'      => $plantilla + $cos + $consultant,
        ]);
    }

    // ─── Overstaffing ─────────────────────────────────────────────────────

    public function overstaffed(): JsonResponse
    {
        $positions = PlantillaPosition::overstaffed()
            ->with(['item.division', 'salaryGrade'])
            ->get()
            ->map(fn ($pos) => array_merge($pos->toArray(), [
                'computed_status' => $pos->computed_status,
                'division'        => $pos->item?->division?->only(['id', 'name']),
                'department_ids'  => $pos->item?->department_ids ?? [],
            ]));

        return response()->json([
            'count' => $positions->count(),
            'items' => $positions,
        ]);
    }

    // ─── Division Analytics ───────────────────────────────────────────────

    /**
     * GET /api/manpower/division-analytics
     *
     * Per-division breakdown: total, filled, vacant, unfilled, overstaffed.
     * Single eager-loaded query — no N+1.
     */
    public function divisionAnalytics(): JsonResponse
    {
        $divisions = Division::with([
            'plantillaItems.positions' => fn ($q) => $q->withCount([
                'assignments as active_count' => fn ($qq) => $qq->whereNull('end_date'),
            ]),
        ])->get();

        $result = $divisions->map(function ($division) {
            $positions   = $division->plantillaItems->flatMap->positions;
            $total       = $positions->count();
            $filled      = $positions->where('active_count', '>=', 1)->count();
            $overstaffed = $positions->where('active_count', '>', 1)->count();
            $unfilled    = $positions->where('requires_approval', true)->where('active_count', 0)->count();
            $vacant      = $positions->where('requires_approval', false)->where('active_count', 0)->count();

            return [
                'division_id'   => $division->id,
                'division_name' => strtoupper($division->name),
                'total_slots'   => $total,
                'filled'        => $filled,
                'vacant'        => $vacant,
                'unfilled'      => $unfilled,
                'overstaffed'   => $overstaffed,
                'vacant_slots'  => $positions
                    ->where('requires_approval', false)
                    ->where('active_count', 0)
                    ->map(fn ($pos) => [
                        'id'             => $pos->id,
                        'item_number'    => $pos->item_number,
                        'position_title' => strtoupper($pos->position_title ?? ''),
                        'department_ids' => $pos->item?->department_ids ?? [],
                    ])
                    ->values(),
            ];
        });

        return response()->json($result);
    }

    // ─── Tabular Report ───────────────────────────────────────────────────

    /**
     * GET /api/manpower/report?department_id=&division_id=
     *
     * Returns employee rows + vacant slot rows for export.
     */
    public function report(Request $request): JsonResponse
    {
        $deptId = $request->input('department_id');
        $divId  = $request->input('division_id');

        $employees = Employee::with([
            'division',
            'primaryAssignment.plantillaPosition.salaryGrade',
            'primaryAssignment.stepIncrement',
        ])
        ->when($divId  && $divId  !== 'All', fn ($q) => $q->where('division_id', $divId))
        ->when($deptId && $deptId !== 'All', fn ($q) =>
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

        $employeeRows = $employees->map(function ($emp) use ($departmentMap) {
            $assignment = $emp->primaryAssignment;
            $position   = optional($assignment)->plantillaPosition;
            $sg         = optional($position)->salaryGrade;
            $step       = optional($assignment)->stepIncrement;

            $deptLabel = collect(
                is_array($emp->department_ids)
                    ? $emp->department_ids
                    : json_decode($emp->department_ids ?? '[]', true)
            )->map(fn ($id) => strtoupper($departmentMap[$id] ?? ''))->filter()->implode(', ');

            return [
                // 'type'              => 'employee',
                'EMPLOYEE NUMBER'   => strtoupper($emp->employee_number ?? ''),
                'PREFIX'            => strtoupper($emp->prefix ?? ''),
                'FIRST NAME'        => strtoupper($emp->first_name ?? ''),
                'MIDDLE NAME'       => strtoupper($emp->middle_name ?? ''),
                'LAST NAME'         => strtoupper($emp->last_name ?? ''),
                'TITLE'             => collect($this->decodeJsonArray($emp->title))->map(fn ($t) => strtoupper(trim($t)))->filter()->implode(', '),
                'ROLE POSITION'     => strtoupper(collect($this->decodeJsonArray($emp->role_position))->filter()->implode(', ')),
                'DEPARTMENT'        => $deptLabel,
                'DIVISION'          => strtoupper(optional($emp->division)->name ?? ''),
                'POSITION TITLE'    => strtoupper(optional($position)->position_title ?? ''),
                'SALARY GRADE'      => strtoupper(optional($sg)->salary_grade ?? ''),
                'STEP'              => strtoupper(optional($step)->step ?? ''),
                'EMPLOYMENT TYPE'   => strtoupper($emp->employment_type ?? ''),
                'EMPLOYMENT STATUS' => strtoupper($emp->employment_status ?? ''),
                'SLOT STATUS'       => strtoupper(optional($position)->computed_status ?? ''),
            ];
        });

        $vacantRows = $this->vacantPositionsQuery($deptId, $divId)
            ->get()
            ->map(function ($pos) use ($departmentMap) {
                $sg          = $pos->salaryGrade;
                $item        = $pos->item;
                $deptLabel   = collect($item?->department_ids ?? [])
                    ->map(fn ($id) => strtoupper($departmentMap[$id] ?? ''))
                    ->filter()
                    ->implode(', ');

                return [
                    'type'              => 'vacant',
                    'EMPLOYEE NUMBER'   => '',
                    'PREFIX'            => '',
                    'FIRST NAME'        => '',
                    'MIDDLE NAME'       => '',
                    'LAST NAME'         => '(VACANT)',
                    'TITLE'             => '',
                    'ROLE POSITION'     => '',
                    'DEPARTMENT'        => $deptLabel,
                    'DIVISION'          => strtoupper($item?->division?->name ?? ''),
                    'POSITION TITLE'    => strtoupper($pos->position_title ?? $item?->title ?? ''),
                    'SALARY GRADE'      => strtoupper($sg?->salary_grade ?? ''),
                    'STEP'              => '',
                    'EMPLOYMENT TYPE'   => '',
                    'EMPLOYMENT STATUS' => '',
                    'SLOT STATUS'       => 'VACANT',
                ];
            });

        [$divisionName, $deptName] = $this->resolveReportTitle(
            $divId, $deptId, $employees->first()?->division, $departmentMap
        );

        $titleParts = array_filter([$divisionName, $deptName]);

        return response()->json([
            'title'          => $titleParts
                ? implode(' - ', $titleParts) . ' - EMPLOYEE MAPPING'
                : 'EMPLOYEE MAPPING',
            'division'       => $divisionName,
            'employee_count' => $employeeRows->count(),
            'vacant_count'   => $vacantRows->count(),
            'report'         => $employeeRows->concat($vacantRows)->values(),
        ]);
    }

    // ─── Tree Utilities ───────────────────────────────────────────────────

    private function hydrateAncestors(
        \Illuminate\Support\Collection &$employeeMap,
        \Illuminate\Support\Collection $edges
    ): void {
        $parentLookup = $edges->keyBy('employee_id');
        $queue        = $employeeMap->keys()->all();
        $seen         = array_flip($queue);

        while ($queue) {
            $current    = array_shift($queue);
            $parentEdge = $parentLookup->get($current);

            if (! $parentEdge) continue;

            $parentId = (int) $parentEdge->parent_id;

            if (! isset($seen[$parentId])) {
                $seen[$parentId] = true;
                $queue[]         = $parentId;
            }
        }

        $missing = array_diff(array_keys($seen), $employeeMap->keys()->all());

        if ($missing) {
            Employee::select(
                    'id', 'department_ids', 'division_id',
                    'prefix', 'first_name', 'middle_name', 'last_name', 'suffix',
                    'title', 'role_position', 'employment_type',
                    'avatar_url', 'border_color', 'aligned', 'expanded'
                )
                ->whereIn('id', $missing)
                ->get()
                ->each(fn ($emp) => $employeeMap->put($emp->id, $emp));
        }
    }

    private function deduplicateEdges(\Illuminate\Support\Collection $edges): \Illuminate\Support\Collection
    {
        $pairs = $edges
            ->filter(fn ($e) => $e->parent_id !== $e->employee_id)
            ->keyBy(fn ($e) => $e->parent_id . '-' . $e->employee_id);

        return $pairs->filter(function ($edge, $key) use ($pairs) {
            $reverseKey = $edge->employee_id . '-' . $edge->parent_id;
            return ! $pairs->has($reverseKey) || $edge->parent_id < $edge->employee_id;
        });
    }

    private function buildAdjacencyMaps(\Illuminate\Support\Collection $edges): array
    {
        $childrenMap  = [];
        $parentMap    = [];
        $allParentMap = [];

        foreach ($edges as $edge) {
            $childrenMap[$edge->parent_id][]    = $edge->employee_id;
            $allParentMap[$edge->employee_id][] = $edge->parent_id;
            $parentMap[$edge->employee_id]      ??= $edge->parent_id;
        }

        return [$childrenMap, $parentMap, $allParentMap];
    }

    private function resolveReportTitle(
        mixed $divId,
        mixed $deptId,
        mixed $firstDivision,
        \Illuminate\Support\Collection $departmentMap
    ): array {
        return [
            ($divId && $divId !== 'All') ? strtoupper(optional($firstDivision)->name ?? '') : '',
            ($deptId && $deptId !== 'All') ? strtoupper($departmentMap->get($deptId) ?? '') : '',
        ];
    }

    private function buildRootNode(
        object $root,
        bool $filterActive,
        array $childrenMap,
        \Illuminate\Support\Collection $employeeMap,
        array $allParentMap,
        \Illuminate\Support\Collection $departmentMap
    ): array {
        $serialized = $this->serializeEmployee(
            $root, $this->buildDisplayName($root), $allParentMap, $departmentMap, $employeeMap
        );

        return [
            'key'      => "node-root-{$root->id}",
            'type'     => 'person',
            'expanded' => $filterActive ? true : (bool) $root->expanded,
            'data'     => array_merge($serialized, [
                'staff' => $this->buildStaffNodes(
                    $filterActive, $childrenMap[$root->id] ?? [], $employeeMap, $allParentMap, $departmentMap
                ),
            ]),
            'children' => $this->buildTreeNode(
                $root->id, [], $filterActive, $childrenMap, $employeeMap, $allParentMap, $departmentMap
            ),
        ];
    }

    private function buildTreeNode(
        int $parentId,
        array $ancestorChain,
        bool $filterActive,
        array $childrenMap,
        \Illuminate\Support\Collection $employeeMap,
        array $allParentMap,
        \Illuminate\Support\Collection $departmentMap
    ): array {
        return collect($childrenMap[$parentId] ?? [])
            ->filter(fn ($childId) =>
                $employeeMap->has($childId) &&
                ! in_array($childId, $ancestorChain, true) &&
                ($filterActive || ! $this->isStaffOnly($employeeMap->get($childId)))
            )
            ->map(function ($childId) use (
                $parentId, $ancestorChain, $filterActive,
                $childrenMap, $employeeMap, $allParentMap, $departmentMap
            ) {
                $emp        = $employeeMap->get($childId);
                $serialized = $this->serializeEmployee(
                    $emp, $this->buildDisplayName($emp), $allParentMap, $departmentMap, $employeeMap
                );

                return [
                    'key'      => "node-{$parentId}-{$childId}",
                    'type'     => 'person',
                    'expanded' => $filterActive ? true : (bool) $emp->expanded,
                    'data'     => array_merge($serialized, [
                        'staff' => $this->buildStaffNodes(
                            $filterActive, $childrenMap[$childId] ?? [], $employeeMap, $allParentMap, $departmentMap
                        ),
                    ]),
                    'children' => $this->buildTreeNode(
                        $childId, [...$ancestorChain, $childId], $filterActive,
                        $childrenMap, $employeeMap, $allParentMap, $departmentMap
                    ),
                ];
            })
            ->values()
            ->toArray();
    }

    private function buildStaffNodes(
        bool $filterActive,
        array $childIds,
        \Illuminate\Support\Collection $employeeMap,
        array $allParentMap,
        \Illuminate\Support\Collection $departmentMap
    ): array {
        if ($filterActive) return [];

        return collect($childIds)
            ->filter(fn ($id) => $employeeMap->has($id) && $this->isStaffOnly($employeeMap->get($id)))
            ->map(function ($id) use ($employeeMap, $allParentMap, $departmentMap) {
                $emp = $employeeMap->get($id);
                return [
                    'id' => 'staff-' . $id,
                    ...$this->serializeEmployee(
                        $emp, $this->buildDisplayName($emp), $allParentMap, $departmentMap, $employeeMap
                    ),
                ];
            })
            ->values()
            ->toArray();
    }
}