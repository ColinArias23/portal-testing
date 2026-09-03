<?php

namespace App\Http\Controllers\Api\RBAC;

use App\Exceptions\Users\PermissionUpdateForbiddenException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Repositories\Shared\UserRepository;
use App\Services\Users\UserActivationService;
use App\Services\Users\UserPermissionService;
use App\Services\Users\UserRoleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;

class UserController extends Controller
{
    private const GUARD = 'web';
    private const PERMISSIONS_CACHE_KEY = 'rbac:permissions:catalog:web';
    private const PERMISSIONS_CACHE_TTL = 3600;

    public function __construct(
        private readonly UserRepository $users,
        private readonly UserActivationService $activation,
        private readonly UserPermissionService $permissionService,
        private readonly UserRoleService $roleService,
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:users.manage,sanctum')->only(['pendingCount', 'pendingUsers', 'approvedUsers', 'permissions', 'activate', 'bulkActivate']);
        $this->middleware('permission:users.manage.roles,sanctum')->only(['updatePermissions', 'updateRole']);
    }

    // ─── PendingCount ─────────────────────────────────────────────────────
    public function pendingCount()
    {
        return response()->json(['count' => $this->users->pendingCount()]);
    }

    // ─── PendingUsers ─────────────────────────────────────────────────────
    public function pendingUsers()
    {
        return response()->json($this->users->pendingUsers());
    }

    // ─── ApprovedUsers ─────────────────────────────────────────────────────
    public function approvedUsers()
    {
        $currentUserId = Auth::id();

        $users = $this->users->approvedUsers($currentUserId)
            ->map(fn ($u) => [
                'id'            => $u->id,
                'username'      => $u->username,
                'email'         => $u->email,
                'name'          => $u->employee?->full_name ?? $u->username,
                'approved_at'   => $u->approved_at,
                'roles'         => $u->roles->pluck('name')->values(),
                'role_position' => $u->employee?->role_position ?? [],
                'permissions'   => $u->permissions->pluck('name')->values(),
            ]);

        return response()->json($users);
    }

    // ─── Permissions ─────────────────────────────────────────────────────
    public function permissions()
    {
        $payload = Cache::remember(self::PERMISSIONS_CACHE_KEY, self::PERMISSIONS_CACHE_TTL, function () {
            $names = Permission::where('guard_name', self::GUARD)
                ->orderBy('name')
                ->pluck('name');

            $grouped = $names->groupBy(fn (string $name) => explode('.', $name)[0] ?? $name);

            return ['permissions' => $names, 'grouped' => $grouped];
        });

        return response()->json($payload);
    }

    // ─── UpdatePermissions ─────────────────────────────────────────────────────
    public function updatePermissions(Request $request, User $user)
    {
        $request->validate([
            'permissions'   => ['present', 'array'],
            'permissions.*' => [
                'string',
                Rule::exists('permissions', 'name')->where('guard_name', self::GUARD),
            ],
        ]);

        try {
            $result = $this->permissionService->update(
                $user,
                Auth::user(),
                $request->permissions,
                $request
            );
        } catch (PermissionUpdateForbiddenException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status());
        }

        return response()->json([
            'message'     => 'Permissions updated successfully.',
            'permissions' => $result['permissions'],
            'roles'       => $result['roles'],
        ]);
    }

    // ─── UpdateRole ─────────────────────────────────────────────────────
    public function updateRole(Request $request, User $user)
    {
        $request->validate([
            'role' => [
                'nullable',
                'string',
                'max:100',
                function ($attribute, $value, $fail) {
                    if ($value === null || strtolower($value) === 'none') {
                        return;
                    }

                    $exists = DB::table('roles')
                        ->where('name', $value)
                        ->where('guard_name', self::GUARD)
                        ->exists();

                    if (! $exists) {
                        $fail('The selected role is invalid.');
                    }
                },
            ],
        ]);

        try {
            $result = $this->roleService->update(
                $user,
                Auth::user(),
                $request->input('role'),
                $request
            );
        } catch (PermissionUpdateForbiddenException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status());
        }

        return response()->json([
            'message' => 'Role updated successfully.',
            'roles'   => $result['roles'],
        ]);
    }

    // ─── Activate ─────────────────────────────────────────────────────
    public function activate(Request $request, User $user)
    {
        $fresh = $this->activation->activate($user, Auth::id(), $request);

        return response()->json([
            'message' => 'User activated successfully.',
            'user'    => $fresh,
        ]);
    }

    // ─── BulkActivate ─────────────────────────────────────────────────────
    public function bulkActivate(Request $request)
    {
        $request->validate([
            'ids'   => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', 'exists:users,id'],
        ]);

        $result = $this->activation->bulkActivate($request->ids, Auth::id(), $request);

        if ($result['count'] === 0) {
            return response()->json([
                'message' => 'No pending users found with the provided IDs.',
            ], 422);
        }

        return response()->json([
            'message' => "{$result['count']} account(s) activated successfully.",
            'count'   => $result['count'],
        ]);
    }
}