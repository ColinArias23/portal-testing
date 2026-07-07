<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnnouncementAttachment extends Model
{
    protected $fillable = ['announcement_id', 'original_filename', 'stored_path', 'mime_type', 'size', 'downloads_count'];
    protected $casts = ['downloads_count' => 'integer'];

    public function announcement()
    {
        return $this->belongsTo(Announcement::class);
    }
}