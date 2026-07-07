<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementAttachment;
use App\Models\AnnouncementComment;
use App\Models\AnnouncementReaction;
use App\Models\AnnouncementView;
use App\Traits\ResolvesAvatarUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AnnouncementController extends Controller
{
    use ResolvesAvatarUrl;

    public function __construct()
    {
        $this->middleware('auth:sanctum');

        $this->middleware('permission:announcements.view')->only([
            'index', 'show', 'comments', 'viewers', 'downloadAttachment',
        ]);
        $this->middleware('role:SuperAdmin|HR')->only(['store']);

        $this->middleware('permission:announcements.manage')->only([
            'update', 'destroy', 'togglePin', 'toggleArchive', 'destroyAttachment',
        ]);
    }

    private function authorizeOwnerOrSuperAdmin(Request $request, Announcement $announcement): void
    {
        $user = $request->user();

        if ($announcement->created_by === $user->id || $user->hasRole('SuperAdmin')) {
            return;
        }

        abort(403, 'You can only manage your own announcements.');
    }

    // ─── Feed ─────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $query = Announcement::with(['creator.employee', 'attachments'])
            ->withCount(['comments', 'views'])
            ->where('is_archived', $request->boolean('archived'));

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $priorities = $request->has('priorities')
            ? (array) $request->get('priorities')
            : array_filter([$request->get('priority')]);

        if (! empty($priorities)) {
            $query->whereIn('priority', $priorities);
        }

        if ($request->has('departments')) {
            $departments = (array) $request->get('departments');
            $query->whereHas('creator.department', function ($q) use ($departments) {
                $q->whereIn('name', $departments);
            });
        }

        if ($request->boolean('pinned_only')) {
            $query->where('is_pinned', true);
        }

        if ($request->boolean('unread_only')) {
            $userId = $request->user()->id;
            $query->whereDoesntHave('views', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            });
        }

        $sort = $request->get('sort', 'newest');
        match ($sort) {
            'oldest' => $query->oldest(),
            'views' => $query->orderByDesc('views_count'),
            'reactions' => $query->withCount('reactions')->orderByDesc('reactions_count'),
            default => $query->latest(),
        };

        $query->orderByDesc('is_pinned');

        $perPage = min((int) $request->get('per_page', 10), 50);
        $announcements = $query->paginate($perPage);

        $this->hydrateComputedFields($announcements->getCollection(), $request->user()->id);

        return response()->json($announcements);
    }

    public function show(Request $request, $id)
    {
        $announcement = Announcement::with([
            'creator.employee', 'attachments',
            'comments.user.employee', 'comments.replies.user.employee',
        ])->withCount('views')->findOrFail($id);

        $userId = $request->user()->id;
        $announcement->my_reaction = $announcement->reactions()
            ->where('user_id', $userId)
            ->value('type');
        $announcement->reactions_summary = $announcement->reactions()
            ->selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();
        $announcement->unread = $announcement->created_by !== $userId
            && ! AnnouncementView::where('announcement_id', $announcement->id)
                ->where('user_id', $userId)
                ->exists();

        return response()->json($announcement);
    }

    private function hydrateComputedFields($collection, int $userId): void
    {
        $ids = $collection->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        $viewedIds = AnnouncementView::where('user_id', $userId)
            ->whereIn('announcement_id', $ids)
            ->pluck('announcement_id')
            ->all();

        $reactionCounts = AnnouncementReaction::whereIn('announcement_id', $ids)
            ->selectRaw('announcement_id, type, count(*) as count')
            ->groupBy('announcement_id', 'type')
            ->get()
            ->groupBy('announcement_id');

        $myReactions = AnnouncementReaction::whereIn('announcement_id', $ids)
            ->where('user_id', $userId)
            ->pluck('type', 'announcement_id');

        $collection->each(function (Announcement $a) use ($userId, $viewedIds, $reactionCounts, $myReactions) {
            $a->unread = $a->created_by !== $userId && ! in_array($a->id, $viewedIds, true);
            $a->my_reaction = $myReactions[$a->id] ?? null;
            $a->reactions_summary = ($reactionCounts[$a->id] ?? collect())
                ->pluck('count', 'type')
                ->toArray();
        });
    }

    // ─── CRUD ─────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $data = $this->validateAnnouncement($request);

        $data['created_by'] = $request->user()->id;

        $announcement = Announcement::create($data);

        $this->storeAttachments($request, $announcement);

        return response()->json(
            $announcement->fresh(['creator.employee', 'attachments']),
            201
        );
    }

    public function update(Request $request, $id)
    {
        $announcement = Announcement::findOrFail($id);
        $this->authorizeOwnerOrSuperAdmin($request, $announcement);

        $data = $this->validateAnnouncement($request);

        $announcement->update($data);

        $this->storeAttachments($request, $announcement);

        return response()->json($announcement->fresh(['creator.employee', 'attachments']));
    }

    public function destroy(Request $request, $id)
    {
        $announcement = Announcement::findOrFail($id);
        $this->authorizeOwnerOrSuperAdmin($request, $announcement);

        $announcement->delete();

        return response()->json(['message' => 'Announcement deleted.']);
    }

    private function validateAnnouncement(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:10000',
            'priority' => ['required', Rule::in(['normal', 'important', 'urgent'])],
            'category' => ['required', Rule::in(['General', 'Job Posting'])],
            'plantilla_posting_id' => [
                'nullable',
                'required_if:category,Job Posting',
                'exists:plantilla_postings,id',
            ],
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => [
                'file',
                'max:10240',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png',
            ],
        ]);
    }

    private function storeAttachments(Request $request, Announcement $announcement): void
    {
        if (! $request->hasFile('attachments')) {
            return;
        }

        foreach ($request->file('attachments') as $file) {
            $path = $file->store("announcements/{$announcement->id}", 'local');

            AnnouncementAttachment::create([
                'announcement_id' => $announcement->id,
                'original_filename' => $file->getClientOriginalName(),
                'stored_path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }

    public function downloadAttachment($attachmentId)
    {
        $attachment = AnnouncementAttachment::findOrFail($attachmentId);

        $attachment->increment('downloads_count');

        return Storage::disk('local')->download(
            $attachment->stored_path,
            $attachment->original_filename
        );
    }

    public function viewers($id)
    {
        $announcement = Announcement::findOrFail($id);

        $views = $announcement->views()
            ->with(['user' => function ($query) {
                $query->select('id', 'given_name', 'last_name', 'username', 'employee_id')
                    ->with('employee:id,avatar_url');
            }])
            ->latest('viewed_at')
            ->limit(20)
            ->get();

        return response()->json([
            'viewers' => $views->map(fn ($v) => [
                'id' => $v->user->id,
                'name' => $v->user->name,
                'initials' => collect(explode(' ', $v->user->name))
                    ->map(fn ($p) => mb_substr($p, 0, 1))
                    ->take(2)->implode(''),
                'avatar_url' => $this->resolveAvatarUrl(
                    $v->user->employee?->getRawOriginal('avatar_url')
                ),
            ]),
            'total' => $announcement->views()->count(),
        ]);
    }

    public function destroyAttachment(Request $request, $attachmentId)
    {
        $attachment = AnnouncementAttachment::with('announcement')->findOrFail($attachmentId);
        $this->authorizeOwnerOrSuperAdmin($request, $attachment->announcement);

        Storage::disk('local')->delete($attachment->stored_path);
        $attachment->delete();

        return response()->json(['message' => 'Attachment removed.']);
    }

    // ─── Pin / Archive toggles ─────────────────────────────────────

    public function togglePin(Request $request, $id)
    {
        $announcement = Announcement::findOrFail($id);
        $this->authorizeOwnerOrSuperAdmin($request, $announcement);

        $announcement->update(['is_pinned' => ! $announcement->is_pinned]);

        return response()->json($announcement);
    }

    public function toggleArchive(Request $request, $id)
    {
        $announcement = Announcement::findOrFail($id);
        $this->authorizeOwnerOrSuperAdmin($request, $announcement);

        $announcement->update(['is_archived' => ! $announcement->is_archived]);

        return response()->json($announcement);
    }

    // ─── Views (read-tracking) ──────────────────────────────────────

    public function markViewed(Request $request, $id)
    {
        $announcement = Announcement::findOrFail($id);
        $userId = $request->user()->id;

        if ($announcement->created_by === $userId) {
            return response()->json(['views_count' => $announcement->views()->count()]);
        }

        AnnouncementView::firstOrCreate(
            ['announcement_id' => $announcement->id, 'user_id' => $userId],
            ['viewed_at' => now()]
        );

        return response()->json(['views_count' => $announcement->views()->count()]);
    }

    // ─── Reactions ──────────────────────────────────────────────────

    public function react(Request $request, $id)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['like', 'love', 'insightful', 'celebrate'])],
        ]);

        $announcement = Announcement::findOrFail($id);
        $userId = $request->user()->id;

        $existing = AnnouncementReaction::where('announcement_id', $announcement->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing && $existing->type === $validated['type']) {
            $existing->delete();
        } elseif ($existing) {
            $existing->update(['type' => $validated['type']]);
        } else {
            AnnouncementReaction::create([
                'announcement_id' => $announcement->id,
                'user_id' => $userId,
                'type' => $validated['type'],
            ]);
        }

        return response()->json([
            'reactions_summary' => $announcement->reactions()
                ->selectRaw('type, count(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray(),
            'my_reaction' => $announcement->reactions()->where('user_id', $userId)->value('type'),
        ]);
    }

    // ─── Comments & Replies ───────────────────────────────────────────

    public function comments($id)
    {
        $announcement = Announcement::findOrFail($id);

        $comments = $announcement->comments()
            ->with(['user.employee', 'replies.user.employee'])
            ->latest()
            ->get();

        return response()->json($comments);
    }

    public function addComment(Request $request, $id)
    {
        $announcement = Announcement::findOrFail($id);

        $validated = $request->validate([
            'body' => 'required|string|max:2000',
            'parent_id' => [
                'nullable',
                Rule::exists('announcement_comments', 'id')
                    ->where('announcement_id', $announcement->id),
            ],
        ]);

        $comment = AnnouncementComment::create([
            'announcement_id' => $announcement->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        return response()->json($comment->fresh('user.employee'), 201);
    }

    public function updateComment(Request $request, $commentId)
    {
        $comment = AnnouncementComment::findOrFail($commentId);

        if ($comment->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $comment->update([
            'body' => $validated['body'],
            'edited_at' => now(),
        ]);

        return response()->json($comment->fresh('user.employee'));
    }

    public function deleteComment(Request $request, $commentId)
    {
        $comment = AnnouncementComment::findOrFail($commentId);

        $isOwner = $comment->user_id === $request->user()->id;
        $isHrOrAdmin = $request->user()->hasRole(['SuperAdmin', 'Admin', 'HR']);

        if (! $isOwner && ! $isHrOrAdmin) {
            abort(403);
        }

        $comment->delete();

        return response()->json(['message' => 'Comment deleted.']);
    }
}