<?php

namespace App\Services\Users;

use App\Events\RBAC\PermissionsUpdated;
use App\Support\Broadcasting\SafeBroadcast;
use App\Exceptions\Users\PermissionUpdateForbiddenException;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserRoleService
{
    private const GUARD = 'web';
    private const ROLES_CACHE_KEY = 'rbac:roles:by_guard:web';
    private const ROLES_CACHE_TTL = 3600;

    public function update(User $target, User $actor, ?string $roleName, Request $request): array
    {
        $this->assertMutable($target, $actor);

        if ($roleName && strtolower($roleName) === 'superadmin') {
            throw new PermissionUpdateForbiddenException('Cannot assign SuperAdmin role.', 403);
        }

        $before = $target->getRoleNames()->toArray();

        if ($roleName && strtolower($roleName) !== 'none') {
            $role = $this->findRoleOrFail($roleName);
            $target->syncRoles([$role->name]);
        } else {
            $toRemove = $target->getRoleNames()
                ->filter(fn ($r) => strtolower($r) !== 'superadmin')
                ->toArray();

            if (!empty($toRemove)) {
                $target->removeRole(...$toRemove);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $target->refresh();

        ActivityLog::record(
            action: 'user.role_updated',
            userId: $actor->id,
            subjectType: User::class,
            subjectId: $target->id,
            subjectLabel: $target->username,
            payload: [
                'target_username' => $target->username,
                'before' => $before,
                'after'  => $target->getRoleNames()->toArray(),
            ],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        SafeBroadcast::send(new PermissionsUpdated($target->id));

        return ['roles' => $target->getRoleNames()];
    }

    private function assertMutable(User $target, User $actor): void
    {
        if ($target->approval_status !== 'APPROVED') {
            throw new PermissionUpdateForbiddenException(
                'Cannot assign roles to unapproved users.',
                422
            );
        }

        if ($target->hasRole('SuperAdmin')) {
            throw new PermissionUpdateForbiddenException(
                'Cannot change the role of a SuperAdmin account.',
                403
            );
        }

        if ($target->id === $actor->id) {
            throw new PermissionUpdateForbiddenException(
                'You cannot change your own role.',
                403
            );
        }
    }

    private function findRoleOrFail(string $roleName): Role
    {
        $roles = Cache::remember(
            self::ROLES_CACHE_KEY,
            self::ROLES_CACHE_TTL,
            fn () => Role::where('guard_name', self::GUARD)->get()->keyBy('name')
        );

        $role = $roles->get($roleName);

        if (!$role) {
            $role = Role::where('name', $roleName)->where('guard_name', self::GUARD)->first();
        }

        abort_if(!$role, 404);

        return $role;
    }

    public static function forgetRolesCache(): void
    {
        Cache::forget(self::ROLES_CACHE_KEY);
    }
}