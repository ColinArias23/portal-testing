<?php

namespace App\Services;

use App\Models\Notification;

class NotificationService
{
    /**
     * Notify all users that a pending account is waiting for HR approval.
     * Visible only to admins.
     */
    public static function accountPending(string $email): void
    {
        Notification::create([
            'type'           => 'account_pending',
            'title'          => 'New Account Pending Approval',
            'message'        => "A new account with email \"{$email}\" is awaiting HR activation.",
            'data'           => ['email' => $email],
            'visibility'     => 'admin',
            'target_user_id' => null,
        ]);
    }

    /**
     * Notify a specific user that their account has been activated.
     * Visible only to the target user.
     */
    public static function accountActivated(int $userId, string $email): void
    {
        Notification::create([
            'type'           => 'account_activated',
            'title'          => 'Your Account Has Been Activated',
            'message'        => "Your account ({$email}) has been approved. You can now log in.",
            'data'           => ['email' => $email],
            'visibility'     => 'user',
            'target_user_id' => $userId,
        ]);
    }

    /**
     * Notify ALL employees (except the poster) that a new training has been posted.
     *
     * @param  \App\Models\Training  $training
     * @param  int                   $postedByUserId  — the user who created the training
     */
    public static function trainingCreated(\App\Models\Training $training, int $postedByUserId): void
    {
        Notification::create([
            'type'           => 'training',
            'title'          => 'New Training Program',
            'message'        => "A new training program has been posted.",
            'data'           => [
                'program'          => $training->title,
                'department'       => $training->department,
                'category'         => $training->category,
                'location'         => $training->event_address,
                'mode'             => $training->training_mode,
                'start_date'       => $training->start_date?->toISOString(),
                'end_date'         => $training->end_date?->toISOString(),
                'duration'         => $training->duration,
                'posted_by_user_id' => $postedByUserId, // used to exclude the poster
            ],
            'visibility'     => 'all',
            'target_user_id' => null,
        ]);
    }
}