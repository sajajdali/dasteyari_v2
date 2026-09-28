<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Referral extends Model
{
    protected $fillable = ['subject_type', 'subject_id', 'from_admin_id', 'to_admin_id', 'note', 'due_at'];

    protected $casts = [
        'due_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function fromAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_admin_id');
    }

    public function toAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_admin_id');
    }

    public function getIsResolvedAttribute(): bool
    {
        return $this->resolved_at !== null;
    }
}
