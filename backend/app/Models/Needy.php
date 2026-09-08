<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Needy extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'user_id', 'name', 'city', 'province', 'family_size',
        'birth_year', 'need_group_id', 'joined_at', 'status', 'priority', 'meta',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function needGroup(): BelongsTo
    {
        return $this->belongsTo(NeedGroup::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(CaseRequest::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    /** بدون حامی فعال — بخش ۳.۹ پلن، هرگز ستون دستی نمی‌شود. */
    public function getIsUnsupportedAttribute(): bool
    {
        return ! $this->requests()->whereHas('supports', fn ($q) => $q->where('status', 'active'))->exists();
    }
}
