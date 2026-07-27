<?php

namespace App\Http\Controllers\Api\Notifications;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {
        $this->middleware('auth:sanctum');
    }

    // ─── Index ─────────────────────────────────────────────────────
    public function index(Request $request)
    {
        return response()->json($this->notifications->index($request->user()));
    }

    // ─── MarkRead ─────────────────────────────────────────────────────
    public function markRead(Request $request, Notification $notification)
    {
        $this->notifications->markRead($notification, $request->user());

        return response()->json(['success' => true]);
    }

    // ─── MarkAllRead ─────────────────────────────────────────────────────
    public function markAllRead(Request $request)
    {
        $this->notifications->markAllRead($request->user());

        return response()->json(['success' => true]);
    }

    // ─── UnreadCount ─────────────────────────────────────────────────────
    public function unreadCount(Request $request)
    {
        return response()->json(['count' => $this->notifications->unreadCount($request->user())]);
    }
}
