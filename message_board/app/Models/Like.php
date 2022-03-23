<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Like extends Model
{
    use SoftDeletes;

    protected $table = 'like';
    protected $fillable = [
        'message_id', 'user_id', 'created_at'
    ];
    public $timestamps = false;
}
