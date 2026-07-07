<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Training;
use App\Models\User;

class NotificationService
{
    public static function accountPending(string $username): void
    {
        Notification::create([
            'type' => 'account_pending',
            'title' => 'New Account Pending Approval',
            'message' => "A new account with username \"{$username}\" is awaiting HR activation.",
            'data' => ['username' => $username],
            'visibility' => 'admin',
            'target_user_id' => null,
        ]);
    }

    public static function accountActivated(int $userId): void
    {
        $user = User::find($userId);

        if (!$user) {
            return;
        }

        Notification::create([
            'type' => 'account_activated',
            'title' => 'Your Account Has Been Activated',
            'message' => "Your account ({$user->username}) has been approved. You can now log in.",
            'data' => ['username' => $user->username, 'user_id' => $userId],
            'visibility' => 'user',
            'target_user_id' => $userId,
        ]);
    }

    public static function trainingCreated(Training $training, int $postedByUserId): void
    {
        Notification::create([
            'type' => 'training',
            'title' => 'New Training Program',
            'message' => "A new training program \"{$training->title}\" has been posted.",
            'data' => [
                'training_id' => $training->id,
                'program' => $training->title,
                'department' => $training->department,
                'category' => $training->category,
                'location' => $training->event_address,
                'mode' => $training->training_mode,
                'start_date' => $training->start_date?->toISOString(),
                'end_date' => $training->end_date?->toISOString(),
                'duration' => $training->duration,
                'posted_by_user_id' => $postedByUserId,
            ],
            'visibility' => 'all',
            'target_user_id' => null,
        ]);
    }

    public static function permissionsUpdated(int $userId, array $permissions): void
    {
        $user = User::find($userId);

        if (!$user) {
            return;
        }

        $permissionList = implode(', ', $permissions);

        Notification::create([
            'type' => 'permissions_updated',
            'title' => 'Your Permissions Have Been Updated',
            'message' => "Your account permissions have been changed. New permissions: {$permissionList}",
            'data' => [
                'user_id' => $userId,
                'permissions' => $permissions,
            ],
            'visibility' => 'user',
            'target_user_id' => $userId,
        ]);
    }

    public static function roleUpdated(int $userId, array $roles): void
    {
        $user = User::find($userId);

        if (!$user) {
            return;
        }

        $roleList = !empty($roles) ? implode(', ', $roles) : 'No roles';

        Notification::create([
            'type' => 'role_updated',
            'title' => 'Your Role Has Been Updated',
            'message' => "Your account role has been changed. New role: {$roleList}",
            'data' => [
                'user_id' => $userId,
                'roles' => $roles,
            ],
            'visibility' => 'user',
            'target_user_id' => $userId,
        ]);
    }

    public static function systemAlert(string $title, string $message, array $data = []): void
    {
        Notification::create([
            'type' => 'system_alert',
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'visibility' => 'admin',
            'target_user_id' => null,
        ]);
    }
}
