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

class UserController extends Controller
{
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
                'email'         => $u->email,
                'name'          => $u->employee?->full_name ?? $u->email,
                'approved_at'   => $u->approved_at,
                'roles'         => $u->getRoleNames(),
                'role_position' => $u->employee?->role_position ?? [],
                'permissions'   => $u->getAllPermissions()->pluck('name')->values(),
            ]);

        return response()->json($users);
    }

    public function updatePermissions(Request $request, User $user)
    {
        $request->validate([
            'permissions'   => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $before = $user->getAllPermissions()->pluck('name')->values()->toArray();

        $user->syncPermissions($request->permissions);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user->unsetRelation('permissions');
        $user->unsetRelation('roles');
        $user->refresh();

        $updatedPermissions = $user->getAllPermissions()->pluck('name')->values();

        ActivityLog::record(
            action:       'user.permissions_updated',
            userId:       $request->user()?->id,
            subjectType:  User::class,
            subjectId:    $user->id,
            subjectLabel: $user->email,
            payload:      [
                'target_email' => $user->email,
                'before'       => $before,
                'after'        => $updatedPermissions->toArray(),
            ],
            ip:        $request->ip(),
            userAgent: $request->userAgent(),
        );

        broadcast(new PermissionsUpdated($user->id));

        return response()->json([
            'message'     => 'Permissions updated successfully.',
            'permissions' => $updatedPermissions,
        ]);
    }

    // ✅ New — assign a single role, or "none" to strip all roles
    public function updateRole(Request $request, User $user)
    {
        $request->validate([
            'role' => ['nullable', 'string'],
        ]);

        $roleName = $request->input('role');

        if ($roleName && strtolower($roleName) === 'superadmin') {
            return response()->json(['message' => 'Cannot assign SuperAdmin role.'], 403);
        }

        $before = $user->getRoleNames()->toArray();

        if ($roleName && $roleName !== 'none') {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            // Replace all current roles with just this one
            $user->syncRoles([$role->name]);
        } else {
            // Strip all non-superadmin roles
            $toRemove = $user->getRoleNames()
                ->filter(fn($r) => strtolower($r) !== 'superadmin')
                ->toArray();
            if (!empty($toRemove)) {
                $user->removeRole(...$toRemove);
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user->refresh();

        ActivityLog::record(
            action:       'user.role_updated',
            userId:       $request->user()?->id,
            subjectType:  User::class,
            subjectId:    $user->id,
            subjectLabel: $user->email,
            payload:      [
                'target_email' => $user->email,
                'before'       => $before,
                'after'        => $user->getRoleNames()->toArray(),
            ],
            ip:        $request->ip(),
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
            ]);

            // ✅ Mark the linked employee as active
            if ($user->employee_id) {
                DB::table('employees')->where('id', $user->employee_id)->update([
                    'employment_status' => 'active',
                    'updated_at'        => now()->toDateTimeString(),
                ]);
            }
        });

        $fresh = User::with('employee.info')->find($user->id);

        ActivityLog::record(
            action:       'user.activated',
            userId:       $request->user()?->id,
            subjectType:  User::class,
            subjectId:    $user->id,
            subjectLabel: $fresh->email,
            payload:      [
                'target_email' => $fresh->email,
                'employee_id'  => $fresh->employee_id,
            ],
            ip:        $request->ip(),
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
            'ids.*' => ['exists:users,id'],
        ]);

        $users = User::whereIn('id', $request->ids)
            ->where('approval_status', 'PENDING')
            ->get();

        DB::transaction(function () use ($users) {
            foreach ($users as $user) {
                $this->linkEmployee($user);

                DB::table('users')->where('id', $user->id)->update([
                    'approval_status' => 'APPROVED',
                    'approved_at'     => now()->toDateTimeString(),
                ]);

                // ✅ Mark the linked employee as active
                $employeeId = $user->employee_id
                    ?? DB::table('users')->where('id', $user->id)->value('employee_id');

                if ($employeeId) {
                    DB::table('employees')->where('id', $employeeId)->update([
                        'employment_status' => 'active',
                        'updated_at'        => now()->toDateTimeString(),
                    ]);
                }
            }
        });

        foreach ($users as $user) {
            $fresh = User::find($user->id);

            ActivityLog::record(
                action:       'user.activated',
                userId:       $request->user()?->id,
                subjectType:  User::class,
                subjectId:    $user->id,
                subjectLabel: $fresh->email,
                payload:      [
                    'target_email' => $fresh->email,
                    'employee_id'  => $fresh->employee_id,
                    'bulk'         => true,
                ],
                ip:        $request->ip(),
                userAgent: $request->userAgent(),
            );

            broadcast(new UserActivated($user->id, $fresh->employee_id));
        }

        return response()->json([
            'message' => "{$users->count()} account(s) activated successfully.",
        ]);
    }

    private function linkEmployee(User $user): void
    {
        if ($user->employee_id) return;

        $now = now()->toDateTimeString();

        $employeeId = DB::table('employees')->insertGetId([
            'first_name'        => null,
            'last_name'         => null,
            'employment_type'   => 'Contract of Service',
            'employment_status' => 'active',
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

        DB::table('users')->where('id', $user->id)->update([
            'employee_id' => $employeeId,
        ]);
    }
}