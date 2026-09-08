<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NeedGroup extends Model
{
    protected $fillable = ['title', 'icon', 'order', 'active', 'plans'];

    protected $casts = [
        'active' => 'boolean',
        'plans' => 'array',
    ];

    public function needies(): HasMany
    {
        return $this->hasMany(Needy::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(CaseRequest::class);
    }
}
