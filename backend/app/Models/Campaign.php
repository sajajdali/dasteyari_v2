<?php

namespace App\Models;

use App\Enums\CampaignState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'title', 'slug', 'category_id', 'state', 'goal', 'raised',
        'starts_at', 'ends_at', 'cover_path', 'short', 'about', 'meta',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'meta' => 'array',
    ];

    public function cases(): HasMany
    {
        return $this->hasMany(CampaignCase::class);
    }

    public function supporters(): HasMany
    {
        return $this->hasMany(CampaignSupporter::class);
    }

    public function updates(): HasMany
    {
        return $this->hasMany(CampaignUpdate::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function statusEnum(): CampaignState
    {
        return CampaignState::from($this->state);
    }

    /** درصد تامین — بخش ۳.۹ پلن. */
    public function getRaisedPercentAttribute(): int
    {
        return $this->goal > 0 ? (int) round($this->raised / $this->goal * 100) : 0;
    }
}
