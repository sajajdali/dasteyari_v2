<?php

namespace App\Models;

use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class CaseRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'requests';

    protected $casts = [
        'meta'         => 'array',
        'requested_at' => 'datetime',
        'deadline_at'  => 'datetime',
        'published_at' => 'datetime',
    ];

    public function needy()          { return $this->belongsTo(Needy::class); }
    public function needGroup()      { return $this->belongsTo(NeedGroup::class); }
    public function supports()       { return $this->hasMany(Support::class, 'request_id'); }
    public function activeSupports() { return $this->supports()->where('status', 'active'); }
    public function docs()           { return $this->hasMany(RequestDoc::class, 'request_id'); }
    public function events()         { return $this->morphMany(CaseEvent::class, 'subject'); }
    public function keepers()        { return $this->morphMany(Keeper::class, 'subject'); }

    public static function morphName(): string { return 'request'; }

    public function statusEnum(): RequestStatus { return RequestStatus::from($this->status); }
    public function getStatusEnumAttribute(): RequestStatus { return $this->statusEnum(); }

    /** درصد تامین — هرگز ستون دستی */
    public function getFundedPercentAttribute(): int
    {
        return $this->amount > 0 ? (int) round($this->amount_funded / $this->amount * 100) : 0;
    }

    public function getRemainingAttribute(): int
    {
        return max(0, (int) $this->amount - (int) $this->amount_funded);
    }

    /** عمر درخواست به‌صورت متن جلالی‌خوان */
    public function getAgeLabelAttribute(): string
    {
        return faDigits($this->requested_at->diffInDays(now())) . ' روز';
    }

    /** کارشناس فقط پرونده‌های تحت پیگیری خودش — بخش ۲.۴ پلن */
    public function scopeVisibleTo(Builder $q, $user): Builder
    {
        if ($user->can('requests.view.all')) {
            return $q;
        }
        return $q->whereHas('keepers', fn ($k) => $k->where('user_id', $user->id));
    }

    public function scopeSearch(Builder $q, string $term): Builder
    {
        return $q->where(fn ($x) => $x
            ->where('title', 'like', "%$term%")
            ->orWhereHas('needy', fn ($n) => $n
                ->where('name', 'like', "%$term%")
                ->orWhere('code', 'like', "%$term%")
                ->orWhere('city', 'like', "%$term%")));
    }
}
