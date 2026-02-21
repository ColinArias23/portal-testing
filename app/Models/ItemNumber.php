<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemNumber extends Model
{
    protected $fillable = [
        'item_number',
        'status',
    ];
}
