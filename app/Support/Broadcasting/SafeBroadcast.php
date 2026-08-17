<?php

namespace App\Support\Broadcasting;

use Illuminate\Support\Facades\Log;
use Throwable;

class SafeBroadcast
{
    public static function send(object $event, bool $toOthers = false): void
    {
        try {
            $pending = broadcast($event);

            if ($toOthers) {
                $pending->toOthers();
            }
        } catch (Throwable $e) {
            Log::warning('Broadcast failed — continuing without realtime push.', [
                'event'   => get_class($event),
                'message' => $e->getMessage(),
            ]);
        }
    }
}