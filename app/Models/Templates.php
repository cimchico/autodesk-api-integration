<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Templates extends Model
{
    //
    protected $table = 'template';

    protected $fillable = [
        'template_id',
        'name',
        'project_id',
    ];
}
