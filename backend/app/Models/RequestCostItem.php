<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestCostItem extends Model
{
    protected $fillable = ['request_id', 'title', 'note', 'amount', 'order'];

    protected $casts = [
        'amount' => 'integer',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(CaseRequest::class, 'request_id');
    }
}
