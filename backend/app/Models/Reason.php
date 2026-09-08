<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reason extends Model
{
    use SoftDeletes;

    protected $fillable = ['action_key', 'text', 'order', 'active'];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function scopeForAction($q, string $actionKey)
    {
        return $q->where('action_key', $actionKey)->where('active', true)->orderBy('order');
    }
}
