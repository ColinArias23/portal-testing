<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\DocumentComment;
use App\Models\PlantillaPostingApplicationDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DocumentCommentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:hiring.plantilla.applications.manage')->only(['store', 'update', 'destroy']);
    }
// ─── AuthorizeAccess ─────────────────────────────────────────────────────
    private function authorizeAccess(Request $request, PlantillaPostingApplicationDocument $document): bool
    {
        $user = $request->user();

        $isOwner = $document->application->employee_id
            === ($user->employee_id ?? $user->employee?->id);

        $canView = $user->can('hiring.plantilla.applications.view');

        return $isOwner || $canView;
    }
// ─── Index ─────────────────────────────────────────────────────
    public function index(Request $request, PlantillaPostingApplicationDocument $document)
    {
        $document->loadMissing('application');

        if (! $this->authorizeAccess($request, $document)) {
            abort(403);
        }

        $comments = $document->comments()
            ->with('creator:id,username')
            ->orderBy('created_at')
            ->get()
            ->map(fn (DocumentComment $c) => $this->transform($c));

        return response()->json($comments);
    }

// ─── Store ─────────────────────────────────────────────────────
    public function store(Request $request, PlantillaPostingApplicationDocument $document)
    {
        $validated = $request->validate([
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        $comment = DocumentComment::create([
            'plantilla_posting_application_document_id' => $document->id,
            'comment'    => $validated['comment'],
            'created_by' => Auth::id(),
        ]);

        $comment->load('creator:id,username');

        ActivityLog::record(
            action: 'document_comment.created',
            userId: Auth::id(),
            subjectType: DocumentComment::class,
            subjectId: $comment->id,
            subjectLabel: $document->original_filename,
            payload: [
                'document_id' => $document->id,
                'comment'     => $comment->comment,
            ],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json($this->transform($comment), 201);
    }
// ─── Update ─────────────────────────────────────────────────────
    public function update(Request $request, DocumentComment $comment)
    {
        $validated = $request->validate([
            'comment'  => ['sometimes', 'string', 'max:2000'],
            'resolved' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('comment', $validated)) {
            $comment->comment = $validated['comment'];
        }

        if (array_key_exists('resolved', $validated)) {
            $comment->resolved_at = $validated['resolved'] ? now() : null;
            $comment->resolved_by = $validated['resolved'] ? Auth::id() : null;
        }

        $comment->save();
        $comment->load('creator:id,username');

        ActivityLog::record(
            action: 'document_comment.updated',
            userId: Auth::id(),
            subjectType: DocumentComment::class,
            subjectId: $comment->id,
            subjectLabel: null,
            payload: $validated,
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json($this->transform($comment));
    }
// ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(Request $request, DocumentComment $comment)
    {
        $commentId = $comment->id;
        $comment->delete();

        ActivityLog::record(
            action: 'document_comment.deleted',
            userId: Auth::id(),
            subjectType: DocumentComment::class,
            subjectId: $commentId,
            subjectLabel: null,
            payload: [],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json(['message' => 'Comment deleted.']);
    }

    private function transform(DocumentComment $c): array
    {
        return [
            'id'              => $c->id,
            'comment'         => $c->comment,
            'resolved_at'     => $c->resolved_at,
            'created_by_name' => $c->creator?->username,
            'created_at'      => $c->created_at,
        ];
    }
}