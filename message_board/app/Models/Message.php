<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use SoftDeletes;

    protected $table = 'Message';
    protected $fillable = [
        'user_id', 'name', 'content'
    ];
    protected $hidden = [
        'deleted_at'
    ];
}
