<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'kind', 'donor_id', 'request_id', 'campaign_id', 'amount', 'way', 'ref',
        'gateway_id', 'status', 'paid_at', 'registered_by', 'manual', 'description', 'meta',
    ];

    protected $casts = [
        'manual' => 'boolean',
        'paid_at' => 'datetime',
        'meta' => 'array',
    ];

    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(CaseRequest::class, 'request_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }
}
