<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlantillaPostingApplicationDocument extends Model
{
    protected $fillable = [
        'plantilla_posting_application_id',
        'document_key',
        'original_filename',
        'stored_path',
        'mime_type',
        'size',
    ];

    public function application()
    {
        return $this->belongsTo(PlantillaPostingApplication::class, 'plantilla_posting_application_id');
    }

    public function comments()
    {
        return $this->hasMany(DocumentComment::class, 'plantilla_posting_application_document_id');
    }
}