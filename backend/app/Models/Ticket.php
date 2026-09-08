<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Ticket extends Model
{
    protected $fillable = [
        'subject', 'from_type', 'from_id', 'category', 'priority', 'state',
        'assignee_id', 'related_type', 'related_id', 'sla_due_at',
    ];

    protected $casts = [
        'sla_due_at' => 'datetime',
    ];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }
}
