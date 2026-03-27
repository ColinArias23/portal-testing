<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\UserActivated;
use App\Events\PermissionsUpdated;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $users = User::with('employee')
            ->where('approval_status', 'APPROVED')
            ->orderBy('approved_at', 'desc')
            ->get()
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

        $user->syncPermissions($request->permissions);

        // ✅ clear cache first
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // ✅ unset the in-memory relation so getAllPermissions() re-queries from DB
        $user->unsetRelation('permissions');

        $updatedPermissions = $user->getAllPermissions()->pluck('name')->values();

        broadcast(new PermissionsUpdated($user->id));

        return response()->json([
            'message'     => 'Permissions updated successfully.',
            'permissions' => $updatedPermissions,
        ]);
    }

    public function activate(User $user)
    {
        DB::transaction(function () use ($user) {
            $this->linkEmployee($user);

            DB::table('users')->where('id', $user->id)->update([
                'approval_status' => 'APPROVED',
                'approved_at'     => now()->toDateTimeString(),
            ]);
        });

        $fresh = User::with('employee.info')->find($user->id);

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
            }
        });

        foreach ($users as $user) {
            $fresh = User::find($user->id);
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
            'first_name'      => null,
            'last_name'       => null,
            'employment_type' => 'Contract of Service',
            'aligned'         => false,
            'expanded'        => false,
            'created_at'      => $now,
            'updated_at'      => $now,
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