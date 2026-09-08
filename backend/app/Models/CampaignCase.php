<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignCase extends Model
{
    public $timestamps = false;

    protected $fillable = ['campaign_id', 'request_id', 'share', 'added_by', 'added_at', 'after_start', 'note'];

    protected $casts = [
        'after_start' => 'boolean',
        'added_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(CaseRequest::class, 'request_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
