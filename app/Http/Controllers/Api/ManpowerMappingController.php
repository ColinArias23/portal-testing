<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class ManpowerMappingController extends Controller
{
    public function tree()
    {
        $employees = DB::table('employees')->get()->keyBy('id');
        $edges = DB::table('employee_hierarchy')->get();

        // Build parent → children map
        $childrenMap = [];
        foreach ($edges as $e) {
            $childrenMap[$e->parent_id][] = $e->employee_id;
        }

        $makeName = function ($emp) {
            return trim(collect([
                $emp->prefix,
                $emp->first_name,
                $emp->middle_name,
                $emp->last_name,
                $emp->suffix
            ])->filter()->implode(' '));
        };

        // ✅ Build staff modal data
        $buildStaff = function ($managerId) use ($childrenMap, $employees, $makeName) {

            $staff = [];
            $childIds = $childrenMap[$managerId] ?? [];

            foreach ($childIds as $childId) {
                $emp = $employees[$childId] ?? null;
                if (!$emp) continue;

                if (($emp->role_position ?? '') !== 'Staff') continue;

                $staff[] = [
                    'id' => uniqid('staff-'),
                    'employeeId' => $emp->id,
                    'name' => $makeName($emp),
                    'role' => $emp->role_position ?? '',
                    'employmentType' => $emp->employment_type ?? '',
                    'image' => $emp->avatar
                        ?: 'https://cdn3.iconfinder.com/data/icons/avatars-flat/33/man_5-1024.png',
                ];
            }

            return $staff;
        };

        $buildTree = function ($parentId, $path = []) use (
            &$buildTree,
            $childrenMap,
            $employees,
            $makeName,
            $buildStaff
        ) {

            $nodes = [];
            $childIds = $childrenMap[$parentId] ?? [];

            foreach ($childIds as $childId) {

                $emp = $employees[$childId] ?? null;
                if (!$emp) continue;

                // ❌ Skip staff as main node
                if (($emp->role_position ?? '') === 'Staff') {
                    continue;
                }

                $isCircular = in_array($childId, $path, true);

                $node = [
                    'key' => uniqid('node-'),
                    'type' => 'person',
                    'expanded' => true,
                    'data' => [
                        'id' => uniqid('ui-'),
                        'employeeId' => $emp->id,
                        'name' => $makeName($emp),
                        'role' => $emp->role_position ?? '',
                        'employmentType' => $emp->employment_type ?? '',
                        'image' => $emp->avatar
                            ?: 'https://cdn3.iconfinder.com/data/icons/avatars-flat/33/man_5-1024.png',

                        // ✅ modal data preserved
                        'staff' => $buildStaff($emp->id),
                    ],
                ];

                if (!$isCircular) {
                    $node['children'] = $buildTree(
                        $childId,
                        array_merge($path, [$childId])
                    );
                } else {
                    $node['children'] = [];
                }

                $nodes[] = $node;
            }

            return $nodes;
        };

        $nodes = $buildTree(null);

        return response()->json([
            'nodes' => $nodes
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | REPORT (BASED ON MAPPING - NO DUPLICATES)
    |--------------------------------------------------------------------------
    */

    public function report(Request $request)
    {
        $query = DB::table('employees');

        // Optional department filter
        if ($request->filled('department') && $request->department !== 'All') {
            $query->where('department', $request->department);
        }

        $employees = $query->orderBy('id')->get();

        return response()->json([
            'report' => $employees
        ]);
    }
}