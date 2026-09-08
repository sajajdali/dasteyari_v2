<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;

    protected $fillable = ['title', 'slug', 'cover_path', 'excerpt', 'body', 'category', 'state', 'published_at'];

    protected $casts = [
        'published_at' => 'datetime',
    ];
}
