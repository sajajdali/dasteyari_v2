<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    /**
     * تا فاز ۱۳ هیچ کد واقعی (غیر از Seeder) مستقیم Menu::update()/updateOrCreate() صدا نمی‌زد —
     * Artisan's SeedCommand کل اجرای seederها را با Model::unguarded() دور می‌زند (بخش داخلی
     * فریم‌ورک)، پس نبودِ fillable هیچ‌وقت در MenuSeeder دیده نمی‌شد؛ اولین مصرف‌کنندهٔ واقعی
     * (کارت «منوها» در تنظیمات) با MassAssignmentException می‌شکست. همان الگوی کشف‌شدهٔ
     * CaseRequest (فاز ۱۱) و Donor (فاز ۱۰) — نکته‌اش این‌جا مستند شد که فقط به «هیچ‌جا صدا زده
     * نشده» فکر نکنیم، «هیچ‌جا خارج از Seeder صدا زده نشده» را هم چک کنیم.
     */
    protected $fillable = ['panel', 'parent_id', 'label', 'icon', 'route', 'link', 'permission', 'badge_key', 'order', 'active'];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->where('active', true)->orderBy('order');
    }

    public function scopePanel(Builder $q, string $panel): Builder
    {
        return $q->where('panel', $panel)->whereNull('parent_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('active', true);
    }

    /** آیا کاربر جاری اجازهٔ دیدن این آیتم را دارد — بدون permission همیشه بله. */
    public function visibleTo(?\App\Models\User $user): bool
    {
        return empty($this->permission) || ($user && $user->can($this->permission));
    }

    /** آدرس نهایی — از نام روت یا لینک ثابت. روت ناموجود را با امنیت به «#» برمی‌گرداند. */
    public function url(): string
    {
        if ($this->route && \Illuminate\Support\Facades\Route::has($this->route)) {
            return route($this->route);
        }

        return $this->link ?: '#';
    }
}
