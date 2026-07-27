<?php

namespace App\Repositories\Recruitment\PlantillaPosting;

use App\Models\DocumentComment;
use App\Models\PlantillaPostingApplicationDocument;
use Illuminate\Support\Collection;

class DocumentCommentRepository
{
    public function loadApplication(PlantillaPostingApplicationDocument $document): PlantillaPostingApplicationDocument
    {
        return $document->loadMissing('application');
    }

    public function forDocument(PlantillaPostingApplicationDocument $document): Collection
    {
        return $document->comments()
            ->with('creator:id,username')
            ->orderBy('created_at')
            ->get();
    }

    public function create(array $attributes): DocumentComment
    {
        return DocumentComment::create($attributes)->load('creator:id,username');
    }

    public function save(DocumentComment $comment): DocumentComment
    {
        $comment->save();

        return $comment->load('creator:id,username');
    }

    public function delete(DocumentComment $comment): void
    {
        $comment->delete();
    }
}
