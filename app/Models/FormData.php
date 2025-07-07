<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormData extends Model
{
    //
    protected $table = 'form_data';

    protected $fillable = [
        'id',
        'template_id',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    
}
