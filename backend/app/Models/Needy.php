<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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

    /** آخرین پرونده — سطر «فهرست نیازمندان» یک پرونده نماینده در هر ردیف نشان می‌دهد (بخش ۹.۱ پلن). */
    public function latestRequest(): HasOne
    {
        return $this->requests()->one()->latestOfMany('requested_at');
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'subject');
    }

    public function scopeSearch(Builder $q, string $term): Builder
    {
        return $q->where(fn ($x) => $x
            ->where('name', 'like', "%$term%")
            ->orWhere('code', 'like', "%$term%")
            ->orWhere('city', 'like', "%$term%"));
    }

    /** بدون حامی فعال — بخش ۳.۹ پلن، هرگز ستون دستی نمی‌شود. */
    public function getIsUnsupportedAttribute(): bool
    {
        return ! $this->requests()->whereHas('supports', fn ($q) => $q->where('status', 'active'))->exists();
    }
}
