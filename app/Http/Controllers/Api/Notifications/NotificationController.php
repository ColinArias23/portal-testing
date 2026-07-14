<?php

namespace App\Http\Controllers\Api\Notifications;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\NotificationRead;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    private const PAGE_SIZE = 50;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }
// ─── Index ─────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = Notification::relevantTo($user)
            ->with(['reads' => fn ($q) => $q->where('user_id', $user->id)])
            ->orderByDesc('created_at')
            ->limit(self::PAGE_SIZE)
            ->get()
            ->map(function ($n) {
                return [
                    'id'         => $n->id,
                    'type'       => $n->type,
                    'title'      => $n->title,
                    'message'    => $n->message,
                    'data'       => $n->data,
                    'read'       => $n->reads->isNotEmpty(),
                    'created_at' => $n->created_at,
                    'time'       => $n->created_at->diffForHumans(),
                ];
            });

        return response()->json([
            'notifications' => $notifications->values(),
            'unread_count'  => $notifications->where('read', false)->count(),
        ]);
    }
// ─── MarkRead ─────────────────────────────────────────────────────
    public function markRead(Request $request, Notification $notification)
    {
        $user = $request->user();

        abort_unless(
            Notification::relevantTo($user)->whereKey($notification->id)->exists(),
            403
        );

        NotificationRead::firstOrCreate([
            'notification_id' => $notification->id,
            'user_id'         => $user->id,
        ]);

        return response()->json(['success' => true]);
    }
// ─── MarkAllRead ─────────────────────────────────────────────────────
    public function markAllRead(Request $request)
    {
        $user = $request->user();

        $ids = Notification::relevantTo($user)->pluck('id');

        if ($ids->isEmpty()) {
            return response()->json(['success' => true]);
        }

        $now = now();
        $rows = $ids->map(fn ($id) => [
            'notification_id' => $id,
            'user_id'         => $user->id,
            'read_at'         => $now,
        ])->all();

        NotificationRead::upsert(
            $rows,
            uniqueBy: ['notification_id', 'user_id'],
            update: []
        );

        return response()->json(['success' => true]);
    }
// ─── UnreadCount ─────────────────────────────────────────────────────
    public function unreadCount(Request $request)
    {
        $user = $request->user();

        $count = Notification::relevantTo($user)
            ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id))
            ->count();

        return response()->json(['count' => $count]);
    }
}