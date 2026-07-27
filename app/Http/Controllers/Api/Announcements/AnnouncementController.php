<?php

namespace App\Http\Controllers\Api\Announcements;

use App\DTOs\AnnouncementDTO;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Services\Announcements\AnnouncementCommentService;
use App\Services\Announcements\AnnouncementInteractionService;
use App\Services\Announcements\AnnouncementQueryService;
use App\Services\Announcements\AnnouncementService;

class AnnouncementController extends Controller
{
    public function __construct(
        private readonly AnnouncementQueryService $query,
        private readonly AnnouncementService $announcements,
        private readonly AnnouncementInteractionService $interactions,
        private readonly AnnouncementCommentService $comments,
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:announcements.view')->only(['index', 'show', 'comments', 'viewers', 'downloadAttachment', 'addComment', 'updateComment', 'deleteComment', 'react', 'markViewed']);
        $this->middleware('permission:announcements.manage')->only(['store']);
        $this->middleware('permission:announcements.manage')->only(['update', 'destroy', 'togglePin', 'toggleArchive', 'destroyAttachment']);
    }

    // ─── Index ─────────────────────────────────────────────────────
    public function index(Request $request)
    {
        return response()->json($this->query->list($request));
    }

    // ─── Show ─────────────────────────────────────────────────────
    public function show(Request $request, $id)
    {
        return response()->json($this->query->show((int) $id, $request->user()->id));
    }

    // ─── Store ─────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $data = AnnouncementDTO::fromArray($this->validateAnnouncement($request));

        $announcement = $this->announcements->create($data, $request);

        return response()->json($announcement, 201);
    }

    // ─── Update ─────────────────────────────────────────────────────
    public function update(Request $request, $id)
    {
        $data = AnnouncementDTO::fromArray($this->validateAnnouncement($request));

        $announcement = $this->announcements->update((int) $id, $data, $request);

        return response()->json($announcement);
    }

    // ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(Request $request, $id)
    {
        $this->announcements->delete((int) $id, $request);

        return response()->json(['message' => 'Announcement deleted.']);
    }

    private function validateAnnouncement(Request $request): array
    {
        return $request->validate([
            'title'                  => 'required|string|max:255',
            'description'            => 'required|string|max:10000',
            'priority'               => ['required', Rule::in(['normal', 'important', 'urgent'])],
            'category'               => ['required', Rule::in(['General', 'Job Posting'])],
            'plantilla_posting_ids'  => ['nullable', 'required_if:category,Job Posting', 'array', 'min:1'],
            'plantilla_posting_ids.*' => ['exists:plantilla_postings,id'],
            'attachments'            => 'nullable|array|max:10',
            'attachments.*'          => [
                'file',
                'max:10240',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png',
            ],
        ]);
    }

    // ─── DownloadAttachment ─────────────────────────────────────────────────────
    public function downloadAttachment($attachmentId)
    {
        return $this->announcements->downloadAttachment((int) $attachmentId);
    }

    // ─── Viewers ─────────────────────────────────────────────────────
    public function viewers($id)
    {
        return response()->json($this->interactions->viewers((int) $id));
    }

    // ─── DestroyAttachment ─────────────────────────────────────────────────────
    public function destroyAttachment(Request $request, $attachmentId)
    {
        $this->announcements->deleteAttachment((int) $attachmentId, $request);

        return response()->json(['message' => 'Attachment removed.']);
    }

    // ─── Pin ─────────────────────────────────────────────────────
    public function togglePin(Request $request, $id)
    {
        return response()->json($this->announcements->togglePin((int) $id, $request));
    }

    // ─── ToggleArchive ─────────────────────────────────────────────────────
    public function toggleArchive(Request $request, $id)
    {
        return response()->json($this->announcements->toggleArchive((int) $id, $request));
    }

    // ─── markViewed ─────────────────────────────────────────────────────
    public function markViewed(Request $request, $id)
    {
        $count = $this->interactions->markViewed((int) $id, $request->user()->id);

        return response()->json(['views_count' => $count]);
    }

    // ─── React ─────────────────────────────────────────────────────
    public function react(Request $request, $id)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['like', 'love', 'insightful', 'celebrate'])],
        ]);

        $result = $this->interactions->react((int) $id, $request->user()->id, $validated['type']);

        return response()->json($result);
    }

    // ─── Comments ─────────────────────────────────────────────────────
    public function comments($id)
    {
        return response()->json($this->comments->list((int) $id));
    }

    // ─── Add Comment ─────────────────────────────────────────────────────
    public function addComment(Request $request, $id)
    {
        $validated = $request->validate([
            'body'      => 'required|string|max:2000',
            'parent_id' => [
                'nullable',
                Rule::exists('announcement_comments', 'id')->where('announcement_id', (int) $id),
            ],
        ]);

        $comment = $this->comments->add(
            (int) $id,
            $request->user()->id,
            $validated['body'],
            $validated['parent_id'] ?? null
        );

        return response()->json($comment, 201);
    }

    // ─── Update Comment ─────────────────────────────────────────────────────
    public function updateComment(Request $request, $commentId)
    {
        $validated = $request->validate(['body' => 'required|string|max:2000']);

        $comment = $this->comments->update((int) $commentId, $request->user()->id, $validated['body']);

        return response()->json($comment);
    }

    // ─── Delete Comment ─────────────────────────────────────────────────────
    public function deleteComment(Request $request, $commentId)
    {
        $this->comments->delete((int) $commentId, $request);

        return response()->json(['message' => 'Comment deleted.']);
    }
}
