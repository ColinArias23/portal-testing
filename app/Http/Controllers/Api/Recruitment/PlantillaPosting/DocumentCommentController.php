<?php

namespace App\Http\Controllers\Api\Recruitment\PlantillaPosting;

use App\Http\Controllers\Controller;
use App\Models\DocumentComment;
use App\Models\PlantillaPostingApplicationDocument;
use App\Services\Recruitment\PlantillaPosting\DocumentCommentService;
use Illuminate\Http\Request;

class DocumentCommentController extends Controller
{
    public function __construct(
        private readonly DocumentCommentService $comments,
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:hiring.plantilla.applications.manage')->only(['store', 'update', 'destroy']);
    }

    // ─── Index ─────────────────────────────────────────────────────
    public function index(Request $request, PlantillaPostingApplicationDocument $document)
    {
        if (! $this->comments->authorizeAccess($request, $document)) {
            abort(403);
        }

        return response()->json($this->comments->index($document));
    }

    // ─── Store ─────────────────────────────────────────────────────
    public function store(Request $request, PlantillaPostingApplicationDocument $document)
    {
        $validated = $request->validate([
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        return response()->json($this->comments->store($request, $document, $validated['comment']), 201);
    }

    // ─── Update ─────────────────────────────────────────────────────
    public function update(Request $request, DocumentComment $comment)
    {
        $validated = $request->validate([
            'comment'  => ['sometimes', 'string', 'max:2000'],
            'resolved' => ['sometimes', 'boolean'],
        ]);

        return response()->json($this->comments->update($request, $comment, $validated));
    }

    // ─── Destroy ─────────────────────────────────────────────────────
    public function destroy(Request $request, DocumentComment $comment)
    {
        $this->comments->destroy($request, $comment);

        return response()->json(['message' => 'Comment deleted.']);
    }
}
