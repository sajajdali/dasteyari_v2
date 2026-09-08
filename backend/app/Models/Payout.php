<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payout extends Model
{
    protected $fillable = ['request_id', 'needy_id', 'amount', 'paid_at', 'way', 'ref', 'by_id', 'doc_path'];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(CaseRequest::class, 'request_id');
    }

    public function needy(): BelongsTo
    {
        return $this->belongsTo(Needy::class);
    }

    public function by(): BelongsTo
    {
        return $this->belongsTo(User::class, 'by_id');
    }
}
