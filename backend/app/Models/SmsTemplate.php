<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsTemplate extends Model
{
    protected $fillable = ['group_key', 'text', 'order', 'active'];

    protected $casts = [
        'active' => 'boolean',
    ];
}
