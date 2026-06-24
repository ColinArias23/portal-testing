<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\AutoUppercase;

class ApplicantDocument extends Model
{

    use AutoUppercase;

    protected $table = 'applicant_documents';

    protected array $uppercaseExclude = [
        'created_at',
        'updated_at',
    ];
    
    protected $fillable = [
        'applicant_id', 
        'name', 
        'status', 
        'file_path',
    ];

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }
}