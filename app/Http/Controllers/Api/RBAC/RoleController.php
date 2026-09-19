<?php

namespace App\Http\Controllers\Api\RBAC;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\Users\UserRoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    private const GUARD = 'web';
    private const ACCESS_SCOPES = ['organization', 'department'];

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:roles.view,sanctum')->only(['index', 'show']);
        $this->middleware('permission:roles.manage,sanctum')->only(['store', 'update', 'destroy']);
    }

    // ─── Index ─────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $roles = Role::where('guard_name', self::GUARD)
            ->when(
                ! $this->canSeeProtectedRoles($request),
                fn ($query) => $query->whereNotIn('name', $this->protectedRoleNames()),
            )
            ->withCount('users')
            ->with(['permissions:id,name', 'departments:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => $this->formatRole($role))
            ->values();

        return response()->json($roles);
    }

    // ─── Show ─────────────────────────────────────────────────────
    public function show(Request $request, Role $role)
    {
        abort_unless($role->guard_name === self::GUARD, 404);

        // A non-SuperAdmin has no business knowing this role even exists.
        if (! $this->canSeeProtectedRoles($request) && $this->isProtectedRole($role)) {
            abort(404);
        }

        $role->loadCount('users');
        $role->load(['permissions:id,name', 'departments:id,name']);

        return response()->json($this->formatRole($role));
    }

    // ─── Store ─────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);

        $role = DB::transaction(function () use ($data, $request) {
            $role = Role::create([
                'name'         => $data['name'],
                'guard_name'   => self::GUARD,
                'description'  => $data['description'],
                'access_scope' => $data['access_scope'],
            ]);

            $role->syncPermissions($data['permissions']);
            $this->syncDepartments($role, $data);

            $this->recordActivity('role.created', $role, $request, [
                'after' => $this->auditSnapshot($role, $data),
            ]);

            return $role;
        });

        $this->invalidateRoleCaches();

        $role->loadCount('users');
        $role->load(['permissions:id,name', 'departments:id,name']);

        return response()->json($this->formatRole($role), 201);
    }

    // ─── Update ─────────────────────────────────────────────────────
    public function update(Request $request, Role $role): JsonResponse
    {
        abort_unless($role->guard_name === self::GUARD, 404);
        $this->assertMutableRole($request, $role);

        $data = $this->validatePayload($request, $role);

        $before = $this->auditSnapshot($role, [
            'name'           => $role->name,
            'description'    => $role->description,
            'access_scope'   => $role->access_scope,
            'permissions'    => $role->permissions->pluck('name')->all(),
            'department_ids' => $role->departments->pluck('id')->all(),
        ]);

        DB::transaction(function () use ($role, $data) {
            $role->forceFill([
                'name'         => $data['name'],
                'description'  => $data['description'],
                'access_scope' => $data['access_scope'],
            ])->save();

            $role->syncPermissions($data['permissions']);
            $this->syncDepartments($role, $data);
        });

        $this->invalidateRoleCaches();

        $role->refresh();
        $role->loadCount('users');
        $role->load(['permissions:id,name', 'departments:id,name']);

        $this->recordActivity('role.updated', $role, $request, [
            'before' => $before,
            'after'  => $this->auditSnapshot($role, $data),
        ]);

        return response()->json($this->formatRole($role));
    }

    // ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(Request $request, Role $role): JsonResponse
    {
        abort_unless($role->guard_name === self::GUARD, 404);
        $this->assertMutableRole($request, $role);

        if (strcasecmp($role->name, (string) config('rbac.default_role')) === 0) {
            throw ValidationException::withMessages([
                'role' => 'The default role assigned to new users cannot be deleted.',
            ]);
        }

        $usersCount = $role->users()->count();

        if ($usersCount > 0) {
            throw ValidationException::withMessages([
                'role' => "This role is still assigned to {$usersCount} user(s). Reassign them before deleting this role.",
            ]);
        }

        $snapshot = $this->auditSnapshot($role, [
            'name'           => $role->name,
            'description'    => $role->description,
            'access_scope'   => $role->access_scope,
            'permissions'    => $role->permissions->pluck('name')->all(),
            'department_ids' => $role->departments()->pluck('departments.id')->all(),
        ]);

        DB::transaction(function () use ($role) {
            $role->departments()->detach();
            $role->delete();
        });

        $this->invalidateRoleCaches();

        $this->recordActivity('role.deleted', $role, $request, ['before' => $snapshot], deleted: true);

        return response()->json(['message' => 'Role deleted.']);
    }

    // ─── Validation ─────────────────────────────────────────────────────
    private function validatePayload(Request $request, ?Role $role = null): array
    {
        $data = $request->validate([
            'name'               => [
                'required', 'string', 'max:255',
                Rule::unique('roles', 'name')
                    ->where(fn ($query) => $query->where('guard_name', self::GUARD))
                    ->ignore($role?->id),
            ],
            'description'        => ['nullable', 'string', 'max:500'],
            'accessScope'        => ['required', Rule::in(self::ACCESS_SCOPES)],
            'departmentIds'      => ['array'],
            'departmentIds.*'    => ['integer', 'exists:departments,id'],
            'permissions'        => ['array'],
            'permissions.*'      => ['string'],
        ]);

        if ($data['accessScope'] === 'department' && empty($data['departmentIds'])) {
            throw ValidationException::withMessages([
                'departmentIds' => 'Select at least one department for a department-scoped role.',
            ]);
        }

        $requestedPermissions = $data['permissions'] ?? [];
        $validPermissionNames = Permission::where('guard_name', self::GUARD)
            ->whereIn('name', $requestedPermissions)
            ->pluck('name');

        if ($validPermissionNames->count() !== count(array_unique($requestedPermissions))) {
            throw ValidationException::withMessages([
                'permissions' => 'One or more selected permissions are not recognized.',
            ]);
        }

        return [
            'name'           => trim($data['name']),
            'description'    => isset($data['description']) ? trim($data['description']) : null,
            'access_scope'   => $data['accessScope'],
            'department_ids' => $data['accessScope'] === 'department'
                ? array_values(array_unique($data['departmentIds'] ?? []))
                : [],
            'permissions'    => $validPermissionNames->values()->all(),
        ];
    }

    private function syncDepartments(Role $role, array $data): void
    {
        $role->departments()->sync($data['department_ids']);
    }

    private function canSeeProtectedRoles(Request $request): bool
    {
        return (bool) $request->user()?->hasRole('SuperAdmin');
    }

    private function isProtectedRole(Role $role): bool
    {
        return in_array($role->name, $this->protectedRoleNames(), true);
    }

    private function protectedRoleNames(): array
    {
        return (array) config('rbac.protected_roles', []);
    }

    private function assertMutableRole(Request $request, Role $role): void
    {
        if (! $this->isProtectedRole($role)) {
            return;
        }

        abort_unless($this->canSeeProtectedRoles($request), 404);

        throw ValidationException::withMessages([
            'role' => "The \"{$role->name}\" role is protected and cannot be modified or deleted.",
        ]);
    }

    private function invalidateRoleCaches(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        UserRoleService::forgetRolesCache();
    }

    private function recordActivity(string $action, Role $role, Request $request, array $payload, bool $deleted = false): void
    {
        ActivityLog::record(
            action: $action,
            userId: $request->user()?->id,
            subjectType: Role::class,
            subjectId: $deleted ? null : $role->id,
            subjectLabel: $role->name,
            payload: $payload,
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );
    }

    private function auditSnapshot(Role $role, array $data): array
    {
        return [
            'name'           => $data['name'] ?? $role->name,
            'description'    => $data['description'] ?? $role->description,
            'access_scope'   => $data['access_scope'] ?? $role->access_scope,
            'department_ids' => $data['department_ids'] ?? [],
            'permissions'    => $data['permissions'] ?? [],
        ];
    }

    private function formatRole(Role $role): array
    {
        return [
            'id'          => $role->id,
            'name'        => $role->name,
            'description' => $role->description,
            'accessScope' => $role->access_scope,
            'departments' => $role->departments->map(fn ($d) => [
                'id'   => $d->id,
                'name' => $d->name,
            ])->values(),
            'permissions' => $role->permissions->pluck('name')->values(),
            'usersCount'  => $role->users_count ?? $role->users()->count(),
            'isActive'    => true,
        ];
    }
}
