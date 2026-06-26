<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\UserActivated;
use App\Events\PermissionsUpdated;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:SuperAdmin')->only([
            'pendingCount',
            'pendingUsers',
            'approvedUsers',
            'updatePermissions',
            'updateRole',
            'activate',
            'bulkActivate',
        ]);
    }

    public function pendingCount()
    {
        $count = User::where('approval_status', 'PENDING')->count();
        return response()->json(['count' => $count]);
    }

    public function pendingUsers()
    {
        $users = User::where('approval_status', 'PENDING')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($users);
    }

    public function approvedUsers()
    {
        $currentUserId = Auth::id();

        $users = User::with('employee')
            ->where('approval_status', 'APPROVED')
            ->where('id', '!=', $currentUserId)
            ->orderBy('approved_at', 'desc')
            ->get()
            ->filter(fn($u) => !$u->hasRole('SuperAdmin'))
            ->values()
            ->map(fn($u) => [
                'id'            => $u->id,
                'username'      => $u->username,
                'email'         => $u->email,
                'name'          => $u->employee?->full_name ?? $u->username,
                'approved_at'   => $u->approved_at,
                'roles'         => $u->getRoleNames(),
                'role_position' => $u->employee?->role_position ?? [],
                'permissions'   => $u->getDirectPermissions()->pluck('name')->values(),
            ]);

        return response()->json($users);
    }

    public function updatePermissions(Request $request, User $user)
    {
        if ($user->approval_status !== 'APPROVED') {
            return response()->json([
                'message' => 'Cannot update permissions for unapproved users.',
            ], 422);
        }

        $request->validate([
            'permissions'   => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $before = $user->getDirectPermissions()->pluck('name')->values()->toArray();

        $user->syncPermissions($request->permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->unsetRelation('permissions');
        $user->unsetRelation('roles');
        $user->refresh();

        $updatedPermissions = $user->getDirectPermissions()->pluck('name')->values();

        ActivityLog::record(
            action: 'user.permissions_updated',
            userId: Auth::id(),
            subjectType: User::class,
            subjectId: $user->id,
            subjectLabel: $user->username,
            payload: [
                'target_username' => $user->username,
                'before' => $before,
                'after'  => $updatedPermissions->toArray(),
            ],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        broadcast(new PermissionsUpdated($user->id));

        return response()->json([
            'message'     => 'Permissions updated successfully.',
            'permissions' => $updatedPermissions,
        ]);
    }

    public function updateRole(Request $request, User $user)
    {
        if ($user->approval_status !== 'APPROVED') {
            return response()->json([
                'message' => 'Cannot assign roles to unapproved users.',
            ], 422);
        }

        $request->validate([
            'role' => ['nullable', 'string', 'max:100'],
        ]);

        $roleName = $request->input('role');

        if ($roleName && strtolower($roleName) === 'superadmin') {
            return response()->json(['message' => 'Cannot assign SuperAdmin role.'], 403);
        }

        $before = $user->getRoleNames()->toArray();

        if ($roleName && strtolower($roleName) !== 'none') {
            $role = Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web']
            );
            $user->syncRoles([$role->name]);

            $user->syncPermissions($role->permissions);
        } else {
            $toRemove = $user->getRoleNames()
                ->filter(fn($r) => strtolower($r) !== 'superadmin')
                ->toArray();
            if (!empty($toRemove)) {
                $user->removeRole(...$toRemove);
            }
            $user->syncPermissions([]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->refresh();

        ActivityLog::record(
            action: 'user.role_updated',
            userId: Auth::id(),
            subjectType: User::class,
            subjectId: $user->id,
            subjectLabel: $user->username,
            payload: [
                'target_username' => $user->username,
                'before' => $before,
                'after'  => $user->getRoleNames()->toArray(),
            ],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        broadcast(new PermissionsUpdated($user->id));

        return response()->json([
            'message' => 'Role updated successfully.',
            'roles'   => $user->getRoleNames(),
        ]);
    }

    public function activate(Request $request, User $user)
    {
        DB::transaction(function () use ($user) {
            $this->linkEmployee($user);

            DB::table('users')->where('id', $user->id)->update([
                'approval_status' => 'APPROVED',
                'approved_at'     => now()->toDateTimeString(),
                'approved_by'     => Auth::id(),
            ]);

            if ($user->employee_id) {
                DB::table('employees')->where('id', $user->employee_id)->update([
                    'employment_status' => 'active',
                    'updated_at'        => now()->toDateTimeString(),
                ]);
            }

            $user->refresh();
            if ($user->roles->isEmpty()) {
                $staffRole = Role::firstOrCreate(['name' => 'Staff', 'guard_name' => 'web']);
                $user->assignRole($staffRole);
                $user->syncPermissions($staffRole->permissions);
            }
        });

        $fresh = User::with('employee.info')->find($user->id);

        ActivityLog::record(
            action: 'user.activated',
            userId: Auth::id(),
            subjectType: User::class,
            subjectId: $user->id,
            subjectLabel: $fresh->username,
            payload: [
                'target_username' => $fresh->username,
                'employee_id'     => $fresh->employee_id,
            ],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        broadcast(new UserActivated($user->id, $fresh->employee_id));

        return response()->json([
            'message' => 'User activated successfully.',
            'user'    => $fresh,
        ]);
    }

    public function bulkActivate(Request $request)
    {
        $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:users,id'],
        ]);

        $users = User::whereIn('id', $request->ids)
            ->where('approval_status', 'PENDING')
            ->get();

        if ($users->isEmpty()) {
            return response()->json([
                'message' => 'No pending users found with the provided IDs.',
            ], 422);
        }

        $currentUserId = Auth::id();

        $staffRole = Role::firstOrCreate(['name' => 'Staff', 'guard_name' => 'web']);

        DB::transaction(function () use ($users, $currentUserId, $staffRole) {
            foreach ($users as $user) {
                $this->linkEmployee($user);

                DB::table('users')->where('id', $user->id)->update([
                    'approval_status' => 'APPROVED',
                    'approved_at'     => now()->toDateTimeString(),
                    'approved_by'     => $currentUserId,
                ]);

                $employeeId = $user->employee_id
                    ?? DB::table('users')->where('id', $user->id)->value('employee_id');

                if ($employeeId) {
                    DB::table('employees')->where('id', $employeeId)->update([
                        'employment_status' => 'active',
                        'updated_at'        => now()->toDateTimeString(),
                    ]);
                }

                $user->refresh();
                if ($user->roles->isEmpty()) {
                    $user->assignRole($staffRole);
                    $user->syncPermissions($staffRole->permissions);
                }
            }
        });

        $fresh = User::whereIn('id', $request->ids)
            ->with('employee.info')
            ->get()
            ->keyBy('id');

        foreach ($users as $user) {
            $userData = $fresh[$user->id];

            ActivityLog::record(
                action: 'user.activated',
                userId: Auth::id(),
                subjectType: User::class,
                subjectId: $user->id,
                subjectLabel: $userData->username,
                payload: [
                    'target_username' => $userData->username,
                    'employee_id'     => $userData->employee_id,
                    'bulk'            => true,
                ],
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );

            broadcast(new UserActivated($user->id, $userData->employee_id));
        }

        return response()->json([
            'message' => "{$users->count()} account(s) activated successfully.",
            'count'   => $users->count(),
        ]);
    }

    private function linkEmployee(User $user): void
    {
        $user->refresh();
        if ($user->employee_id) {
            return;
        }

        DB::transaction(function () use ($user) {
            $lockedUser = User::lockForUpdate()->find($user->id);

            if ($lockedUser->employee_id) {
                return;
            }

            $now = now()->toDateTimeString();

            $employeeId = DB::table('employees')->insertGetId([
                'first_name'        => $lockedUser->given_name ?? null,
                'middle_name'       => $lockedUser->middle_name  ?? null,
                'last_name'         => $lockedUser->last_name  ?? null,
                'employment_type'   => 'Contract of Service',
                'employment_status' => 'pending',
                'aligned'           => false,
                'expanded'          => false,
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);

            DB::table('employee_info')->insert([
                'employee_id' => $employeeId,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);

            DB::table('users')
                ->where('id', $lockedUser->id)
                ->update([
                    'employee_id' => $employeeId,
                    'updated_at'  => $now,
                ]);

            $user->employee_id = $employeeId;
        });
    }
}