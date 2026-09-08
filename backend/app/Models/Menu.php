<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
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
