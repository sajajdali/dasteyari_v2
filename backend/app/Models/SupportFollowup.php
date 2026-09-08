<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportFollowup extends Model
{
    public $timestamps = false;

    protected $fillable = ['support_id', 'admin_id', 'result', 'grace_days', 'due_at', 'note', 'created_at'];

    protected $casts = [
        'due_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function support(): BelongsTo
    {
        return $this->belongsTo(Support::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
