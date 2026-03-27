<?php

namespace App\Events;

use App\Models\Training;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TrainingCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Training $training;
    public int      $postedByUserId;
    public array    $formatted;

    /**
     * @param Training $training      The Eloquent model (for the notification bell payload)
     * @param int      $postedByUserId  Excluded on the frontend notification bell
     * @param array    $formatted     Pre-formatted training array (for the table realtime update)
     */
    public function __construct(Training $training, int $postedByUserId, array $formatted)
    {
        $this->training       = $training;
        $this->postedByUserId = $postedByUserId;
        $this->formatted      = $formatted;
    }

    /**
     * Broadcast on TWO channels:
     *
     *  1. "notifications" — NotificationBell listens here for .notification.created
     *  2. "trainings"     — TrainingPage listens here for .training.created
     *
     * Reverb will call broadcastWith() once and send the same payload to both.
     * The frontend handlers ignore keys they don't need.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('notifications'),
            new Channel('trainings'),
        ];
    }

    /**
     * Because we need TWO different event names on two channels, we split this
     * into two separate event classes that share the same dispatch — see the
     * controller. This single class keeps the notification bell name.
     *
     * broadcastAs() can only return one string, so we use this event for the
     * notification channel and dispatch TrainingPosted separately for the
     * trainings channel — OR we use the merged payload approach below and let
     * the frontend ignore irrelevant keys.
     *
     * ── Chosen approach: ONE event, TWO channels, broadcastAs = notification.created
     *    The trainingPage listener also listens on "notifications" channel for
     *    "notification.created" and checks e.training to detect new programs.
     *
     *    This is the cleanest option since you already have one public channel.
     */
    public function broadcastAs(): string
    {
        // NotificationBell listens: .notification.created  ✓
        // TrainingPage   listens: .notification.created  ✓  (checks e.training key)
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        $t = $this->training;

        return [
            // ── Notification bell fields ──────────────────────────────────
            'type'              => 'training',
            'title'             => 'New Training Program',
            'message'           => 'A new training program has been posted.',
            'posted_by_user_id' => $this->postedByUserId,
            'data'              => [
                'program'    => $t->title,
                'department' => $t->department,
                'category'   => $t->category,
                'location'   => $t->event_address,
                'mode'       => $t->training_mode,
                'start_date' => $t->start_date?->toISOString(),
                'end_date'   => $t->end_date?->toISOString(),
                'duration'   => $t->duration,
            ],

            // ── Training table realtime field ─────────────────────────────
            // trainingPage checks for this key to prepend the new row.
            // The notification bell ignores it (it doesn't use e.training).
            'training'          => $this->formatted,
        ];
    }
}