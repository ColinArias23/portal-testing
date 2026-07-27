<?php

namespace App\Services\Recruitment\PlantillaPosting;

use App\Models\ActivityLog;
use App\Models\DocumentComment;
use App\Models\PlantillaPostingApplicationDocument;
use App\Repositories\Recruitment\PlantillaPosting\DocumentCommentRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class DocumentCommentService
{
    public function __construct(
        private readonly DocumentCommentRepository $comments,
    ) {
    }

    public function authorizeAccess(Request $request, PlantillaPostingApplicationDocument $document): bool
    {
        $user = $request->user();

        $isOwner = $document->application->employee_id
            === ($user->employee_id ?? $user->employee?->id);

        $canView = $user->can('hiring.plantilla.applications.view');

        return $isOwner || $canView;
    }

    public function index(PlantillaPostingApplicationDocument $document): Collection
    {
        $this->comments->loadApplication($document);

        return $this->comments->forDocument($document)->map(fn ($c) => $this->transform($c));
    }

    public function store(Request $request, PlantillaPostingApplicationDocument $document, string $commentText): array
    {
        $comment = $this->comments->create([
            'plantilla_posting_application_document_id' => $document->id,
            'comment'    => $commentText,
            'created_by' => Auth::id(),
        ]);

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

        return $this->transform($comment);
    }

    public function update(Request $request, DocumentComment $comment, array $validated): array
    {
        if (array_key_exists('comment', $validated)) {
            $comment->comment = $validated['comment'];
        }

        if (array_key_exists('resolved', $validated)) {
            $comment->resolved_at = $validated['resolved'] ? now() : null;
            $comment->resolved_by = $validated['resolved'] ? Auth::id() : null;
        }

        $comment = $this->comments->save($comment);

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

        return $this->transform($comment);
    }

    public function destroy(Request $request, DocumentComment $comment): void
    {
        $commentId = $comment->id;

        $this->comments->delete($comment);

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
