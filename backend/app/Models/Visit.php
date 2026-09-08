<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Visit extends Model
{
    protected $fillable = ['needy_id', 'request_id', 'officer_id', 'visited_at', 'amount_suggested', 'report', 'files'];

    protected $casts = [
        'visited_at' => 'datetime',
        'files' => 'array',
    ];

    public function needy(): BelongsTo
    {
        return $this->belongsTo(Needy::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(CaseRequest::class, 'request_id');
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_id');
    }
}
