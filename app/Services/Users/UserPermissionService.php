<?php

namespace App\Services\Users;

use App\Events\RBAC\PermissionsUpdated;
use App\Exceptions\Users\PermissionUpdateForbiddenException;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

class UserPermissionService
{
    private const RESTRICTED_CACHE_KEY = 'rbac:super_admin_only_permissions';
    private const RESTRICTED_CACHE_TTL = 3600;

    public function update(User $target, User $actor, array $requestedPermissions, Request $request): array
    {
        $this->assertMutable($target, $actor);
        $this->assertNotRestricted($requestedPermissions, $actor);

        $before = $target->getDirectPermissions()->pluck('name')->values()->toArray();
        $target->syncPermissions($requestedPermissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $target->unsetRelation('permissions');
        $target->unsetRelation('roles');
        $target->refresh();

        $updatedPermissions = $target->getDirectPermissions()->pluck('name')->values();

        ActivityLog::record(
            action: 'user.permissions_updated',
            userId: $actor->id,
            subjectType: User::class,
            subjectId: $target->id,
            subjectLabel: $target->username,
            payload: [
                'target_username' => $target->username,
                'before' => $before,
                'after'  => $updatedPermissions->toArray(),
            ],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        broadcast(new PermissionsUpdated($target->id));

        return [
            'permissions' => $updatedPermissions,
            'roles'       => $target->getRoleNames(),
        ];
    }

    private function assertMutable(User $target, User $actor): void
    {
        if ($target->approval_status !== 'APPROVED') {
            throw new PermissionUpdateForbiddenException(
                'Cannot update permissions for unapproved users.',
                422
            );
        }

        if ($target->hasRole('SuperAdmin')) {
            throw new PermissionUpdateForbiddenException(
                'Cannot modify permissions for a SuperAdmin account.',
                403
            );
        }

        if ($target->id === $actor->id) {
            throw new PermissionUpdateForbiddenException(
                'You cannot modify your own permissions.',
                403
            );
        }
    }

    private function assertNotRestricted(array $requested, User $actor): void
    {
        $restricted = Cache::remember(
            self::RESTRICTED_CACHE_KEY,
            self::RESTRICTED_CACHE_TTL,
            fn () => config('rbac.super_admin_only_permissions')
        );

        $blocked = collect($requested)->intersect($restricted);

        if ($blocked->isNotEmpty() && !$actor->hasRole('SuperAdmin')) {
            throw new PermissionUpdateForbiddenException(
                'You are not authorized to grant restricted permission(s): ' . $blocked->implode(', '),
                403
            );
        }
    }
}
