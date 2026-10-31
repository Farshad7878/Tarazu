<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * شعبه‌ی فروش/انبار. هر شعبه موجودی مستقل دارد (branch_stocks) و کاربرِ پذیرش به یک شعبه تعلق دارد.
 */
class Branch extends Model
{
    protected $fillable = [
        'name',
        'code',
        'address',
        'phone',
        'is_main',
        'is_active',
    ];

    protected $casts = [
        'is_main' => 'boolean',
        'is_active' => 'boolean',
    ];

    public static function mainId(): ?int
    {
        return static::where('is_main', true)->value('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(BranchStock::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
