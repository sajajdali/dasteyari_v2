<?php

namespace App\Models;

use App\Enums\SupportStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Support extends Model
{
    use HasFactory;

    protected $fillable = [
        'donor_id', 'request_id', 'plan', 'amount', 'started_at', 'ended_at',
        'end_reason_id', 'status', 'given_total', 'months_count',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(CaseRequest::class, 'request_id');
    }

    public function endReason(): BelongsTo
    {
        return $this->belongsTo(Reason::class, 'end_reason_id');
    }

    public function followups(): HasMany
    {
        return $this->hasMany(SupportFollowup::class);
    }

    public function statusEnum(): SupportStatus
    {
        return SupportStatus::from($this->status);
    }

    /** ردیف منتقل‌شده کم‌رنگ نشان داده می‌شود — بخش ۸.۱ پلن. */
    public function getIsDimmedAttribute(): bool
    {
        return $this->statusEnum()->dimmed();
    }
}
