<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicantDocument extends Model
{
    protected $table = 'applicant_documents';

    protected $fillable = [
        'applicant_id', 'name', 'status', 'file_path',
    ];

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }
}