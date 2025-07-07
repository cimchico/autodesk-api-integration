<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PoolingStatus extends Model
{
    //
    

    protected $table = 'pooling_status';

    protected $fillable = [
        'user_id',
        'is_polling',
        'started_at',
        'stopped_at'
    ];

    
}
