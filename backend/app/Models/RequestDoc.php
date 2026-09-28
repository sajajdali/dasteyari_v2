<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class RequestDoc extends Model
{
    protected $fillable = ['request_id', 'type', 'path', 'uploaded_by', 'verified_by', 'verified_at', 'state', 'note'];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(CaseRequest::class, 'request_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function events(): MorphMany
    {
        return $this->morphMany(CaseEvent::class, 'subject');
    }
}
