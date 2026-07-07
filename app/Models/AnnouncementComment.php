<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnnouncementComment extends Model
{
    protected $fillable = ['announcement_id', 'parent_id', 'user_id', 'body', 'edited_at'];

    protected $casts = ['edited_at' => 'datetime'];

    public function announcement()
    {
        return $this->belongsTo(Announcement::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function replies()
    {
        return $this->hasMany(AnnouncementComment::class, 'parent_id');
    }
}