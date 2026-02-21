<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\EmployeeHierarchy;
use App\Models\PlantillaItem;
use Illuminate\Http\Request;

class OrgChartController extends Controller
{
    public function tree(Request $request)
    {
        $divisionId   = $request->query('division_id');     // ?division_id=3
        $departmentId = $request->query('department_id');   // ?department_id=2

        // ===============================
        // 1) EMPLOYEES + PRIMARY DIVISION
        // ===============================
        $employees = Employee::query()
            ->with([
                'divisions' => fn ($q) => $q->wherePivot('is_primary', true)->with('department'),
            ])
            ->get();

        $byId = $employees->keyBy('id');

        // ===============================
        // 2) HIERARCHY EDGES
        // ===============================
        $edgesQuery = EmployeeHierarchy::query();

        if ($divisionId) {
            $edgesQuery->where('division_id', $divisionId);
        }

        $edges = $edgesQuery->get(['parent_id', 'child_id', 'division_id']);

        // adjacency list
        $childrenMap = [];
        $allChildIds = [];

        foreach ($edges as $edge) {
            $p = (int) $edge->parent_id;
            $c = (int) $edge->child_id;

            $childrenMap[$p][] = $c;
            $allChildIds[$c] = true;
        }

        // roots
        $allParentIds = array_keys($childrenMap);

        $rootIds = [];
        foreach ($allParentIds as $pid) {
            if (!isset($allChildIds[$pid])) {
                $rootIds[] = $pid;
            }
        }

        // standalone employees
        $standalone = $employees
            ->pluck('id')
            ->filter(fn ($id) => !in_array($id, $allParentIds) && !isset($allChildIds[$id]))
            ->values()
            ->all();

        $rootIds = array_values(array_unique(array_merge($rootIds, $standalone)));

        // department filter (by department name)
        $deptNameFilter = null;
        if ($departmentId) {
            $dept = Department::find($departmentId);
            $deptNameFilter = $dept?->name;
        }

        // ===============================
        // 3) BUILD EMPLOYEE TREE
        // ===============================
        $build = function ($id) use (&$build, $byId, $childrenMap, $deptNameFilter) {
            if (!isset($byId[$id])) return null;

            $emp  = $byId[$id];
            $node = $this->toNode($emp);

            $childIds = $childrenMap[$id] ?? [];
            $children = [];

            foreach ($childIds as $cid) {
                $childNode = $build($cid);
                if ($childNode) $children[] = $childNode;
            }

            $node['children'] = $children;

            // filter by department: keep if matches OR has children
            if ($deptNameFilter) {
                $matches = ($node['data']['department'] ?? null) === $deptNameFilter;
                if (!$matches && count($children) === 0) {
                    return null;
                }
                $node['expanded'] = true;
            }

            return $node;
        };

        $tree = [];
        foreach ($rootIds as $rid) {
            $root = $build($rid);
            if ($root) $tree[] = $root;
        }

        // ===============================
        // 4) ADD VACANT POSITIONS POOL
        // ===============================
        $vacantStatuses = ['UNFILLED', 'FOR_PSB', 'PENDING_APPOINTMENT', 'IMPENDING'];

        $vacants = PlantillaItem::query()
            ->whereIn('status', $vacantStatuses)
            ->orderBy('item_number')
            ->get(['id', 'item_number', 'status', 'title']);

        // If dept filter is selected: optional filter vacants by keyword match (TEMP)
        // (Since plantilla_items has no department_id)
        if ($deptNameFilter) {
            $vacants = $vacants->filter(function ($v) use ($deptNameFilter) {
                // very light heuristic: match dept name in title if you encode it
                return stripos($v->title, $deptNameFilter) !== false;
            })->values();
        }

        // Make a root node for vacant items
        $vacantRoot = [
            'id' => 'VACANT_POOL',
            'expanded' => true,
            'data' => [
                'department' => $deptNameFilter ?? 'All',
                'label' => 'Vacant Positions',
                'firstName' => null,
                'lastName' => null,
                'name' => 'VACANT POSITIONS',
                'role' => 'Plantilla Items',
                'position' => 'Plantilla Items',
                'image' => null,
                'avatar' => null,
                'employmentType' => 'VACANT',
                'borderColor' => '#9CA3AF',
                'notes' => null,
                'staff' => [],
                'employeeId' => null,
                'employeeNumber' => null,
            ],
            'children' => $vacants->map(function ($p) {
                return $this->toVacantNode($p);
            })->toArray(),
        ];

        // Add root only if may vacant
        if (count($vacantRoot['children']) > 0) {
            $tree[] = $vacantRoot;
        }

        return response()->json(['nodes' => $tree]);
    }

    public function departments()
    {
        $rows = Department::query()
            ->select(['id', 'code', 'name'])
            ->orderBy('name')
            ->get();

        return response()->json(['departments' => $rows]);
    }

    public function divisions(Request $request)
    {
        $rows = Division::query()
            ->with('department:id,code,name')
            ->select(['id', 'department_id', 'code', 'name'])
            ->orderBy('name')
            ->get();

        return response()->json(['divisions' => $rows]);
    }

    private function toNode(Employee $e): array
    {
        $primaryDivision = $e->divisions?->first();
        $departmentName  = $primaryDivision?->department?->name;

        $first = $e->first_name ?? '';
        $last  = $e->last_name ?? '';
        $full  = trim($first . ' ' . $last);

        return [
            'id'       => (string) $e->id,
            'expanded' => (bool) ($e->expanded ?? false),

            'data' => [
                'department' => $departmentName ?? 'Unassigned',
                'label'      => $departmentName ?? 'Unassigned',

                'firstName' => $e->first_name,
                'lastName'  => $e->last_name,
                'name'      => $full ?: 'Vacant',

                'role'     => $e->position_designation ?? $e->title ?? null,
                'position' => $e->position_designation ?? $e->title ?? null,

                'image'  => $e->avatar,
                'avatar' => $e->avatar,

                'employmentType' => $e->employment_type ?? 'VACANT',

                'borderColor' => $e->border_color,
                'notes'       => $e->notes,

                'staff' => [],

                'employeeId'     => $e->employee_number,
                'employeeNumber' => $e->employee_number,
            ],

            'children' => [],
        ];
    }

    private function toVacantNode($p): array
    {
        return [
            'id' => 'PI-' . $p->id,
            'expanded' => false,
            'data' => [
                'department' => 'Unassigned', // since no dept link yet
                'label' => 'Vacant',

                'firstName' => null,
                'lastName'  => null,
                'name'      => 'Vacant',

                'role' => $p->title,
                'position' => $p->title,

                'image' => null,
                'avatar' => null,

                'employmentType' => 'VACANT',

                'borderColor' => '#9CA3AF',
                'notes' => 'Plantilla Item: '.$p->item_number.' | Status: '.$p->status,

                'staff' => [],

                'employeeId' => $p->item_number,
                'employeeNumber' => $p->item_number,
            ],
            'children' => [],
        ];
    }
}