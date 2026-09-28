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

    /**
     * تا فاز ۱۱ هیچ کد واقعی (غیر از Factory/تینکر) مستقیم CaseRequest::create() صدا نمی‌زد — به همین
     * دلیل نبودِ fillable/guarded دیده نشده بود (Factory این چک را دور می‌زند). ⚡request-help.blade.php
     * اولین مسیر واقعی ثبت درخواست از طریق فرم عمومی است؛ بدون fillable با
     * MassAssignmentException می‌شکست.
     */
    protected $fillable = [
        'needy_id', 'need_group_id', 'title', 'plan', 'period_days',
        'amount', 'amount_funded', 'requested_at', 'deadline_at',
        'status', 'published_at', 'priority', 'slot', 'meta',
    ];

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
    public function docRequests()    { return $this->hasMany(DocRequest::class, 'request_id'); }
    public function pledges()        { return $this->hasMany(Pledge::class, 'request_id'); }
    public function transactions()   { return $this->hasMany(Transaction::class, 'request_id'); }
    public function events()         { return $this->morphMany(CaseEvent::class, 'subject'); }
    public function keepers()        { return $this->morphMany(Keeper::class, 'subject'); }
    public function visits()         { return $this->hasMany(Visit::class, 'request_id'); }
    public function costItems()      { return $this->hasMany(RequestCostItem::class, 'request_id')->orderBy('order'); }

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

    /** پرونده‌های «در انتظار کمک» سایت عمومی — بخش ۹.۴/۱۲ پلن؛ منتشرشده ولی هنوز تکمیل نشده. */
    public function scopePublicOpen(Builder $q): Builder
    {
        return $q->whereIn('status', ['published', 'funding']);
    }

    /** برچسب فوریت سایت عمومی — تفسیر من از روی مهلت باقی‌مانده (طرح تعریف عددی نداشت، بخش ۹.۴). */
    public function getPublicUrgencyAttribute(): array
    {
        if ($this->plan === 'monthly') {
            return ['label' => 'ماهانه', 'kind' => 'ok'];
        }

        if (! $this->deadline_at) {
            return ['label' => 'بدون مهلت مشخص', 'kind' => 'ok'];
        }

        $days = (int) now()->diffInDays($this->deadline_at, false);

        return match (true) {
            $days <= 10 => ['label' => 'فوری', 'kind' => 'late'],
            $days <= 30 => ['label' => 'در جریان', 'kind' => 'soon'],
            default     => ['label' => 'زمان کافی', 'kind' => 'ok'],
        };
    }
}
