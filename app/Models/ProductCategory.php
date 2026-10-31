<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{
    use HasFactory, \App\Models\Concerns\Auditable;

    /**
     * فیلدهای قابل تخصیص
     */
    protected $fillable = [
        'name',
        'icon',
        'sku_prefix',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'sort_order' => 0,
        'is_active' => true,
    ];

    /**
     * پیشوند SKU فقط شامل حروف و اعداد انگلیسی و با حروف بزرگ ذخیره می‌شود
     */
    public function setSkuPrefixAttribute($value): void
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $value));

        $this->attributes['sku_prefix'] = $clean !== '' ? substr($clean, 0, 10) : null;
    }

    /**
     * محصولات این دسته‌بندی
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    /**
     * Scope برای دسته‌بندی‌های فعال
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * پیشوند SKU مؤثر (در صورت خالی بودن، پیشوند پیش‌فرض)
     */
    public function getEffectiveSkuPrefixAttribute(): string
    {
        return $this->sku_prefix ?: Product::DEFAULT_SKU_PREFIX;
    }
}
