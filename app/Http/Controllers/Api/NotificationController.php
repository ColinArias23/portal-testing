<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\NotificationRead;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = Notification::visibleTo($user)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            // ── exclude training notifications posted by the current user ──
            ->filter(function ($n) use ($user) {
                if ($n->type !== 'training') return true;

                $postedBy = $n->data['posted_by_user_id'] ?? null;

                // If posted_by_user_id matches the current user, hide it from them
                return $postedBy !== $user->id;
            })
            ->map(function ($n) use ($user) {
                return [
                    'id'         => $n->id,
                    'type'       => $n->type,
                    'title'      => $n->title,
                    'message'    => $n->message,
                    'data'       => $n->data,
                    'read'       => $n->isReadBy($user->id),
                    'created_at' => $n->created_at,
                    'time'       => $n->created_at->diffForHumans(),
                ];
            })
            ->values(); // re-index after filter

        $unread = $notifications->where('read', false)->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count'  => $unread,
        ]);
    }

    public function markRead(Request $request, Notification $notification)
    {
        $user = $request->user();

        NotificationRead::firstOrCreate([
            'notification_id' => $notification->id,
            'user_id'         => $user->id,
        ]);

        return response()->json(['success' => true]);
    }

    public function markAllRead(Request $request)
    {
        $user = $request->user();

        $ids = Notification::visibleTo($user)->pluck('id');

        foreach ($ids as $id) {
            NotificationRead::firstOrCreate([
                'notification_id' => $id,
                'user_id'         => $user->id,
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function unreadCount(Request $request)
    {
        $user = $request->user();

        $total = Notification::visibleTo($user)
            ->get()
            // exclude training notifications posted by the current user
            ->filter(function ($n) use ($user) {
                if ($n->type !== 'training') return true;
                $postedBy = $n->data['posted_by_user_id'] ?? null;
                return $postedBy !== $user->id;
            })
            ->count();

        $read = NotificationRead::where('user_id', $user->id)
            ->whereIn('notification_id', Notification::visibleTo($user)->pluck('id'))
            ->count();

        return response()->json(['count' => max(0, $total - $read)]);
    }
}