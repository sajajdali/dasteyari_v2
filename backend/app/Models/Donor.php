<?php

namespace App\Models;

use App\Enums\DonorStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Donor extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'kind', 'city', 'capacity_cases', 'monthly_day',
        'anon_default', 'status', 'joined_at', 'meta',
    ];

    protected $casts = [
        'anon_default' => 'boolean',
        'joined_at' => 'datetime',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function supports(): HasMany
    {
        return $this->hasMany(Support::class);
    }

    public function activeSupports(): HasMany
    {
        return $this->supports()->where('status', 'active');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function statusEnum(): DonorStatus
    {
        return DonorStatus::from($this->status);
    }
}
