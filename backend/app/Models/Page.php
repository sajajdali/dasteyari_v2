<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = ['key', 'title', 'body', 'seo'];

    protected $casts = [
        'seo' => 'array',
    ];
}
