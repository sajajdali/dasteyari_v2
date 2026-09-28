<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CampaignSupporter extends Model
{
    protected $fillable = ['campaign_id', 'name', 'role', 'followers', 'link', 'code'];

    protected static function booted(): void
    {
        static::creating(function (self $supporter) {
            if (! $supporter->code) {
                do {
                    $code = Str::upper(Str::random(6));
                } while (self::where('code', $code)->exists());

                $supporter->code = $code;
            }
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** تراکنش‌های ورودی که واقعاً از طریق لینک اختصاصی این پشتیبان ثبت شده‌اند. */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
